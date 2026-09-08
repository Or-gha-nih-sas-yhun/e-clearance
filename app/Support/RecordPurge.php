<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a record has to take everything that belonged to it with it.
 *
 * The live tables are MyISAM, so MySQL parses the ON DELETE CASCADE the
 * migrations declare and then ignores it — nothing is cleaned up for us, and
 * leftover rows surface as blank entries in every listing that joins them back
 * to their owner.
 *
 * Two rules shape what each method removes:
 *
 * - Only what the deleted party *owns* goes. A student owns their clearance
 *   rows and submissions. An approver merely referenced a student's clearance
 *   record through `approver_id`, so deleting that account must not touch the
 *   student's clearance history.
 * - Every table is checked with Schema::hasTable() first. Deploys never run
 *   migrations, so a database can be missing the newer tables entirely.
 */
final class RecordPurge
{
    /** Tables keyed by the student who owns the row. */
    private const STUDENT_TABLES = [
        'clearance_request',
        'clearance_status',
        'clearance_signatures',
        'clearance_verification_tokens',
        'office_clearance_status',
        'office_requirements',
        'office_submissions',
        'student_submissions',
        'instructor_remarks',
        'irregular_enrollment',
        'password_resets',
    ];

    /** Tables keyed by the instructor who owns the row. */
    private const INSTRUCTOR_TABLES = [
        'instructor_assignment',
        'clearance_status',
        'student_submissions',
        'instructor_remarks',
        'irregular_enrollment',
    ];

    /** Tables keyed by the subject the row belongs to. */
    private const SUBJECT_TABLES = [
        'clearance_status',
        'instructor_assignment',
        'instructor_remarks',
        'irregular_enrollment',
        'student_submissions',
    ];

    public static function student(string $studentId, ?string $email = null): void
    {
        // clearance_approval hangs off clearance_request, not off the student.
        if (self::hasTables('clearance_request', 'clearance_approval')) {
            $requestIds = DB::table('clearance_request')->where('student_id', $studentId)->pluck('id');

            if ($requestIds->isNotEmpty()) {
                DB::table('clearance_approval')->whereIn('clearance_id', $requestIds)->delete();
            }
        }

        self::deleteUploads('office_submissions', 'student_id', $studentId);
        self::deleteUploads('student_submissions', 'student_id', $studentId);

        foreach (self::STUDENT_TABLES as $table) {
            self::deleteBy($table, 'student_id', $studentId);
        }

        // The registration roster belongs to the college, not to the student, so
        // the row stays — but it is re-opened, otherwise a deleted student could
        // never register again from the same Microsoft account.
        if (Schema::hasTable('student_registry')) {
            DB::table('student_registry')
                ->where('student_id', $studentId)
                ->update(['status' => 'inactive', 'registered_at' => null, 'updated_at' => now()]);
        }

        self::accountTraces('student', $studentId, $email);
    }

    public static function instructor(string $instructorId, ?string $email = null): void
    {
        // A per-subject clearance row names the instructor who signs it. With the
        // instructor gone the row can never be actioned, so it goes too.
        self::deleteUploads('student_submissions', 'instructor_id', $instructorId);

        foreach (self::INSTRUCTOR_TABLES as $table) {
            self::deleteBy($table, 'instructor_id', $instructorId);
        }

        self::accountTraces('instructor', $instructorId, $email);
    }

    public static function treasurer(string $treasurerId, ?string $email = null): void
    {
        self::accountTraces('treasurer', $treasurerId, $email);
    }

    public static function registrar(string $registrarId, ?string $email = null): void
    {
        self::accountTraces('registrar', $registrarId, $email);
    }

    public static function officePersonnel(string $personnelId, ?string $email = null): void
    {
        self::accountTraces('office', $personnelId, $email);
    }

    public static function subject(int|string $subjectId): void
    {
        self::deleteUploads('student_submissions', 'subject_id', $subjectId);

        foreach (self::SUBJECT_TABLES as $table) {
            self::deleteBy($table, 'subject_id', $subjectId);
        }
    }

    /**
     * An assignment ties one instructor and subject to one program/year/section.
     * Removing it clears the clearance rows it produced for the students in that
     * section, and nothing belonging to other sections.
     */
    /**
     * Everything one irregular enrolment owns.
     *
     * Dropping a subject from an irregular student's list has to take its
     * clearance record, remarks and uploaded file with it — a stranded
     * `clearance_status` row would keep counting towards the dean and registrar
     * prerequisite for a subject the student is no longer enrolled in.
     */
    public static function enrollment(string $studentId, int $subjectId, string $instructorId): void
    {
        foreach (['clearance_status', 'instructor_remarks', 'student_submissions', 'irregular_enrollment'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $scope = fn () => DB::table($table)
                ->where('student_id', $studentId)
                ->where('subject_id', $subjectId)
                ->where('instructor_id', $instructorId);

            if ($table === 'student_submissions' && Schema::hasColumn($table, 'file_path')) {
                foreach ($scope()->pluck('file_path') as $path) {
                    SecureUpload::delete($path);
                }
            }

            $scope()->delete();
        }
    }

    public static function instructorAssignment(object $assignment): void
    {
        if (! Schema::hasTable('student_account')) {
            return;
        }

        $studentIds = DB::table('student_account')
            ->where('program', $assignment->program ?? null)
            ->where('year_level', $assignment->year_level ?? null)
            ->where('section', $assignment->section ?? null)
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return;
        }

        foreach (['clearance_status', 'instructor_remarks', 'irregular_enrollment', 'student_submissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $scope = fn () => DB::table($table)
                ->where('instructor_id', $assignment->instructor_id ?? null)
                ->where('subject_id', $assignment->subject_id ?? null)
                ->whereIn('student_id', $studentIds);

            if ($table === 'student_submissions' && Schema::hasColumn($table, 'file_path')) {
                foreach ($scope()->pluck('file_path') as $path) {
                    SecureUpload::delete($path);
                }
            }

            $scope()->delete();
        }
    }

    /**
     * Rows every portal account leaves behind: its notifications, its side of any
     * conversation, its saved signature and any pending password reset.
     */
    private static function accountTraces(string $role, string $accountId, ?string $email): void
    {
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('user_id', $accountId)
                ->when(
                    Schema::hasColumn('notifications', 'recipient_role'),
                    fn ($query) => $query->where('recipient_role', $role)
                )
                ->delete();
        }

        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')
                ->where(fn ($query) => $query
                    ->where(fn ($sent) => $sent->where('sender_id', $accountId)->where('sender_role', $role))
                    ->orWhere(fn ($received) => $received->where('receiver_id', $accountId)->where('receiver_role', $role))
                )
                ->delete();
        }

        if (Schema::hasTable('chat_support')) {
            DB::table('chat_support')->where('sender_id', $accountId)->where('sender_role', $role)->delete();
        }

        if ($role !== 'student') {
            self::deleteBy('esignatures', 'signer_id', $accountId);
        }

        if ($email !== null && $email !== '' && Schema::hasTable('account_password_resets')) {
            DB::table('account_password_resets')->whereRaw('LOWER(email) = ?', [strtolower($email)])->delete();
        }
    }

    /** Deletes the uploaded files themselves, which no cascade would ever reach. */
    private static function deleteUploads(string $table, string $column, int|string $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'file_path')) {
            return;
        }

        foreach (DB::table($table)->where($column, $value)->pluck('file_path') as $path) {
            SecureUpload::delete($path);
        }
    }

    private static function deleteBy(string $table, string $column, int|string $value): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->where($column, $value)->delete();
        }
    }

    private static function hasTables(string ...$tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
