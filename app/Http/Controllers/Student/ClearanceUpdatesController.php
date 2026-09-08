<?php

namespace App\Http\Controllers\Student;

use App\Support\SectionKey;

use App\Http\Controllers\Controller;
use App\Models\ClearanceStatus;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use App\Support\SecureUpload;
use App\Support\StudentSubjects;
use App\Support\SubmissionFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClearanceUpdatesController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $workflow = $this->buildWorkflowData($student);

        return view('student.clearance-updates', array_merge([
            'student' => $student,
        ], $workflow));
    }

    public function submitInstructor(Request $request)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subject_codes,subject_id'],
            'instructor_id' => ['required', 'string', 'max:50', 'exists:instructor_account,instructor_id'],
        ]);

        $student = Auth::guard('student')->user();

        abort_unless(ClearanceWorkflow::instructorIsAssigned(
            $student,
            (int) $data['subject_id'],
            $data['instructor_id'],
        ), 403);

        $existingStatus = DB::table('clearance_status')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $data['subject_id'])
            ->where('instructor_id', $data['instructor_id'])
            ->value('status');

        if ($existingStatus !== null && $existingStatus !== 'Rejected') {
            throw ValidationException::withMessages([
                'subject_id' => 'This instructor clearance request is already active.',
            ]);
        }

        ClearanceStatus::upsertStatus($student->student_id, (int) $data['subject_id'], $data['instructor_id'], [
            'status' => 'Pending',
            'remarks' => null,
        ]);

        Notification::create([
            'user_id' => $data['instructor_id'],
            'recipient_role' => 'instructor',
            'message' => "New clearance request from {$student->full_name}.",
            'notif_type' => 'clearance',
            'link_url' => route('instructor.clearance'),
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Instructor clearance request submitted.']);
    }

    public function submitOffice(Request $request)
    {
        $data = $request->validate([
            'office_role' => ['required', 'string', 'max:50'],
        ]);

        $student = Auth::guard('student')->user();

        $officeRole = $this->validatedOfficeRole($data['office_role']);
        $this->ensureOfficeRequestMayBeOpened($student, $officeRole);

        DB::table('office_clearance_status')->updateOrInsert(
            ['student_id' => $student->student_id, 'office_role' => $officeRole],
            [
                'status' => 'Pending',
                'remarks' => null,
                'approver_id' => $student->student_id,
                'updated_at' => now(),
            ]
        );

        $this->notifyOfficeRecipients($officeRole, $student);

        return back()->with('flash', ['type' => 'success', 'message' => 'Office clearance request submitted.']);
    }

    public function uploadOfficeSubmission(Request $request)
    {
        $data = $request->validate([
            'office_role' => ['required', 'string', 'max:50'],
            'submission_file' => SecureUpload::rules(),
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $student = Auth::guard('student')->user();
        $officeRole = $this->validatedOfficeRole($data['office_role']);

        abort_if(DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->where('office_role', $officeRole)
            ->where('status', 'Approved')
            ->exists(), 409, 'An approved clearance cannot be replaced with a new upload.');

        if (! ClearanceWorkflow::prerequisitesMet($student, $officeRole)) {
            throw ValidationException::withMessages([
                'office_role' => 'Complete the required earlier clearance steps before submitting to this office.',
            ]);
        }

        $storedFile = SecureUpload::store(
            $data['submission_file'],
            "office_submissions/{$student->student_id}/{$officeRole}",
        );

        try {
            DB::transaction(function () use ($student, $officeRole, $storedFile, $data): void {
                DB::table('office_submissions')->insert([
                    'student_id' => $student->student_id,
                    'personnel_id' => $officeRole,
                    'office' => $officeRole,
                    'approver_role' => $officeRole,
                    'file_path' => $storedFile['path'],
                    'file_name' => $storedFile['original_name'],
                    'file_type' => $storedFile['mime_type'],
                    'description' => $data['description'] ?? null,
                    'status' => 'Pending',
                    'submitted_at' => now(),
                ]);

                DB::table('office_clearance_status')->updateOrInsert(
                    ['student_id' => $student->student_id, 'office_role' => $officeRole],
                    [
                        'status' => 'Pending',
                        'remarks' => null,
                        'approver_id' => $student->student_id,
                        'updated_at' => now(),
                    ],
                );
            });
        } catch (Throwable $exception) {
            SecureUpload::delete($storedFile['path']);
            throw $exception;
        }

        $this->notifyOfficeRecipients($officeRole, $student);

        return back()->with('flash', ['type' => 'success', 'message' => 'Document sent to '.ucwords($officeRole).' for review.']);
    }

    public function viewOfficeSubmission(int $submission, Request $request)
    {
        $student = Auth::guard('student')->user();
        $record = DB::table('office_submissions')
            ->where('id', $submission)
            ->where('student_id', $student->student_id)
            ->first();

        abort_unless($record, 404);

        return SubmissionFileResponse::make($record, $request);
    }

    private function normalizeOfficeRole(string $officeRole): string
    {
        return ClearanceWorkflow::normalizeOfficeRole($officeRole) ?? strtolower(trim($officeRole));
    }

    private function validatedOfficeRole(string $officeRole): string
    {
        $normalized = ClearanceWorkflow::normalizeOfficeRole($officeRole);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'office_role' => 'Select a valid clearance office.',
            ]);
        }

        return $normalized;
    }

    private function ensureOfficeRequestMayBeOpened(StudentAccount $student, string $officeRole): void
    {
        if (! ClearanceWorkflow::prerequisitesMet($student, $officeRole)) {
            throw ValidationException::withMessages([
                'office_role' => 'Complete the required earlier clearance steps before submitting to this office.',
            ]);
        }

        if (! ClearanceWorkflow::canOpenOfficeRequest($student, $officeRole)) {
            throw ValidationException::withMessages([
                'office_role' => 'This office clearance request is already active or approved.',
            ]);
        }
    }

    private function notifyOfficeRecipients(string $officeRole, object $student): void
    {
        $message = "New clearance request from {$student->full_name} for ".ucwords($officeRole).'.';
        $link = route('office.clearance.requests');
        $recipientId = null;
        $recipientRole = 'office';

        if ($officeRole === 'registrar') {
            $recipientRole = 'registrar';
            $recipientId = Schema::hasTable('registrar')
                ? DB::table('registrar')->orderBy('id')->value('registrar_id')
                : null;
            $link = route('registrar.student-clearance');
        } elseif ($officeRole === 'section treasurer') {
            $recipientRole = 'treasurer';
            $recipientId = Schema::hasTable('treasurers') ? DB::table('treasurers')->where('treasurer_type', 'section')
                ->where('program', $student->program)->where('year_level', $student->year_level)
                ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section])
                ->orderBy('id')->value('treasurer_id') : null;
            $link = route('treasurer.dashboard');
        } elseif ($officeRole === 'department treasurer') {
            $recipientRole = 'treasurer';
            $recipientId = Schema::hasTable('treasurers') ? DB::table('treasurers')->where('treasurer_type', 'department')
                ->where('department', $student->program)->orderBy('id')->value('treasurer_id') : null;
            $link = route('treasurer.dashboard');
        } else {
            $roleMap = [
                'property custodian' => 'property_custodian', 'scc adviser' => 'scc_adviser',
                'sas director' => 'sas_director', 'guidance office' => 'guidance', 'library' => 'library',
                'dean' => 'program_head_'.strtolower($student->program),
            ];
            if (isset($roleMap[$officeRole]) && Schema::hasTable('admin_personnel')) {
                $recipientId = DB::table('admin_personnel')->where('role', $roleMap[$officeRole])
                    ->orderBy('id')->value('personnel_id');
            }
        }

        if ($recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'recipient_role' => $recipientRole,
                'message' => $message,
                'notif_type' => 'clearance',
                'link_url' => $link,
            ]);
        }
    }

    private function resolveOfficeApproverName(string $officeRole, object $student, ?string $approverId): string
    {
        $account = null;

        if ($officeRole === 'registrar' && Schema::hasTable('registrar')) {
            $query = DB::table('registrar');
            $account = $approverId ? (clone $query)->where('registrar_id', $approverId)->first() : null;
            $account ??= $query->orderBy('id')->first();
        } elseif (in_array($officeRole, ['section treasurer', 'department treasurer'], true) && Schema::hasTable('treasurers')) {
            $query = DB::table('treasurers');
            $account = $approverId ? (clone $query)->where('treasurer_id', $approverId)->first() : null;

            if (! $account && $officeRole === 'section treasurer') {
                $account = $query->where('treasurer_type', 'section')
                    ->where('program', $student->program)
                    ->where('year_level', $student->year_level)
                    ->whereRaw(SectionKey::sql('section').' = '.SectionKey::sql('?'), [$student->section])
                    ->orderBy('id')
                    ->first();
            } elseif (! $account) {
                $account = $query->where('treasurer_type', 'department')
                    ->where('department', $student->program)
                    ->orderBy('id')
                    ->first();
            }
        } elseif (Schema::hasTable('admin_personnel')) {
            $roleMap = [
                'property custodian' => 'property_custodian',
                'scc adviser' => 'scc_adviser',
                'sas director' => 'sas_director',
                'guidance office' => 'guidance',
                'library' => 'library',
                'dean' => 'program_head_'.strtolower($student->program),
            ];
            $query = DB::table('admin_personnel');
            $account = $approverId ? (clone $query)->where('personnel_id', $approverId)->first() : null;

            if (! $account && isset($roleMap[$officeRole])) {
                $account = $query->where('role', $roleMap[$officeRole])->orderBy('id')->first();
            }
        }

        if (! $account) {
            return 'No account assigned';
        }

        $name = trim(implode(' ', array_filter([
            $account->firstname ?? null,
            $account->middlename ?? null,
            $account->lastname ?? null,
            $account->suffix ?? null,
        ])));

        return $name !== '' ? $name : ($account->email ?? 'Account holder');
    }

    public function buildWorkflowData($student): array
    {
        $subjectClearances = DB::table('clearance_status')
            ->leftJoin('subject_codes', 'clearance_status.subject_id', '=', 'subject_codes.subject_id')
            ->leftJoin('instructor_account', 'clearance_status.instructor_id', '=', 'instructor_account.instructor_id')
            ->where('clearance_status.student_id', $student->student_id)
            ->select(
                'clearance_status.subject_id',
                'clearance_status.instructor_id',
                'clearance_status.status',
                'clearance_status.remarks',
                'clearance_status.updated_at',
                'subject_codes.subject_code',
                'subject_codes.subject_description',
                'instructor_account.firstname as instructor_firstname',
                'instructor_account.lastname as instructor_lastname'
            )
            ->orderBy('subject_codes.subject_code')
            ->get();

        // An irregular student's list is the subjects they enrolled in, not their
        // section's block; StudentSubjects decides which of the two applies. The
        // pairs come back as ids, so the display columns are looked up either
        // side of them rather than joined onto two different source tables.
        $enrolled = StudentSubjects::forStudent($student);

        $subjectLookup = DB::table('subject_codes')
            ->whereIn('subject_id', $enrolled->pluck('subject_id')->unique()->all())
            ->get(['subject_id', 'subject_code', 'subject_description'])
            ->keyBy('subject_id');

        $instructorLookup = DB::table('instructor_account')
            ->whereIn('instructor_id', $enrolled->pluck('instructor_id')->unique()->all())
            ->get(['instructor_id', 'firstname', 'lastname'])
            ->keyBy('instructor_id');

        $instructorAssignments = $enrolled
            ->map(fn ($pair) => (object) [
                'subject_id' => $pair->subject_id,
                'instructor_id' => $pair->instructor_id,
                'subject_code' => $subjectLookup->get($pair->subject_id)->subject_code ?? null,
                'subject_description' => $subjectLookup->get($pair->subject_id)->subject_description ?? null,
                'instructor_firstname' => $instructorLookup->get($pair->instructor_id)->firstname ?? null,
                'instructor_lastname' => $instructorLookup->get($pair->instructor_id)->lastname ?? null,
            ])
            ->sortBy('subject_code')
            ->values();

        $officeClearances = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->orderBy('office_role')
            ->get();

        $officeSubmissionMap = [];
        if (Schema::hasTable('office_submissions')) {
            $officeSubmissions = DB::table('office_submissions')
                ->where('student_id', $student->student_id)
                ->orderByDesc('submitted_at')
                ->get();

            foreach ($officeSubmissions as $submission) {
                $submissionRole = $this->normalizeOfficeRole($submission->office ?: $submission->approver_role);
                $officeSubmissionMap[$submissionRole] ??= $submission;
            }
        }

        $subjectStatusMap = [];
        foreach ($subjectClearances as $clearance) {
            $subjectStatusMap[$clearance->subject_id.':'.$clearance->instructor_id] = $clearance;
        }

        $instructorItems = [];
        foreach ($instructorAssignments as $assignment) {
            $status = $subjectStatusMap[$assignment->subject_id.':'.$assignment->instructor_id] ?? null;
            $itemStatus = $status->status ?? 'Pending';
            $item = [
                'subject_id' => $assignment->subject_id,
                'instructor_id' => $assignment->instructor_id,
                'subject_code' => $assignment->subject_code,
                'subject_description' => $assignment->subject_description,
                'instructor_name' => trim(($assignment->instructor_firstname ?? '').' '.($assignment->instructor_lastname ?? '')),
                'status' => $itemStatus,
                'remarks' => $status->remarks ?? null,
                'updated_at' => $status->updated_at ?? null,
                'is_approved' => in_array($itemStatus, ['Approved', 'Cleared'], true),
            ];
            $item['can_submit'] = ($status->status ?? null) === null || ($status->status ?? null) === 'Rejected';
            $instructorItems[] = $item;
        }

        $officeItems = [];
        $officeStatusMap = [];
        foreach ($officeClearances as $clearance) {
            $normalizedRole = $this->normalizeOfficeRole($clearance->office_role);
            $officeStatusMap[$normalizedRole] = $clearance;
        }

        $registrarApproved = ($officeStatusMap['registrar']->status ?? null) === 'Approved';

        $allInstructorApproved = count($instructorItems) > 0 && count(array_filter($instructorItems, fn ($item) => $item['is_approved'])) === count($instructorItems);

        $officeRoles = [
            ['key' => 'section treasurer', 'label' => 'Section Treasurer', 'requires' => []],
            ['key' => 'department treasurer', 'label' => 'Department Treasurer', 'requires' => ['section treasurer']],
            ['key' => 'property custodian', 'label' => 'Property Custodian', 'requires' => []],
            ['key' => 'scc adviser', 'label' => 'SCC Adviser', 'requires' => []],
            ['key' => 'sas director', 'label' => 'SAS Director', 'requires' => []],
            ['key' => 'guidance office', 'label' => 'Guidance Office', 'requires' => []],
            ['key' => 'library', 'label' => 'Library', 'requires' => []],
            ['key' => 'dean', 'label' => 'Dean', 'requires' => ['section treasurer', 'department treasurer']],
            ['key' => 'registrar', 'label' => 'Registrar', 'requires' => ['section treasurer', 'department treasurer', 'property custodian', 'scc adviser', 'sas director', 'guidance office', 'library', 'dean']],
        ];

        foreach ($officeRoles as $role) {
            $status = $officeStatusMap[$role['key']] ?? null;
            $canSubmit = true;
            foreach ($role['requires'] as $requiredRole) {
                $requiredStatus = $officeStatusMap[$requiredRole] ?? null;
                if (($requiredStatus->status ?? 'Not Requested') !== 'Approved') {
                    $canSubmit = false;
                    break;
                }
            }

            if ($role['key'] === 'dean' && ! $allInstructorApproved) {
                $canSubmit = false;
            }

            $officeItems[] = [
                'key' => $role['key'],
                'label' => $role['label'],
                'status' => $status->status ?? 'Not Requested',
                'remarks' => $status->remarks ?? null,
                'updated_at' => $status->updated_at ?? null,
                'approver_name' => $this->resolveOfficeApproverName($role['key'], $student, $status->approver_id ?? null),
                'can_submit' => $canSubmit && (($status->status ?? null) === null || ($status->status ?? null) === 'Rejected'),
                'requires' => $role['requires'],
                'submission' => $officeSubmissionMap[$role['key']] ?? null,
            ];
        }

        $subjectsApproved = count(array_filter($instructorItems, fn ($item) => $item['is_approved']));
        $subjectsTotal = count($instructorItems);

        return [
            'subjectClearances' => $subjectClearances,
            'instructorAssignments' => $instructorAssignments,
            'instructorItems' => $instructorItems,
            'officeClearances' => $officeClearances,
            'officeItems' => $officeItems,
            'registrarApproved' => $registrarApproved,
            'summary' => [
                'subjectsTotal' => $subjectsTotal,
                'subjectsApproved' => $subjectsApproved,
                'officeTotal' => count($officeItems),
                'officeApproved' => count(array_filter($officeItems, fn ($item) => ($item['status'] ?? 'Pending') === 'Approved')),
            ],
        ];
    }
}
