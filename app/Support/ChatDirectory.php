<?php

namespace App\Support;

use App\Models\StudentAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who is allowed to message whom.
 *
 * Chat used to be a single student-to-instructor channel. It now spans every
 * staff portal that reviews a clearance — instructors, office personnel, both
 * treasurers and the registrar — so the "is this pair allowed to talk" rule can
 * no longer live inside one controller. Both sides of every conversation resolve
 * that rule here, which is what stops the office portal from reaching a student
 * the office portal cannot review.
 *
 * Scoping deliberately mirrors App\Support\ClearanceAccess, with one relaxation:
 * a conversation does not require an existing office_clearance_status row. A
 * student has to be able to ask a question *before* submitting a request, so the
 * directory scopes by assignment (section, program, department) rather than by
 * whether paperwork already exists.
 */
final class ChatDirectory
{
    /** Staff portals a student can hold a conversation with. */
    public const STAFF_ROLES = ['instructor', 'office', 'treasurer', 'registrar'];

    /** Every role that can appear as a chat participant. */
    public const ROLES = ['student', 'instructor', 'office', 'treasurer', 'registrar'];

    public function __construct(private readonly ClearanceAccess $access = new ClearanceAccess) {}

    /**
     * Staff accounts the given student may message, ready for the contact list.
     *
     * @return Collection<int, object>
     */
    public function staffContactsFor(StudentAccount $student): Collection
    {
        return collect(self::STAFF_ROLES)
            ->flatMap(fn (string $role) => $this->staffContacts($role, $student))
            ->values();
    }

    /**
     * Students the given staff account may message.
     *
     * @return Collection<int, object>
     */
    public function studentContactsFor(string $role, object $staff): Collection
    {
        if (! Schema::hasTable('student_account')) {
            return collect();
        }

        $query = DB::table('student_account')->select(
            'student_account.student_id',
            'student_account.firstname',
            'student_account.lastname',
            'student_account.program',
            'student_account.year_level',
            'student_account.section',
        )->distinct();

        $scoped = match ($role) {
            'instructor' => $this->scopeInstructorStudents($query, $staff),
            'office' => $this->access->scopeOfficeStudents($query, $staff),
            'treasurer' => $this->access->scopeTreasurerStudents($query, $staff),
            'registrar' => $query,
            default => $query->whereRaw('1 = 0'),
        };

        return $scoped->orderBy('student_account.lastname')->orderBy('student_account.firstname')->get()
            ->map(fn ($student) => (object) [
                'role' => 'student',
                'id' => (string) $student->student_id,
                'name' => trim("{$student->firstname} {$student->lastname}") ?: $student->student_id,
                // Under the name, staff want the class the student is in — the
                // ID stays as a chip and is still searchable.
                'title' => trim(implode(' · ', array_filter([
                    $student->year_level ? 'Year '.$student->year_level : null,
                    $student->section ? 'Section '.$student->section : null,
                ]))) ?: (string) $student->student_id,
                'group' => 'Students',
                'meta' => array_values(array_filter([
                    $student->program,
                    $student->student_id,
                ])),
                'program' => (string) $student->program,
                'year_level' => (string) $student->year_level,
                'section' => (string) $student->section,
            ]);
    }

    /**
     * The single authorization gate for a student/staff conversation. `$staff`
     * accepts an account when the caller is the staff member (already
     * authenticated) or an identifier when the caller is the student.
     */
    public function permits(StudentAccount $student, string $staffRole, object|string $staff): bool
    {
        if (! in_array($staffRole, self::STAFF_ROLES, true)) {
            return false;
        }

        $account = is_object($staff) ? $staff : $this->staffAccount($staffRole, $staff);

        if ($account === null) {
            return false;
        }

        return match ($staffRole) {
            'instructor' => $this->instructorTeaches($account, $student),
            'office' => $this->officeServes($account, $student),
            'treasurer' => $this->treasurerServes($account, $student),
            'registrar' => true,
            default => false,
        };
    }

    /** The account row behind a (role, id) pair, or null when it no longer exists. */
    public function staffAccount(string $role, string $identifier): ?object
    {
        $portal = PortalAccounts::PORTALS[$role] ?? null;

        if ($portal === null || trim($identifier) === '' || ! Schema::hasTable($portal['table'])) {
            return null;
        }

        return DB::table($portal['table'])->where($portal['key'], $identifier)->first();
    }

    /** Human label for a staff contact — the office it speaks for, not its guard. */
    public function staffTitle(string $role, object $account): string
    {
        return match ($role) {
            'instructor' => trim((string) ($account->department ?? '')) ?: 'Instructor',
            'office' => $this->officeLabel($account),
            'treasurer' => ucwords($this->access->treasurerOfficeRole($account) ?: 'Treasurer'),
            'registrar' => 'Registrar Office',
            default => PortalAccounts::label($role),
        };
    }

