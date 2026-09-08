<?php

namespace App\Http\Controllers\Student;

use App\Support\SectionKey;
use App\Support\StudentSubjects;

use App\Http\Controllers\Controller;
use App\Models\ClearanceStatus;
use App\Models\StudentSubmission;
use App\Support\SecureUpload;
use App\Support\SubmissionFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmissionRemarkController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $subjectClearances = DB::table('clearance_status')
            ->leftJoin('subject_codes', 'clearance_status.subject_id', '=', 'subject_codes.subject_id')
            ->leftJoin('instructor_account', 'clearance_status.instructor_id', '=', 'instructor_account.instructor_id')
            ->where('clearance_status.student_id', $student->student_id)
            ->select(
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

        $submissions = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->leftJoin('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
            ->leftJoin('instructor_account as iac', 'iac.instructor_id', '=', 'ia.instructor_id')
            ->leftJoin('student_submissions as ss', function ($join) use ($student) {
                $join->on('ss.subject_id', '=', 'ia.subject_id')
                    ->on('ss.instructor_id', '=', 'ia.instructor_id')
                    ->where('ss.student_id', '=', $student->student_id);
            })
            ->leftJoin('clearance_status as cs', function ($join) use ($student) {
                $join->on('cs.subject_id', '=', 'ia.subject_id')
                    ->on('cs.instructor_id', '=', 'ia.instructor_id')
                    ->where('cs.student_id', '=', $student->student_id);
            })
            ->where('ia.student_id', $student->student_id)
            ->select([
                'ia.subject_id',
                'sc.subject_code',
                'sc.subject_description',
                'ia.instructor_id',
                'iac.firstname as instructor_firstname',
                'iac.lastname as instructor_lastname',
                'ss.id as submission_id',
                'ss.file_name',
                'ss.file_type',
                'ss.description',
                'ss.submitted_at',
                DB::raw("COALESCE(cs.status,'Pending') as clearance_status"),
                'cs.remarks as clearance_remarks',
            ])
            ->orderBy('sc.subject_code')
            ->get()
            ->map(function ($submission) use ($student) {
                if (empty($submission->clearance_remarks)) {
                    $submission->clearance_remarks = DB::table('instructor_remarks')
                        ->where('student_id', $student->student_id)
                        ->where('subject_id', $submission->subject_id)
                        ->where('instructor_id', $submission->instructor_id)
                        ->latest('created_at')
                        ->value('remark');
                }

                return $submission;
            });

        return view('student.submission-remark', compact('student', 'subjectClearances', 'submissions'));
    }

    public function upload(Request $request)
    {
        $student = Auth::guard('student')->user();

        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subject_codes,subject_id'],
            'instructor_id' => ['required', 'string', 'max:50', 'exists:instructor_account,instructor_id'],
            'submission_file' => SecureUpload::rules(),
            'description' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(
            StudentSubjects::covers($student, (int) $data['subject_id'], $data['instructor_id']),
            403,
        );

        $storedFile = SecureUpload::store(
            $data['submission_file'],
            "student_submissions/{$student->student_id}"
        );

        $submission = StudentSubmission::firstOrNew([
            'student_id' => $student->student_id,
            'subject_id' => $data['subject_id'],
            'instructor_id' => $data['instructor_id'],
        ]);
        $previousPath = $submission->exists ? $submission->file_path : null;

        try {
            DB::transaction(function () use ($submission, $storedFile, $data, $student) {
                $submission->file_path = $storedFile['path'];
                $submission->file_name = $storedFile['original_name'];
                $submission->file_type = $storedFile['mime_type'];
                $submission->description = $data['description'] ?? ($data['remarks'] ?? null);
                $submission->submitted_at = now();
                $submission->save();

                ClearanceStatus::upsertStatus($student->student_id, $data['subject_id'], $data['instructor_id'], [
                    'status' => 'Pending',
                ]);
            });
        } catch (Throwable $exception) {
            SecureUpload::delete($storedFile['path']);

            throw $exception;
        }

        if ($previousPath && $previousPath !== $storedFile['path']) {
            SecureUpload::delete($previousPath);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Submission sent to your instructor for review.']);
    }

    public function download(StudentSubmission $submission, ?Request $request = null)
    {
        $student = Auth::guard('student')->user();

        abort_unless($submission->student_id === optional($student)->student_id, 403);

        return SubmissionFileResponse::make($submission, $request ?? request());
    }
}
