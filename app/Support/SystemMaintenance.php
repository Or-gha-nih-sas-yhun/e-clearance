<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * End-of-term maintenance for the Main Admin settings page.
 *
 * Two irreversible operations live here — promoting every student a year, and
 * clearing the term's clearance records — plus the preview counts the page
 * shows before either is allowed to run.
 *
 * Two constraints shape the implementation:
 *
 * - Every table is MyISAM, so `DB::transaction()` rolls nothing back. Each
 *   operation is therefore ordered so a failure part-way leaves the database in
 *   a state that is still correct, and re-running it is safe.
 * - Deploys never run `artisan migrate`, so a database may be missing the
 *   `status` column or whole tables. Everything is guarded with
 *   `Schema::hasTable()` / `Schema::hasColumn()`.
 */
final class SystemMaintenance
{
    /** Clearance tables cleared by a term reset, and the column naming the student. */
    private const CLEARANCE_TABLES = [
        'office_clearance_status',
        'clearance_status',
        'instructor_remarks',
        'student_submissions',
        'office_submissions',
    ];

    /** Tables whose rows own an uploaded file that must leave storage too. */
    private const UPLOAD_TABLES = ['student_submissions', 'office_submissions'];

    /**
     * How many subjects sit in each semester, so the settings page can show what
     * choosing a term will actually make available for assignment.
     *
     * @return array<string, int>
     */
    public static function subjectsPerSemester(): array
    {
        if (! Schema::hasTable('subject_codes')) {
            return [];
        }

        $counts = DB::table('subject_codes')
            ->selectRaw('semester, COUNT(*) as total')
            ->groupBy('semester')
            ->pluck('total', 'semester');

        $perSemester = [];
        foreach (AcademicTerm::SEMESTERS as $semester) {
            $perSemester[$semester] = (int) ($counts[$semester] ?? 0);
        }

        return $perSemester;
    }

    public static function statusColumnAvailable(): bool
    {
        return Schema::hasTable('student_account') && Schema::hasColumn('student_account', 'status');
    }

    /**
     * What an end-of-year promotion would do, without doing it.
     *
     * @return array{promoting: array<string, int>, graduating: int, total: int, available: bool}
     */
    public static function previewPromotion(): array
    {
        if (! Schema::hasTable('student_account')) {
            return ['promoting' => [], 'graduating' => 0, 'total' => 0, 'available' => false];
        }

        $promoting = [];
        foreach (['1', '2', '3'] as $year) {
            $promoting[$year] = self::activeStudents()->where('year_level', $year)->count();
        }

        $graduating = self::activeStudents()->where('year_level', '4')->count();

        return [
            'promoting' => $promoting,
            'graduating' => $graduating,
            'total' => array_sum($promoting) + $graduating,
            'available' => self::statusColumnAvailable(),
        ];
    }

    /**
     * Move every active student up one year. Anyone leaving 4th year is
     * deactivated instead — they keep their records but can no longer sign in,
     * and their registration entry is re-opened so the Microsoft account can be
     * issued to a future intake.
     *
     * @return array{promoted: int, graduated: int}
     */
    public static function promoteStudents(): array
    {
        if (! Schema::hasTable('student_account')) {
            return ['promoted' => 0, 'graduated' => 0];
        }

        // Graduate FIRST. Promoting 3 -> 4 before this would sweep the
        // just-promoted third years into the same deactivation.
        $graduating = self::activeStudents()->where('year_level', '4')->pluck('student_id');
        $graduated = 0;

        if ($graduating->isNotEmpty() && self::statusColumnAvailable()) {
            $graduated = DB::table('student_account')
                ->whereIn('student_id', $graduating)
                ->update(['status' => 'inactive', 'deactivated_at' => now()]);

            if (Schema::hasTable('student_registry')) {
                DB::table('student_registry')
                    ->whereIn('student_id', $graduating)
                    ->update(['status' => 'inactive', 'registered_at' => null, 'updated_at' => now()]);
            }
        }

        // Descending, so a student is never bumped twice in one run.
        $promoted = 0;
        foreach ([['3', '4'], ['2', '3'], ['1', '2']] as [$from, $to]) {
            $promoted += self::activeStudents()->where('year_level', $from)->update(['year_level' => $to]);
        }

        AuditLogger::record('maintenance.students_promoted', 'admin', null, 'student_account', null, [
            'promoted' => $promoted,
            'graduated' => $graduated,
        ]);

        return ['promoted' => $promoted, 'graduated' => $graduated];
    }

    /**
     * Row and file counts a term reset would remove.
     *
     * @return array{tables: array<string, int>, files: int, total: int}
     */
    public static function previewClearanceReset(): array
    {
        $tables = [];
        foreach (self::CLEARANCE_TABLES as $table) {
            $tables[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        $files = 0;
        foreach (self::UPLOAD_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'file_path')) {
                $files += DB::table($table)->whereNotNull('file_path')->where('file_path', '!=', '')->count();
            }
        }

        return ['tables' => $tables, 'files' => $files, 'total' => array_sum($tables)];
    }

    /**
     * Every clearance record as CSV, one `record_type` column distinguishing the
     * source table. Built before any deletion so the archive is a faithful copy
     * of what the reset is about to remove.
     */
    public static function clearanceArchiveCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['record_type', 'student_id', 'reference', 'status', 'remarks', 'recorded_at']);

        foreach (self::CLEARANCE_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                fputcsv($handle, [
                    $table,
                    $row->student_id ?? '',
                    self::archiveReference($table, $row),
                    $row->status ?? '',
                    $row->remarks ?? $row->remark ?? '',
                    $row->updated_at ?? $row->submitted_at ?? $row->created_at ?? '',
                ]);
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Clear the term. Uploaded files go before their rows, because the rows are
     * the only record of where those files live — deleting rows first would
     * strand every file on disk permanently.
     *
     * @return array{rows: int, files: int}
     */
    public static function resetClearanceRecords(): array
    {
        $files = 0;
        foreach (self::UPLOAD_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'file_path')) {
                continue;
            }

            foreach (DB::table($table)->pluck('file_path') as $path) {
                if (is_string($path) && trim($path) !== '') {
                    SecureUpload::delete($path);
                    $files++;
                }
            }
        }

        $rows = 0;
        foreach (self::CLEARANCE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows += DB::table($table)->delete();
            }
        }

        AuditLogger::record('maintenance.clearance_reset', 'admin', null, null, null, [
            'rows_deleted' => $rows,
            'files_deleted' => $files,
        ]);

        return ['rows' => $rows, 'files' => $files];
    }

    private static function activeStudents()
    {
        $query = DB::table('student_account');

        // A database without the status column has no inactive students yet.
        if (self::statusColumnAvailable()) {
            $query->where('status', 'active');
        }

        return $query;
    }

    private static function archiveReference(string $table, object $row): string
    {
        return match ($table) {
            'office_clearance_status' => (string) ($row->office_role ?? ''),
            'office_submissions' => trim((string) ($row->approver_role ?? '').' '.(string) ($row->file_name ?? '')),
            'student_submissions' => trim('subject '.(string) ($row->subject_id ?? '').' '.(string) ($row->file_name ?? '')),
            default => trim('subject '.(string) ($row->subject_id ?? '').' instructor '.(string) ($row->instructor_id ?? '')),
        };
    }
}
