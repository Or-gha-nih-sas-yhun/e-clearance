<?php

namespace App\Support;

use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ClearanceAccess
{
    public function instructorCanReview(object $instructor, StudentAccount $student, int $subjectId): bool
    {
        $instructorId = trim((string) ($instructor->instructor_id ?? ''));

        if ($instructorId === '' || $subjectId < 1) {
            return false;
        }

        $regularAssignment = DB::table('instructor_assignment as ia')
            ->join('student_account as sa', function ($join) {
                $join->on('sa.program', '=', 'ia.program')
                    ->on('sa.year_level', '=', 'ia.year_level')
                    ->whereRaw(SectionKey::sql('sa.section').' = '.SectionKey::sql('ia.section'));
            })
            ->where('ia.instructor_id', $instructorId)
            ->where('ia.subject_id', $subjectId)
            ->where('sa.student_id', $student->student_id)
            ->exists();

        if ($regularAssignment) {
            return true;
        }

        return Schema::hasTable('irregular_enrollment')
            && DB::table('irregular_enrollment')
                ->where('instructor_id', $instructorId)
                ->where('subject_id', $subjectId)
                ->where('student_id', $student->student_id)
                ->exists();
    }

    public function officeCanReview(object $office, StudentAccount $student): bool
    {
        $officeRole = $this->officeRole($office);

        if ($officeRole === '') {
            return false;
        }

        if ($officeRole === 'dean') {
            $program = $this->programHeadProgram($office);

            if ($program === null || ! $this->sameValue($student->program, $program)) {
                return false;
            }
        }

        // The College of Education Department Head sits above the BSED and BEED
        // program heads and reaches only their students.
        if (! ClearanceWorkflow::officeApplies($officeRole, $student)) {
            return false;
        }

        return $this->studentHasOfficeRequest($student, $officeRole);
    }

    public function treasurerCanReview(object $treasurer, StudentAccount $student): bool
    {
        $type = strtolower(trim((string) ($treasurer->treasurer_type ?? '')));

        $withinAssignment = false;

        if ($type === 'section') {
            $withinAssignment = $this->sameValue($student->program, $treasurer->program ?? null)
                && $this->sameValue($student->year_level, $treasurer->year_level ?? null)
                && SectionKey::matches($student->section, $treasurer->section ?? null);
        }

        if ($type === 'department') {
            $withinAssignment = $this->sameValue($student->program, $treasurer->department ?? null);
        }

        return $withinAssignment
            && $this->studentHasOfficeRequest($student, $this->treasurerOfficeRole($treasurer));
    }

    public function registrarCanReview(StudentAccount $student): bool
    {
        return $this->studentHasOfficeRequest($student, 'registrar');
    }

    public function treasurerOfficeRole(object $treasurer): string
    {
        return match (strtolower(trim((string) ($treasurer->treasurer_type ?? '')))) {
            'section' => 'section treasurer',
            'department' => 'department treasurer',
            default => '',
        };
    }

    public function officeRole(?object $office): string
    {
        if ($office === null) {
            return '';
        }

        foreach ([$office->role ?? null, $office->office ?? null] as $assignment) {
            $normalized = $this->normalizeOfficeRole((string) $assignment);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    public function programHeadProgram(?object $office): ?string
    {
        if ($office === null) {
            return null;
        }

        foreach ([$office->role ?? null, $office->office ?? null] as $assignment) {
            $normalized = strtolower(str_replace(['_', '-'], ' ', trim((string) $assignment)));
            $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

            if (preg_match('/\bprogram head\s+([a-z0-9]+)\b/', $normalized, $matches) === 1) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    public function scopeOfficeStudents($query, object $office, string $studentTable = 'student_account')
    {
        $officeRole = $this->officeRole($office);

        if ($officeRole === '') {
            return $query->whereRaw('1 = 0');
        }

        if ($officeRole === 'dean') {
            $program = $this->programHeadProgram($office);

            if ($program === null) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereRaw(
                "LOWER(TRIM({$studentTable}.program)) = LOWER(TRIM(?))",
                [$program]
            );
        }

        if ($officeRole === 'education department head') {
            $programs = ClearanceWorkflow::EDUCATION_PROGRAMS;
            $placeholders = implode(', ', array_fill(0, count($programs), 'LOWER(TRIM(?))'));

            $query->whereRaw("LOWER(TRIM({$studentTable}.program)) IN ({$placeholders})", $programs);
        }

        return $query;
    }

    public function scopeTreasurerStudents($query, object $treasurer, string $studentTable = 'student_account')
    {
        $type = strtolower(trim((string) ($treasurer->treasurer_type ?? '')));

        if ($type === 'section') {
            return $query
                ->whereRaw("LOWER(TRIM({$studentTable}.program)) = LOWER(TRIM(?))", [$treasurer->program])
                ->whereRaw("LOWER(TRIM({$studentTable}.year_level)) = LOWER(TRIM(?))", [$treasurer->year_level])
                ->whereRaw(
                    SectionKey::sql("{$studentTable}.section").' = '.SectionKey::sql('?'),
                    [$treasurer->section]
                );
        }

        if ($type === 'department') {
            return $query->whereRaw(
                "LOWER(TRIM({$studentTable}.program)) = LOWER(TRIM(?))",
                [$treasurer->department]
            );
        }

        return $query->whereRaw('1 = 0');
    }

    private function normalizeOfficeRole(string $officeRole): string
    {
        $normalized = strtolower(str_replace(['_', '-'], ' ', trim($officeRole)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        if ($normalized === '') {
            return '';
        }

        return match (true) {
            str_contains($normalized, 'program head'),
            $normalized === 'dean' => 'dean',
            str_contains($normalized, 'property custodian') => 'property custodian',
            str_contains($normalized, 'scc adviser'),
            str_contains($normalized, 'scc advisor'),
            str_contains($normalized, 'ssc adviser'),
            str_contains($normalized, 'ssc advisor') => 'scc adviser',
            str_contains($normalized, 'sas director') => 'sas director',
            str_contains($normalized, 'guidance') => 'guidance office',
            str_contains($normalized, 'library') => 'library',
            str_contains($normalized, 'registrar') => 'registrar',
            str_contains($normalized, 'section') && str_contains($normalized, 'treasurer') => 'section treasurer',
            str_contains($normalized, 'department') && str_contains($normalized, 'treasurer') => 'department treasurer',
            default => $normalized,
        };
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        $left = strtolower(trim((string) $left));
        $right = strtolower(trim((string) $right));

        return $left !== '' && $right !== '' && hash_equals($left, $right);
    }

    private function studentHasOfficeRequest(StudentAccount $student, string $officeRole): bool
    {
        if ($officeRole === '') {
            return false;
        }

        return DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->whereRaw('LOWER(TRIM(office_role)) = ?', [$officeRole])
            ->exists();
    }
}
