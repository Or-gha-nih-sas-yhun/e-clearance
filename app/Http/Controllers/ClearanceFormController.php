<?php

namespace App\Http\Controllers;

use App\Support\SectionKey;
use App\Support\StudentSubjects;

use App\Models\ClearanceVerificationToken;
use App\Models\StudentAccount;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ClearanceFormController extends Controller
{
    public function student()
    {
        $student = $this->approvedStudent();
        if (! $student) {
            return redirect()->route('student.clearance-updates')->with('flash', [
                'type' => 'error',
                'message' => 'Your clearance form will be available after Registrar approval.',
            ]);
        }

        return view('clearance.form', $this->dataFor($student, false));
    }

    public function studentDownload()
    {
        $student = $this->approvedStudent();
        if (! $student) {
            return redirect()->route('student.clearance-updates')->with('flash', [
                'type' => 'error',
                'message' => 'Your clearance PDF will be available after Registrar approval.',
            ]);
        }

        $html = view('clearance.form', $this->dataFor($student, false) + [
            'pdfDownload' => true,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Times-Roman');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'MCC-Clearance-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $student->student_id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function registrar(string $studentId)
    {
        $student = StudentAccount::where('student_id', $studentId)->firstOrFail();
        Gate::forUser(Auth::guard('registrar')->user())->authorize('reviewRegistrar', $student);

        return view('clearance.form', $this->dataFor($student, true));
    }

    public function verify(string $studentId)
    {
        $student = StudentAccount::where('student_id', $studentId)->firstOrFail();
        Gate::forUser(Auth::guard('registrar')->user())->authorize('reviewRegistrar', $student);

        return view('clearance.form', $this->dataFor($student, true));
    }

    public function verifyToken(string $token)
    {
        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);

        $verification = ClearanceVerificationToken::where('token_hash', hash('sha256', $token))->firstOrFail();
        $student = StudentAccount::where('student_id', $verification->student_id)->firstOrFail();
        $verification->forceFill(['last_verified_at' => now()])->save();

        $summary = $this->verificationSummary($student);
        $studentName = trim($student->firstname.' '.$student->lastname);
        $maskedStudentId = str_repeat('•', max(strlen($student->student_id) - 4, 0))
            .substr($student->student_id, -4);

        return response()
            ->view('clearance.verification', [
                'student' => $student,
                'studentName' => $studentName,
                'maskedStudentId' => $maskedStudentId,
                'overallStatus' => $summary['overallStatus'],
                'token' => $verification,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function approvedStudent(): ?StudentAccount
    {
        $student = Auth::guard('student')->user();
        if (! $student instanceof StudentAccount) {
            throw new AccessDeniedHttpException;
        }

        $registrarApproved = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->whereRaw("LOWER(TRIM(office_role)) = 'registrar'")
            ->where('status', 'Approved')
            ->exists();

        return $registrarApproved ? $student : null;
    }

    private function dataFor(StudentAccount $student, bool $isRegistrar): array
    {
        // Regular students take their section's block; an irregular student takes
        // the subjects they enrolled in themselves. StudentSubjects::pairs() is
        // that rule as one derived table, so the printed form lists exactly the
        // subjects the student's own clearance page does.
        $subjects = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'sp')
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'sp.subject_id')
            ->join('instructor_account as i', 'i.instructor_id', '=', 'sp.instructor_id')
            ->leftJoin('clearance_status as cs', function ($join) use ($student) {
                $join->on('cs.subject_id', '=', 'sp.subject_id')->on('cs.instructor_id', '=', 'sp.instructor_id')
                    ->where('cs.student_id', '=', $student->student_id);
            })
            ->where('sp.student_id', $student->student_id)
            ->select('sp.instructor_id', 'sc.subject_code', 'sc.subject_description', 'i.firstname', 'i.lastname',
                DB::raw("COALESCE(cs.status, 'Pending') as status"), 'cs.remarks', 'cs.updated_at')
            ->orderBy('sc.subject_code')->get();

        $officeLabels = [
            'property custodian' => 'Property Custodian',
            'scc adviser' => 'SCC Adviser',
            'sas director' => 'SAS Director',
            'guidance office' => 'Guidance Office',
            'library' => 'Library',
            'registrar' => 'Registrar',
        ];
        $signatures = DB::table('esignatures')->get()->keyBy(fn ($signature) => $signature->signer_id.'|'.$signature->signer_role);
        $subjects->transform(function ($subject) use ($signatures) {
            $subject->signature_data = $subject->status === 'Approved'
                ? optional($signatures->get($subject->instructor_id.'|instructor'))->signature_data : null;

            return $subject;
        });
        $statuses = DB::table('office_clearance_status')->where('student_id', $student->student_id)
            ->select('office_role', 'status', 'remarks', 'approver_id')->get()
            ->keyBy(fn ($status) => $this->normalizeOfficeRole($status->office_role));
        $officePersonnel = $this->officePersonnel($student->program);
        $offices = collect($officeLabels)->map(function ($label, $role) use ($statuses, $signatures, $officePersonnel) {
            $status = $statuses->get($role);
            $signature = $status && $status->status === 'Approved' && $status->approver_id
                ? $signatures->firstWhere('signer_id', $status->approver_id) : null;

            $approverId = $status->approver_id ?? null;
            $officerName = $approverId && isset($officePersonnel['byId'][$approverId])
                ? $officePersonnel['byId'][$approverId]
                : ($officePersonnel['byRole'][$role] ?? null);

            return (object) ['label' => $label, 'status' => $status->status ?? 'Pending',
                'remarks' => $status->remarks ?? null, 'signature_data' => $signature->signature_data ?? null,
                'officer_name' => $officerName, 'personnel_id' => $approverId];
        });
        $subjectDone = $subjects->isNotEmpty() && $subjects->every(fn ($subject) => $subject->status === 'Approved');
        $officeDone = $offices->every(fn ($office) => $office->status === 'Approved')
            && (($statuses->get('dean')->status ?? 'Pending') === 'Approved');

        $deanStatusRecord = $statuses->get('dean');
        $deanApproverId = $deanStatusRecord->approver_id ?? null;
        $deanName = $deanApproverId && isset($officePersonnel['byId'][$deanApproverId])
            ? $officePersonnel['byId'][$deanApproverId]
            : $officePersonnel['deanName'];

        $verificationUrl = route('clearance.verify', ['token' => $this->verificationTokenFor($student)]);
        $qrCode = new QrCode(data: $verificationUrl, size: 140, margin: 4);
        $qrCodeDataUri = (new PngWriter)->write($qrCode)->getDataUri();

        return compact('student', 'subjects', 'offices', 'isRegistrar', 'verificationUrl', 'qrCodeDataUri') + [
            'deanName' => $deanName,
            'deanStatus' => $statuses->get('dean')->status ?? 'Pending',
            'overallStatus' => $subjectDone && $officeDone ? 'Cleared' : 'In Progress',
        ];
    }

    private function verificationTokenFor(StudentAccount $student): string
    {
        $record = ClearanceVerificationToken::where('student_id', $student->student_id)->first();

        if ($record) {
            try {
                $token = Crypt::decryptString($record->token_encrypted);
                if (strlen($token) === 64 && ctype_alnum($token)
                    && hash_equals($record->token_hash, hash('sha256', $token))) {
                    return $token;
                }
            } catch (\Throwable) {
                // Rotate an unreadable token below (for example after an APP_KEY rotation).
            }
        }

        $token = Str::random(64);
        ClearanceVerificationToken::updateOrCreate(
            ['student_id' => $student->student_id],
            [
                'token_hash' => hash('sha256', $token),
                'token_encrypted' => Crypt::encryptString($token),
                'issued_at' => now(),
                'last_verified_at' => null,
            ],
        );

        return $token;
    }

    /** @return array{overallStatus: string} */
    private function verificationSummary(StudentAccount $student): array
    {
        $assignedSubjects = StudentSubjects::forStudent($student);

        $approvedSubjects = $assignedSubjects->filter(fn ($assignment) => DB::table('clearance_status')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('instructor_id', $assignment->instructor_id)
            ->where('status', 'Approved')
            ->exists());

        $requiredOffices = [
            'section treasurer', 'department treasurer', 'property custodian', 'scc adviser',
            'sas director', 'guidance office', 'library', 'dean', 'registrar',
        ];
        $approvedOffices = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->where('status', 'Approved')
            ->pluck('office_role')
            ->map(fn ($role) => $this->normalizeOfficeRole($role))
            ->unique()
            ->intersect($requiredOffices)
            ->count();

        $cleared = $assignedSubjects->isNotEmpty()
            && $approvedSubjects->count() === $assignedSubjects->count()
            && $approvedOffices === count($requiredOffices);

        return ['overallStatus' => $cleared ? 'Cleared' : 'In Progress'];
    }

    private function officePersonnel(?string $program): array
    {
        $byId = [];
        $byRole = [];
        $deansByProgram = [];

        if (Schema::hasTable('admin_personnel')) {
            foreach (DB::table('admin_personnel')->get(['personnel_id', 'firstname', 'lastname', 'office', 'role']) as $personnel) {
                $name = trim("{$personnel->firstname} {$personnel->lastname}");
                if ($name === '') {
                    continue;
                }

                if ($personnel->personnel_id) {
                    $byId[$personnel->personnel_id] = $name;
                }

                // The personnel record may identify the assignment in either the
                // office or role field. Index both so either setup appears on the form.
                foreach ([$personnel->office, $personnel->role] as $assignment) {
                    $role = $this->normalizeOfficeRole($assignment);
                    if ($role !== '' && ! isset($byRole[$role])) {
                        $byRole[$role] = $name;
                    }
                }

                if (preg_match('/^program_head_(.+)$/', (string) $personnel->role, $matches)) {
                    $deansByProgram[strtolower($matches[1])] = $name;
                }
            }
        }

        if (Schema::hasTable('registrar')) {
            foreach (DB::table('registrar')->get(['registrar_id', 'firstname', 'lastname']) as $registrar) {
                $name = trim("{$registrar->firstname} {$registrar->lastname}");
                if ($name !== '') {
                    $byId[$registrar->registrar_id] = $name;
                    $byRole['registrar'] ??= $name;
                }
            }
        }

        $deanName = $deansByProgram[strtolower(trim((string) $program))] ?? $byRole['dean'] ?? null;

        return compact('byId', 'byRole', 'deanName');
    }

    private function normalizeOfficeRole(?string $role): string
    {
        $role = strtolower(str_replace(['_', '-'], ' ', trim((string) $role)));

        return match (true) {
            str_contains($role, 'program head'), str_contains($role, 'dean') => 'dean',
            str_contains($role, 'property') => 'property custodian',
            str_contains($role, 'scc') => 'scc adviser',
            str_contains($role, 'sas') => 'sas director',
            str_contains($role, 'guidance') => 'guidance office',
            str_contains($role, 'library') => 'library',
            str_contains($role, 'registrar') => 'registrar',
            default => $role,
        };
    }
}
