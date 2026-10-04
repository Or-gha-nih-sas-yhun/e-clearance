<?php

namespace App\Support;

use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;

final class ClearanceWorkflow
{
    public const OFFICE_ROLES = [
        'section treasurer',
        'department treasurer',
        'property custodian',
        'scc adviser',
        'sas director',
        'guidance office',
        'library',
        'dean',
        'education department head',
        'registrar',
    ];

    /**
     * The only programs that answer to the College of Education Department Head.
     *
     * BSED and BEED are two degree programs under one college, each with its own
     * program head. Their students clear that program head first and then the
     * department head above them; every other program has no such step, which is
     * why the office chain is per-student rather than one fixed list.
     */
    public const EDUCATION_PROGRAMS = ['BSED', 'BEED'];

    /** Offices that only some students pass through, and who they apply to. */
    private const CONDITIONAL_ROLES = ['education department head' => self::EDUCATION_PROGRAMS];

    public static function normalizeOfficeRole(string $officeRole): ?string
    {
        $normalized = strtolower(trim(str_replace(['_', '-'], ' ', $officeRole)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        $role = match (true) {
            (str_contains($normalized, 'section') && str_contains($normalized, 'treasurer')),
            $normalized === 'section' => 'section treasurer',
            (str_contains($normalized, 'department') && str_contains($normalized, 'treasurer')),
            $normalized === 'department' => 'department treasurer',
            str_contains($normalized, 'education') && (str_contains($normalized, 'department head')
                || str_contains($normalized, 'dept head')) => 'education department head',
            str_contains($normalized, 'dean'), str_contains($normalized, 'program head'),
            str_contains($normalized, 'department head') => 'dean',
            str_contains($normalized, 'registrar') => 'registrar',
            in_array($normalized, ['ssc adviser', 'ssc advisor', 'scc advisor'], true) => 'scc adviser',
            default => $normalized,
        };

        return in_array($role, self::OFFICE_ROLES, true) ? $role : null;
    }

    /**
     * An irregular student's subjects are the ones they enrolled in themselves,
     * not their section's block — {@see StudentSubjects} owns that distinction.
     */
    public static function instructorIsAssigned(
        StudentAccount $student,
        int $subjectId,
        string $instructorId,
    ): bool {
        return StudentSubjects::covers($student, $subjectId, $instructorId);
    }

    /** Whether this office is part of the given student's clearance at all. */
    public static function officeApplies(string $officeRole, ?object $student): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole) ?? '';

        if ($officeRole === '') {
            return false;
        }

        $programs = self::CONDITIONAL_ROLES[$officeRole] ?? null;

        if ($programs === null) {
            return true;
        }

        $program = strtoupper(trim((string) ($student->program ?? '')));

        return in_array($program, $programs, true);
    }

    /**
     * The offices this student must clear, in order, each with what it waits on.
     *
     * This is the one description of the chain. The student's clearance page,
     * the printed form and {@see prerequisitesMet()} all read it, so a new
     * office cannot appear in one of them and be missing from another — which
     * is exactly how a student could be shown a step the server would refuse.
     *
     * @return list<array{key: string, label: string, requires: list<string>}>
     */
    public static function officeChainFor(?object $student): array
    {
        $labels = [
            'section treasurer' => 'Section Treasurer',
            'department treasurer' => 'Department Treasurer',
            'property custodian' => 'Property Custodian',
            'scc adviser' => 'SSC Adviser',
            'sas director' => 'SAS Director',
            'guidance office' => 'Guidance Office',
            'library' => 'Library',
            'dean' => 'Program Head',
            'education department head' => 'College of Education Department Head',
            'registrar' => 'Registrar',
        ];

        $chain = [];
        $earlier = [];

        foreach (self::OFFICE_ROLES as $role) {
            if (! self::officeApplies($role, $student)) {
                continue;
            }

            $chain[] = [
                'key' => $role,
                'label' => $labels[$role],
                'requires' => match ($role) {
                    'department treasurer' => ['section treasurer'],
                    'dean' => ['section treasurer', 'department treasurer'],
                    // The department head signs only after the student's own
                    // program head has, which is the rule that makes BSED and
                    // BEED pass through two heads rather than one.
                    'education department head' => ['dean'],
                    // Everything before it, so an office added to the chain is
                    // required by the registrar without a second list to edit.
                    'registrar' => $earlier,
                    default => [],
                },
            ];

            $earlier[] = $role;
        }

        return $chain;
    }

    public static function prerequisitesMet(StudentAccount $student, string $officeRole): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole) ?? '';

        if (! self::officeApplies($officeRole, $student)) {
            return false;
        }

        if ($officeRole === 'library' && ! LibraryEvaluation::submissionAllowed($student->student_id)) {
            return false;
        }
        if ($officeRole === 'guidance office' && ! LibraryEvaluation::submissionAllowed($student->student_id, 'guidance')) {
            return false;
        }

        $requiredOffices = collect(self::officeChainFor($student))
            ->firstWhere('key', $officeRole)['requires'] ?? [];

        if ($requiredOffices !== []) {
            $approved = DB::table('office_clearance_status')
                ->where('student_id', $student->student_id)
                ->where('status', 'Approved')
                ->pluck('office_role')
                ->map(fn ($role) => self::normalizeOfficeRole((string) $role))
                ->filter()
                ->unique()
                ->intersect($requiredOffices)
                ->count();

            if ($approved !== count($requiredOffices)) {
                return false;
            }
        }

        return ! in_array($officeRole, ['dean', 'registrar'], true)
            || self::allInstructorClearancesApproved($student);
    }

    public static function canOpenOfficeRequest(StudentAccount $student, string $officeRole): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole);
        if ($officeRole === null) {
            return false;
        }

        $status = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->get(['office_role', 'status'])
            ->first(fn ($record) => self::normalizeOfficeRole((string) $record->office_role) === $officeRole)
            ?->status;

        return $status === null || $status === 'Rejected';
    }

    public static function allInstructorClearancesApproved(StudentAccount $student): bool
    {
        $assignments = StudentSubjects::forStudent($student);

        // An irregular student who has not declared a single subject yet has
        // nothing to be cleared of, so the dean and registrar stay closed until
        // they do — the same as a section with no assignments at all.
        if ($assignments->isEmpty()) {
            return false;
        }

        return $assignments->every(fn ($assignment) => DB::table('clearance_status')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('instructor_id', $assignment->instructor_id)
            ->where('status', 'Approved')
            ->exists());
    }
}
