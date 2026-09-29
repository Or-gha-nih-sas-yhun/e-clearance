<?php

namespace App\Support;

use App\Models\StudentAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which (subject, instructor) pairs a student has to get cleared.
 *
 * Regular and irregular enrollment supply the student's required subjects:
 *
 * - A **regular** student takes their section's block, so their subjects are
 *   whatever `instructor_assignment` holds for their program + year + section.
 * - An **irregular** student is by definition not on that block. They pick each
 *   subject and its instructor themselves, and those choices are the rows in
 *   `irregular_enrollment`. Their section's block does *not* apply — clearing
 *   subjects they are not taking is the exact problem the manual list solves.
 * - A **bridging** subject is optional for either student type. Existing data
 *   marks it with `(BRIDGING)` in the subject code; the dedicated `Bridging`
 *   semester is also supported. It is excluded from the section block and the
 *   manual irregular picker until the student opts in.
 *
 * Six places used to hand-roll the regular half of that query — the student's
 * clearance page, the submit gate, the dean/registrar prerequisite, the printed
 * form, the QR verification, and every instructor listing. Each one silently
 * excluded irregular students, so an irregular student could not submit and no
 * instructor could see them. They all go through here now.
 *
 * Everything is guarded with `Schema::hasTable()`/`hasColumn()` because deploys
 * never run `artisan migrate`: on a database without the optional enrollment
 * tables, the older regular and irregular behavior remains available.
 */
final class StudentSubjects
{
    public const BRIDGING_SEMESTER = 'Bridging';

    public static function irregularTableAvailable(): bool
    {
        return Schema::hasTable('irregular_enrollment');
    }

    /** Whether this database can tell regular and irregular students apart. */
    public static function typeColumnAvailable(): bool
    {
        return Schema::hasTable('student_account')
            && Schema::hasColumn('student_account', 'student_type');
    }

    /** Whether optional bridging enrolments can be stored on this database. */
    public static function bridgingAvailable(): bool
    {
        return Schema::hasTable('bridging_enrollment')
            && self::hasBridgingClassification();
    }

    public static function isIrregular(?object $student): bool
    {
        if ($student === null || ! self::typeColumnAvailable()) {
            return false;
        }

        return strtolower(trim((string) ($student->student_type ?? ''))) === 'irregular';
    }

    /** Whether this student manages their own subject list. */
    public static function picksOwnSubjects(?object $student): bool
    {
        return self::isIrregular($student) && self::irregularTableAvailable();
    }

    /**
     * The pairs this student must clear, newest join order irrelevant.
     *
     * @return Collection<int, object{subject_id: int, instructor_id: string}>
     */
    public static function forStudent(object $student): Collection
    {
        if (self::picksOwnSubjects($student)) {
            $subjects = DB::table('irregular_enrollment as ie')
                ->when(self::hasBridgingClassification(), fn ($query) => self::excludeBridging(
                    $query->join('subject_codes as sc', 'sc.subject_id', '=', 'ie.subject_id'),
                    'sc',
                ))
                ->where('ie.student_id', $student->student_id)
                ->orderBy('ie.subject_id')
                ->get(['ie.subject_id', 'ie.instructor_id']);
        } else {
            $subjects = DB::table('instructor_assignment as ia')
                ->when(self::hasBridgingClassification(), fn ($query) => self::excludeBridging(
                    $query->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id'),
                    'sc',
                ))
                ->where('ia.program', $student->program)
                ->where('ia.year_level', $student->year_level)
                ->whereRaw(SectionKey::sql('ia.section').' = '.SectionKey::sql('?'), [$student->section])
                ->orderBy('ia.subject_id')
                ->get(['ia.subject_id', 'ia.instructor_id']);
        }

        if (! self::bridgingAvailable()) {
            return $subjects;
        }

        return $subjects
            ->concat(self::bridgingSubjectsFor($student))
            ->unique(fn ($subject) => $subject->subject_id.':'.$subject->instructor_id)
            ->sortBy('subject_id')
            ->values();
    }

