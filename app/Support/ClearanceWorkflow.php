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
        'registrar',
    ];

    public static function normalizeOfficeRole(string $officeRole): ?string
    {
        $normalized = strtolower(trim(str_replace(['_', '-'], ' ', $officeRole)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        $role = match (true) {
            (str_contains($normalized, 'section') && str_contains($normalized, 'treasurer')),
            $normalized === 'section' => 'section treasurer',
            (str_contains($normalized, 'department') && str_contains($normalized, 'treasurer')),
            $normalized === 'department' => 'department treasurer',
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

    public static function prerequisitesMet(StudentAccount $student, string $officeRole): bool
    {
        $officeRole = self::normalizeOfficeRole($officeRole) ?? '';

        $requiredOffices = match ($officeRole) {
            'department treasurer' => ['section treasurer'],
            'dean' => ['section treasurer', 'department treasurer'],
            'registrar' => [
                'section treasurer', 'department treasurer', 'property custodian', 'scc adviser',
                'sas director', 'guidance office', 'library', 'dean',
            ],
            default => [],
        };

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
