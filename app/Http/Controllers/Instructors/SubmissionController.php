<?php

namespace App\Http\Controllers\Instructors;

use App\Support\SectionKey;
use App\Support\StudentSubjects;

use App\Http\Controllers\Controller;
use App\Models\ClearanceStatus;
use App\Models\InstructorRemark;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use App\Support\SubmissionFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    // GET /instructor/submissions  (was view_submissions.php GET render)
    public function index(Request $request)
    {
        $instructorId = Auth::guard('instructor')->user()->instructor_id;
        $studentNameExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(COALESCE(sa.firstname, '') || ' ' || COALESCE(sa.lastname, ''))"
            : "TRIM(CONCAT(COALESCE(sa.firstname, ''), ' ', COALESCE(sa.lastname, '')))";

        $fStudent = $request->query('student_id', '');
        $fSubject = (int) $request->query('subject_id', 0);
        $fStatus = in_array($request->query('status'), ['Pending', 'Approved'], true)
            ? $request->query('status') : '';

        // The pairs table already resolves regular-vs-irregular enrolment, so an
        // irregular student who picked this instructor shows up here too.
        $students = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->join('student_account as sa', 'sa.student_id', '=', 'ia.student_id')
            ->where('ia.instructor_id', $instructorId)
            ->distinct()
            ->select('sa.student_id', 'sa.firstname', 'sa.lastname', DB::raw("{$studentNameExpression} as full_name"),
                'sa.program', 'sa.year_level', 'sa.section')
            ->orderBy('sa.lastname')->orderBy('sa.firstname')->get();

        $subjects = DB::table('instructor_assignment as ia')
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
            ->where('ia.instructor_id', $instructorId)
            ->distinct()
            ->select('ia.subject_id', 'sc.subject_code', 'sc.subject_description')
            ->orderBy('sc.subject_code')->get();

        $submissions = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->join('student_account as sa', 'sa.student_id', '=', 'ia.student_id')
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
            ->leftJoin('clearance_status as cs', function ($j) {
                $j->on('cs.student_id', '=', 'sa.student_id')
                    ->on('cs.subject_id', '=', 'ia.subject_id')
                    ->on('cs.instructor_id', '=', 'ia.instructor_id');
            })
            ->leftJoin('student_submissions as ss', function ($j) {
                $j->on('ss.student_id', '=', 'sa.student_id')
                    ->on('ss.subject_id', '=', 'ia.subject_id')
                    ->on('ss.instructor_id', '=', 'ia.instructor_id');
            })
            ->where('ia.instructor_id', $instructorId)
            ->when($fStudent, fn ($q) => $q->where('sa.student_id', $fStudent))
            ->when($fSubject, fn ($q) => $q->where('ia.subject_id', $fSubject))
            ->when($fStatus === 'Approved', fn ($q) => $q->where('cs.status', 'Approved'))
            ->when($fStatus === 'Pending', fn ($q) => $q->whereRaw("COALESCE(cs.status,'Pending') <> 'Approved'"))
            ->select([
                'ss.id',
                DB::raw('sa.student_id as student_id'),
                'ss.subject_id', 'ss.instructor_id',
                'ss.file_name', 'ss.file_type', 'ss.description', 'ss.submitted_at',
                'sc.subject_code', 'sc.subject_description',
                DB::raw("{$studentNameExpression} as student_name"),
                'sa.program', 'sa.year_level', 'sa.section',
                DB::raw("CASE WHEN cs.status='Approved' THEN 'Approved' ELSE 'Pending' END as clearance_status"),
                'cs.remarks as clearance_remarks',
                'ia.subject_id as assigned_subject_id',
            ])
            ->orderByRaw('CASE WHEN ss.submitted_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('ss.submitted_at')
            ->paginate(25)->withQueryString();

        $allRemarks = InstructorRemark::query()
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'instructor_remarks.subject_id')
            ->join('student_account as sa', 'sa.student_id', '=', 'instructor_remarks.student_id')
            ->leftJoin('clearance_status as remark_status', function ($join) {
                $join->on('remark_status.student_id', '=', 'instructor_remarks.student_id')
                    ->on('remark_status.subject_id', '=', 'instructor_remarks.subject_id')
                    ->on('remark_status.instructor_id', '=', 'instructor_remarks.instructor_id');
            })
            ->where('instructor_remarks.instructor_id', $instructorId)
            ->select('instructor_remarks.*', 'sc.subject_code', 'sc.subject_description',
                DB::raw("{$studentNameExpression} as student_name"),
                DB::raw("CASE WHEN remark_status.status='Approved' THEN 'Approved' ELSE 'Pending' END as clearance_status"))
            ->latest('instructor_remarks.created_at')->limit(100)->get();

        $stats = StudentSubmission::query()
            ->leftJoin('clearance_status as cs', function ($j) {
                $j->on('cs.student_id', '=', 'student_submissions.student_id')
                    ->on('cs.subject_id', '=', 'student_submissions.subject_id')
                    ->on('cs.instructor_id', '=', 'student_submissions.instructor_id');
            })
            ->where('student_submissions.instructor_id', $instructorId)
            ->selectRaw("COUNT(*) as total,
                SUM(COALESCE(cs.status,'Pending')='Approved') as approved,
                0 as rejected,
                SUM(COALESCE(cs.status,'Pending')<>'Approved') as pending")
            ->first();

        return view('instructor.instructor.view_submissions', compact(
            'students', 'subjects', 'submissions', 'allRemarks', 'stats',
            'fStudent', 'fSubject', 'fStatus'
        ));
    }

    // POST /instructor/submissions/clearance  (ajax_set_clearance)
    public function setClearance(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Approved,Pending'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $instructor = Auth::guard('instructor')->user();
        $instructorId = $instructor->instructor_id ?? Auth::guard('instructor')->id();
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($instructor)->authorize('reviewSubject', [$student, (int) $data['subject_id']]);

        $previousStatus = ClearanceStatus::where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('instructor_id', $instructorId)
            ->value('status');

        ClearanceStatus::upsertStatus($data['student_id'], $data['subject_id'], $instructorId, [
            'status' => $data['status'], 'remarks' => $data['remarks'] ?? null,
        ]);

        if (! empty($data['remarks'])) {
            InstructorRemark::create([
                'student_id' => $data['student_id'],
                'subject_id' => $data['subject_id'],
                'instructor_id' => $instructorId,
                'remark' => $data['remarks'],
            ]);
        }

        if ($data['status'] === 'Approved' && $previousStatus !== 'Approved') {
            Notification::create([
                'user_id' => $data['student_id'],
                'recipient_role' => 'student',
                'message' => 'Your instructor approved your clearance for a subject.',
                'notif_type' => 'clearance',
                'link_url' => route('student.clearance-updates'),
            ]);
        }

        return response()->json(['success' => true, 'message' => "Clearance {$data['status']}."]);
    }

    // POST /instructor/remarks/{remark}  (ajax_edit_remark)
    public function editRemark(Request $request, InstructorRemark $remark)
    {
        $instructor = Auth::guard('instructor')->user();
        abort_unless(hash_equals((string) $remark->instructor_id, (string) $instructor->instructor_id), 403);

        $student = StudentAccount::where('student_id', $remark->student_id)->firstOrFail();
        Gate::forUser($instructor)->authorize('reviewSubject', [$student, (int) $remark->subject_id]);

        $data = $request->validate(['remark' => ['required', 'string', 'max:2000']]);
        $remark->update(['remark' => $data['remark']]);

        ClearanceStatus::upsertStatus($remark->student_id, $remark->subject_id, $remark->instructor_id, [
            'remarks' => $data['remark'],
        ]);

        return response()->json(['success' => true, 'message' => 'Remark updated!']);
    }

    // GET /instructor/remarks  (was get_remarks.php)
    public function remarksHistory(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);
        $instructor = Auth::guard('instructor')->user();
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($instructor)->authorize('reviewSubject', [$student, (int) $data['subject_id']]);

        $rows = InstructorRemark::where('instructor_id', $instructor->instructor_id)
            ->where('student_id', $data['student_id'])
            ->where('subject_id', (int) $data['subject_id'])
            ->latest('created_at')->limit(20)->get()
            ->map(fn ($r) => [
                'remark' => e($r->remark),
                'created_at' => $r->created_at->format('M d, Y g:i A'),
            ]);

        return response()->json($rows);
    }

    // GET /instructor/submissions/{submission}/download  (was serve_file.php)
    public function download(StudentSubmission $submission, Request $request)
    {
        $instructor = Auth::guard('instructor')->user();
        Gate::forUser($instructor)->authorize('view', $submission);

        return SubmissionFileResponse::make($submission, $request);
    }
}