    /** Whether this exact pair is one the student is enrolled under. */
    public static function covers(object $student, int $subjectId, string $instructorId): bool
    {
        $instructorId = trim($instructorId);

        if ($subjectId < 1 || $instructorId === '') {
            return false;
        }

        return self::forStudent($student)->contains(fn ($subject) =>
            (int) $subject->subject_id === $subjectId
            && trim((string) $subject->instructor_id) === $instructorId
        );
    }

    /** Bridging assignments currently offered to this student's section. */
    public static function bridgingAssignmentsFor(object $student): Collection
    {
        if (! self::bridgingAvailable() || ! Schema::hasTable('instructor_account')) {
            return collect();
        }

        return DB::table('instructor_assignment as ia')
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
            ->leftJoin('instructor_account as i', 'i.instructor_id', '=', 'ia.instructor_id')
            ->where('ia.program', $student->program)
            ->where('ia.year_level', $student->year_level)
            ->whereRaw(SectionKey::sql('ia.section').' = '.SectionKey::sql('?'), [$student->section])
            ->where(fn ($query) => self::includeBridging($query, 'sc'))
            ->orderBy('sc.subject_code')
            ->get([
                'ia.assignment_id', 'ia.subject_id', 'ia.instructor_id',
                'sc.subject_code', 'sc.subject_description', 'sc.year_level', 'sc.semester',
                'i.firstname as instructor_firstname', 'i.lastname as instructor_lastname',
            ]);
    }

    /** Optional bridging pairs this student has explicitly accepted. */
    public static function bridgingSubjectsFor(object $student): Collection
    {
        if (! self::bridgingAvailable()) {
            return collect();
        }

        $selected = DB::table('bridging_enrollment')
            ->where('student_id', $student->student_id)
            ->get(['subject_id', 'instructor_id'])
            ->keyBy(fn ($row) => $row->subject_id.':'.$row->instructor_id);

        return self::bridgingAssignmentsFor($student)
            ->filter(fn ($assignment) => $selected->has($assignment->subject_id.':'.$assignment->instructor_id))
            ->map(fn ($assignment) => (object) [
                'subject_id' => $assignment->subject_id,
                'instructor_id' => $assignment->instructor_id,
            ])
            ->values();
    }

    /**
     * Every (student_id, subject_id, instructor_id) pair in the college, as a
     * derived table to join listings against.
     *
     * Use it as `DB::query()->fromSub(StudentSubjects::pairs(), 'sp')`. It is
     * the same rule as {@see forStudent()} expressed set-wise, so a listing and
     * a student's own page can never disagree about who clears what.
     */
    public static function pairs(): Builder
    {
        $regular = DB::table('instructor_assignment as ia')
            ->join('student_account as sa', function ($join) {
                $join->on('sa.program', '=', 'ia.program')
                    ->on('sa.year_level', '=', 'ia.year_level')
                    ->whereRaw(SectionKey::sql('sa.section').' = '.SectionKey::sql('ia.section'));
            })
            ->select('sa.student_id as student_id', 'ia.subject_id as subject_id', 'ia.instructor_id as instructor_id');

        if (self::hasBridgingClassification()) {
            self::excludeBridging(
                $regular->join('subject_codes as rsc', 'rsc.subject_id', '=', 'ia.subject_id'),
                'rsc',
            );
        }

        if (! self::typeColumnAvailable() || ! self::irregularTableAvailable()) {
            return self::appendBridgingPairs($regular);
        }

        // An irregular student is excluded from their section's block and picks
        // up their own rows from the union below instead.
        $regular->whereRaw("LOWER(TRIM(COALESCE(sa.student_type, ''))) <> 'irregular'");

        $irregular = DB::table('irregular_enrollment as ie')
            ->join('student_account as isa', 'isa.student_id', '=', 'ie.student_id')
            ->whereRaw("LOWER(TRIM(COALESCE(isa.student_type, ''))) = 'irregular'")
            ->select('ie.student_id as student_id', 'ie.subject_id as subject_id', 'ie.instructor_id as instructor_id');

        if (self::hasBridgingClassification()) {
            self::excludeBridging(
                $irregular->join('subject_codes as isc', 'isc.subject_id', '=', 'ie.subject_id'),
                'isc',
            );
        }

        return self::appendBridgingPairs($regular->unionAll($irregular));
    }

