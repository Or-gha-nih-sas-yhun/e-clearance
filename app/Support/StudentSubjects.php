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
 * There are two ways to be enrolled and they are mutually exclusive:
 *
 * - A **regular** student takes their section's block, so their subjects are
 *   whatever `instructor_assignment` holds for their program + year + section.
 * - An **irregular** student is by definition not on that block. They pick each
 *   subject and its instructor themselves, and those choices are the rows in
 *   `irregular_enrollment`. Their section's block does *not* apply — clearing
 *   subjects they are not taking is the exact problem the manual list solves.
 *
 * Six places used to hand-roll the regular half of that query — the student's
 * clearance page, the submit gate, the dean/registrar prerequisite, the printed
 * form, the QR verification, and every instructor listing. Each one silently
 * excluded irregular students, so an irregular student could not submit and no
 * instructor could see them. They all go through here now.
 *
 * Everything is guarded with `Schema::hasTable()`/`hasColumn()` because deploys
 * never run `artisan migrate`: on a database without `irregular_enrollment` or
 * without `student_account.student_type`, every student is simply treated as
 * regular and the system behaves exactly as it did before.
 */
final class StudentSubjects
{
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
            return DB::table('irregular_enrollment')
                ->where('student_id', $student->student_id)
                ->orderBy('subject_id')
                ->get(['subject_id', 'instructor_id']);
        }

        return DB::table('instructor_assignment')
            ->where('program', $student->program)
            ->where('year_level', $student->year_level)
            ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section])
            ->orderBy('subject_id')
            ->get(['subject_id', 'instructor_id']);
    }

    /** Whether this exact pair is one the student is enrolled under. */
    public static function covers(object $student, int $subjectId, string $instructorId): bool
    {
        $instructorId = trim($instructorId);

        if ($subjectId < 1 || $instructorId === '') {
            return false;
        }

        if (self::picksOwnSubjects($student)) {
            return DB::table('irregular_enrollment')
                ->where('student_id', $student->student_id)
                ->where('subject_id', $subjectId)
                ->where('instructor_id', $instructorId)
                ->exists();
        }

        return DB::table('instructor_assignment')
            ->where('subject_id', $subjectId)
            ->where('instructor_id', $instructorId)
            ->where('program', $student->program)
            ->where('year_level', $student->year_level)
            ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section])
            ->exists();
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

        if (! self::typeColumnAvailable() || ! self::irregularTableAvailable()) {
            return $regular;
        }

        // An irregular student is excluded from their section's block and picks
        // up their own rows from the union below instead.
        $regular->whereRaw("LOWER(TRIM(COALESCE(sa.student_type, ''))) <> 'irregular'");

        $irregular = DB::table('irregular_enrollment as ie')
            ->join('student_account as isa', 'isa.student_id', '=', 'ie.student_id')
            ->whereRaw("LOWER(TRIM(COALESCE(isa.student_type, ''))) = 'irregular'")
            ->select('ie.student_id as student_id', 'ie.subject_id as subject_id', 'ie.instructor_id as instructor_id');

        return $regular->unionAll($irregular);
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
                ->join('instructor_account as i', 'i.instructor_id', '=', 'ia.instructor_id'),
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
}