    /**
     * The staff accounts of one portal a student may message.
     *
     * Instructor and treasurer scoping is narrowed in SQL first: permits() runs a
     * query per instructor, so filtering a whole faculty table in PHP would put a
     * query per row on this page. Office and registrar scoping is pure string
     * work, so those tables are filtered in PHP without extra queries.
     *
     * @return Collection<int, object>
     */
    private function staffContacts(string $role, StudentAccount $student): Collection
    {
        $portal = PortalAccounts::PORTALS[$role] ?? null;

        if ($portal === null || ! Schema::hasTable($portal['table'])) {
            return collect();
        }

        $query = DB::table($portal['table']);

        match ($role) {
            'instructor' => $this->narrowToTeachingInstructors($query, $student),
            'treasurer' => $this->narrowToServingTreasurers($query, $student),
            default => null,
        };

        // Resolved once for the whole list rather than per instructor.
        $subjectsTaught = $role === 'instructor' ? $this->subjectsTaughtTo($student) : [];

        return $query->get()
            ->filter(fn ($account) => $this->permits($student, $role, $account))
            ->map(fn ($account) => (object) [
                'role' => $role,
                'id' => (string) $account->{$portal['key']},
                'name' => trim(($account->firstname ?? '').' '.($account->lastname ?? ''))
                    ?: trim((string) ($account->name ?? '')) ?: (string) $account->{$portal['key']},
                // An instructor is identified by what they teach this student,
                // which is what the student needs to know before messaging —
                // their department says nothing about which subject it is about.
                'title' => ($subjectsTaught[(string) $account->{$portal['key']}] ?? null)
                    ?: $this->staffTitle($role, $account),
                'group' => $this->groupLabel($role),
                'meta' => array_values(array_filter([
                    $this->groupLabel($role),
                    $role === 'instructor' ? trim((string) ($account->department ?? '')) : null,
                ])),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * The subjects each instructor handles for this student, keyed by instructor.
     *
     * Built from {@see StudentSubjects}, so an irregular student sees the
     * subjects they actually enrolled in rather than their section's block. One
     * query for the codes, not one per instructor in the contact list.
     *
     * @return array<string, string>
     */
    private function subjectsTaughtTo(StudentAccount $student): array
    {
        // Deploys never migrate, so the subject table may not exist here; the
        // contact list falls back to the instructor's department rather than
        // failing the whole chat page.
        if (! Schema::hasTable('subject_codes') || ! Schema::hasTable('instructor_assignment')) {
            return [];
        }

        $pairs = StudentSubjects::forStudent($student);

        if ($pairs->isEmpty()) {
            return [];
        }

        // The description is what a student recognises — subject codes here are
        // often bare numbers ("1234"), which would label nothing useful.
        $names = DB::table('subject_codes')
            ->whereIn('subject_id', $pairs->pluck('subject_id')->unique()->all())
            ->get(['subject_id', 'subject_code', 'subject_description'])
            ->mapWithKeys(fn ($subject) => [
                $subject->subject_id => trim((string) $subject->subject_description) ?: trim((string) $subject->subject_code),
            ]);

        $byInstructor = [];
        foreach ($pairs as $pair) {
            $name = $names[$pair->subject_id] ?? null;

            if ($name !== null && $name !== '') {
                $byInstructor[(string) $pair->instructor_id][] = $name;
            }
        }

        return array_map(
            fn (array $subjects) => implode(' · ', array_unique($subjects)),
            $byInstructor,
        );
    }

    private function groupLabel(string $role): string
    {
        return match ($role) {
            'instructor' => 'Instructors',
            'office' => 'Offices',
            'treasurer' => 'Treasury',
            'registrar' => 'Registrar',
            default => PortalAccounts::label($role),
        };
    }

    private function officeLabel(object $account): string
    {
        $role = $this->access->officeRole($account);

        if ($role === 'dean') {
            $program = $this->access->programHeadProgram($account);

            return $program ? "Program Head — {$program}" : 'Program Head';
        }

        return $role === '' ? 'Office Personnel' : ucwords($role);
    }

    /**
     * Regular students reach an instructor through instructor_assignment; an
     * irregular student's subjects are recorded per-student in
     * irregular_enrollment instead, so both routes have to count.
     */
    private function instructorTeaches(object $instructor, StudentAccount $student): bool
    {
        $instructorId = trim((string) ($instructor->instructor_id ?? ''));

        if ($instructorId === '' || ! Schema::hasTable('instructor_assignment')) {
            return false;
        }

        $assigned = DB::table('instructor_assignment')
            ->where('instructor_id', $instructorId)
            ->where('program', $student->program)
            ->where('year_level', $student->year_level)
            ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section])
            ->exists();

        if ($assigned) {
            return true;
        }

        return Schema::hasTable('irregular_enrollment')
            && DB::table('irregular_enrollment')
                ->where('instructor_id', $instructorId)
                ->where('student_id', $student->student_id)
                ->exists();
    }

    /** Program heads only serve their own program; every other office serves all. */
    private function officeServes(object $office, StudentAccount $student): bool
    {
        $role = $this->access->officeRole($office);

        if ($role === '' || ClearanceWorkflow::normalizeOfficeRole($role) === null) {
            return false;
        }

        // The College of Education Department Head reaches only the students
        // whose clearance they actually sign.
        if (! ClearanceWorkflow::officeApplies($role, $student)) {
            return false;
        }

        if ($role !== 'dean') {
            return true;
        }

        $program = $this->access->programHeadProgram($office);

        return $program !== null && $this->sameValue($student->program, $program);
    }

    private function treasurerServes(object $treasurer, StudentAccount $student): bool
    {
        return match (strtolower(trim((string) ($treasurer->treasurer_type ?? '')))) {
            'section' => $this->sameValue($student->program, $treasurer->program ?? null)
                && $this->sameValue($student->year_level, $treasurer->year_level ?? null)
                && SectionKey::matches($student->section, $treasurer->section ?? null),
            'department' => $this->sameValue($student->program, $treasurer->department ?? null),
            default => false,
        };
    }

    /** Restricts an instructor_account query to whoever teaches this student. */
    private function narrowToTeachingInstructors($query, StudentAccount $student): void
    {
        if (! Schema::hasTable('instructor_assignment')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scope) use ($student) {
            $scope->whereExists(fn ($assignment) => $assignment->from('instructor_assignment as ia')
                ->whereColumn('ia.instructor_id', 'instructor_account.instructor_id')
                ->where('ia.program', $student->program)
                ->where('ia.year_level', $student->year_level)
                ->whereRaw(SectionKey::sql('ia.section').' = '.SectionKey::sql('?'), [$student->section])
                ->selectRaw('1'));

            if (Schema::hasTable('irregular_enrollment')) {
                $scope->orWhereExists(fn ($enrollment) => $enrollment->from('irregular_enrollment as ie')
                    ->whereColumn('ie.instructor_id', 'instructor_account.instructor_id')
                    ->where('ie.student_id', $student->student_id)
                    ->selectRaw('1'));
            }
        });
    }