    /**
     * The instructors an irregular student may choose from for one subject:
     * whoever the Main Admin has assigned to teach it, in any section.
     *
     * @return Collection<int, object>
     */
    public static function instructorsTeaching(int $subjectId): Collection
    {
        return DB::table('instructor_assignment as ia')
            ->join('instructor_account as i', 'i.instructor_id', '=', 'ia.instructor_id')
            ->where('ia.subject_id', $subjectId)
            ->distinct()
            ->orderBy('i.lastname')->orderBy('i.firstname')
            ->get(['i.instructor_id', 'i.firstname', 'i.lastname', 'i.department']);
    }

    /**
     * Every subject that has at least one instructor assigned to it, with those
     * instructors — the whole menu an irregular student picks from.
     *
     * @return Collection<int, object>
     */
    public static function offeredSubjects(): Collection
    {
        // Only this term's subjects, the same rule the assignment form follows.
        return AcademicTerm::scopeSubjects(
            DB::table('instructor_assignment as ia')
                ->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
                ->join('instructor_account as i', 'i.instructor_id', '=', 'ia.instructor_id')
                ->where(fn ($query) => self::excludeBridging($query, 'sc')),
            'sc.semester',
        )
            ->distinct()
            ->orderBy('sc.subject_code')->orderBy('i.lastname')
            ->get([
                'sc.subject_id', 'sc.subject_code', 'sc.subject_description',
                'sc.year_level', 'sc.program', 'sc.semester',
                'i.instructor_id', 'i.firstname as instructor_firstname', 'i.lastname as instructor_lastname',
            ]);
    }

    private static function appendBridgingPairs(Builder $query): Builder
    {
        if (! self::bridgingAvailable()) {
            return $query;
        }

        $bridging = DB::table('bridging_enrollment as be')
            ->join('student_account as bsa', 'bsa.student_id', '=', 'be.student_id')
            ->join('instructor_assignment as bia', function ($join) {
                $join->on('bia.subject_id', '=', 'be.subject_id')
                    ->on('bia.instructor_id', '=', 'be.instructor_id')
                    ->on('bia.program', '=', 'bsa.program')
                    ->on('bia.year_level', '=', 'bsa.year_level')
                    ->whereRaw(SectionKey::sql('bia.section').' = '.SectionKey::sql('bsa.section'));
            })
            ->join('subject_codes as bsc', 'bsc.subject_id', '=', 'be.subject_id')
            ->where(fn ($query) => self::includeBridging($query, 'bsc'))
            ->select('be.student_id as student_id', 'be.subject_id as subject_id', 'be.instructor_id as instructor_id');

        return $query->unionAll($bridging);
    }

    private static function includeBridging(Builder $query, string $alias): Builder
    {
        return $query->whereRaw("LOWER(TRIM(COALESCE({$alias}.semester, ''))) = ?", [strtolower(self::BRIDGING_SEMESTER)])
            ->orWhereRaw("LOWER(COALESCE({$alias}.subject_code, '')) LIKE ?", ['%bridging%']);
    }

    private static function excludeBridging(Builder $query, string $alias): Builder
    {
        return $query->whereRaw("LOWER(TRIM(COALESCE({$alias}.semester, ''))) <> ?", [strtolower(self::BRIDGING_SEMESTER)])
            ->whereRaw("LOWER(COALESCE({$alias}.subject_code, '')) NOT LIKE ?", ['%bridging%']);
    }

    private static function hasBridgingClassification(): bool
    {
        return Schema::hasTable('subject_codes')
            && Schema::hasColumn('subject_codes', 'semester')
            && Schema::hasColumn('subject_codes', 'subject_code');
    }
}