    /** Restricts a treasurers query to the section and department treasurers serving this student. */
    private function narrowToServingTreasurers($query, StudentAccount $student): void
    {
        $query->where(function ($scope) use ($student) {
            $scope->where(fn ($section) => $section
                ->whereRaw('LOWER(TRIM(treasurer_type)) = ?', ['section'])
                ->whereRaw('LOWER(TRIM(program)) = LOWER(TRIM(?))', [$student->program])
                ->whereRaw('LOWER(TRIM(year_level)) = LOWER(TRIM(?))', [$student->year_level])
                ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section]))
                ->orWhere(fn ($department) => $department
                    ->whereRaw('LOWER(TRIM(treasurer_type)) = ?', ['department'])
                    ->whereRaw('LOWER(TRIM(department)) = LOWER(TRIM(?))', [$student->program]));
        });
    }

    private function scopeInstructorStudents($query, object $instructor)
    {
        $instructorId = trim((string) ($instructor->instructor_id ?? ''));

        if ($instructorId === '' || ! Schema::hasTable('instructor_assignment')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scope) use ($instructorId) {
            $scope->whereExists(fn ($assignment) => $assignment->from('instructor_assignment as ia')
                ->whereColumn('ia.program', 'student_account.program')
                ->whereColumn('ia.year_level', 'student_account.year_level')
                ->whereRaw(SectionKey::sql('ia.section').' = '.SectionKey::sql('student_account.section'))
                ->where('ia.instructor_id', $instructorId)
                ->selectRaw('1'));

            if (Schema::hasTable('irregular_enrollment')) {
                $scope->orWhereExists(fn ($enrollment) => $enrollment->from('irregular_enrollment as ie')
                    ->whereColumn('ie.student_id', 'student_account.student_id')
                    ->where('ie.instructor_id', $instructorId)
                    ->selectRaw('1'));
            }
        });
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        $left = strtolower(trim((string) $left));
        $right = strtolower(trim((string) $right));

        return $left !== '' && $right !== '' && hash_equals($left, $right);
    }
}
