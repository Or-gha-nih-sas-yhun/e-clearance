<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Concerns\ReportsClearanceRefusals;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Support\ClearanceAccess;
use App\Support\ClearanceWorkflow;
use App\Support\LibraryEvaluation;
use App\Support\SubmissionFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    use ReportsClearanceRefusals;

    public function index()
    {
        $office = Auth::guard('office')->user();
        $officeName = $this->getOfficeRole($office);

        $clearanceQuery = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeName]);
        $this->access()->scopeOfficeStudents($clearanceQuery, $office);

        $pendingCount = (clone $clearanceQuery)
            ->where('office_clearance_status.status', '<>', 'Approved')->count();
        $approvedCount = (clone $clearanceQuery)
            ->where('office_clearance_status.status', 'Approved')->count();
        $clearanceList = (clone $clearanceQuery)
            ->select(
                'office_clearance_status.id',
                'office_clearance_status.status',
                'office_clearance_status.remarks',
                'office_clearance_status.updated_at',
                'student_account.student_id',
                'student_account.firstname',
                'student_account.lastname',
                'student_account.program',
                'student_account.year_level',
                'student_account.section'
            )
            ->orderByDesc('office_clearance_status.updated_at')
            ->limit(15)
            ->get();

        $submissionQuery = DB::table('office_submissions')
            ->join('student_account', 'office_submissions.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_submissions.office)) = ?', [$officeName]);
        $this->access()->scopeOfficeStudents($submissionQuery, $office);

        $pendingSubmissions = (clone $submissionQuery)
            ->where('office_submissions.status', 'Pending')->count();

        $recentSubmissions = (clone $submissionQuery)
            ->select(
                'office_submissions.id',
                'office_submissions.file_name',
                'office_submissions.remarks',
                'office_submissions.description',
                'office_submissions.status',
                'office_submissions.submitted_at',
                'student_account.student_id',
                'student_account.firstname',
                'student_account.lastname'
            )
            ->orderByDesc('office_submissions.submitted_at')
            ->limit(10)
            ->get();

        $requirements = DB::table('office_requirements')
            ->whereRaw('LOWER(TRIM(office_role)) = ?', [$officeName])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('office.dashboard', compact(
            'office',
            'officeName',
            'pendingCount',
            'approvedCount',
            'clearanceList',
            'pendingSubmissions',
            'recentSubmissions',
            'requirements'
        ));
    }

    public function submissions()
    {
        $office = Auth::guard('office')->user();
        $officeName = $this->getOfficeRole($office);

        $submissionQuery = DB::table('office_submissions')
            ->join('student_account', 'office_submissions.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_submissions.office)) = ?', [$officeName]);
        $this->access()->scopeOfficeStudents($submissionQuery, $office);

        $submissions = $submissionQuery
            ->select(
                'office_submissions.id',
                'office_submissions.student_id',
                'office_submissions.file_name',
                'office_submissions.remarks',
                'office_submissions.description',
                'office_submissions.status',
                'office_submissions.submitted_at',
                'student_account.firstname',
                'student_account.lastname'
            )
            ->orderByDesc('office_submissions.submitted_at')
            ->get();

        $remarksQuery = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeName]);
        $this->access()->scopeOfficeStudents($remarksQuery, $office);

        $remarks = $remarksQuery
            ->select(
                'office_clearance_status.id',
                'office_clearance_status.student_id',
                'office_clearance_status.status',
                'office_clearance_status.remarks',
                'office_clearance_status.updated_at',
                'student_account.firstname',
                'student_account.lastname',
                'student_account.program',
                'student_account.year_level',
                'student_account.section'
            )
            ->orderByDesc('office_clearance_status.updated_at')
            ->get();

        return view('office.submissions', compact('office', 'officeName', 'submissions', 'remarks'));
    }

    public function viewSubmissionFile(int $submission, Request $request)
    {
        $office = Auth::guard('office')->user();
        $officeRole = $this->getOfficeRole($office);

        $query = DB::table('office_submissions')
            ->join('student_account', 'office_submissions.student_id', '=', 'student_account.student_id')
            ->where('office_submissions.id', $submission)
            ->whereRaw('LOWER(TRIM(office_submissions.office)) = ?', [$officeRole]);
        $this->access()->scopeOfficeStudents($query, $office);

        $record = $query
            ->select('office_submissions.file_path', 'office_submissions.file_name', 'office_submissions.file_type')
            ->first();

        abort_unless($record, 404);

        return SubmissionFileResponse::make($record, $request);
    }

    public function clearanceRequests(?Request $request = null)
    {
        $request ??= request();
        $office = Auth::guard('office')->user();
        $officeName = $this->getOfficeRole($office);

        $baseQuery = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeName]);
        $this->access()->scopeOfficeStudents($baseQuery, $office);

        $pendingCount = (clone $baseQuery)->where('office_clearance_status.status', '<>', 'Approved')->count();
        $approvedCount = (clone $baseQuery)->where('office_clearance_status.status', 'Approved')->count();
        $totalStudents = (clone $baseQuery)->distinct()->count('student_account.student_id');
        $filterPrograms = (clone $baseQuery)->whereNotNull('student_account.program')->distinct()->orderBy('student_account.program')->pluck('student_account.program');
        $filterYears = (clone $baseQuery)->whereNotNull('student_account.year_level')->distinct()->orderBy('student_account.year_level')->pluck('student_account.year_level');
        $filterSections = (clone $baseQuery)->whereNotNull('student_account.section')->distinct()->orderBy('student_account.section')->pluck('student_account.section');

        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['Pending', 'Approved'], true) ? $request->query('status') : '';
        $sort = $request->query('sort') === 'asc' ? 'asc' : 'desc';

        $requests = $baseQuery
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('student_account.student_id', 'like', "%{$search}%")
                    ->orWhere('student_account.firstname', 'like', "%{$search}%")
                    ->orWhere('student_account.lastname', 'like', "%{$search}%");
            }))
            ->when($request->query('program'), fn ($query, $program) => $query->where('student_account.program', $program))
            ->when($request->query('year_level'), fn ($query, $year) => $query->where('student_account.year_level', $year))
            ->when($request->query('section'), fn ($query, $section) => $query->where('student_account.section', $section))
            ->when($status === 'Approved', fn ($query) => $query->where('office_clearance_status.status', 'Approved'))
            ->when($status === 'Pending', fn ($query) => $query->where('office_clearance_status.status', '<>', 'Approved'))
            ->select(
                'office_clearance_status.id',
                'office_clearance_status.student_id',
                'office_clearance_status.office_role',
                'office_clearance_status.status',
                'office_clearance_status.remarks',
                'office_clearance_status.updated_at',
                'student_account.firstname',
                'student_account.lastname',
                'student_account.program',
                'student_account.year_level',
                'student_account.section'
            )
            ->orderBy('office_clearance_status.updated_at', $sort)
            ->paginate(15)->withQueryString();

        $evaluationScope = match ($officeName) {
            'library' => 'library',
            'guidance office' => 'guidance',
            default => null,
        };
        $officeEvaluation = $evaluationScope ? LibraryEvaluation::current($evaluationScope) : null;
        $evaluationCompletions = LibraryEvaluation::completions($officeEvaluation, $requests->pluck('student_id')->all(), $evaluationScope ?? 'library');

        return view('office.clearance-requests', compact('office', 'officeName', 'requests', 'pendingCount', 'approvedCount', 'totalStudents', 'filterPrograms', 'filterYears', 'filterSections', 'officeEvaluation', 'evaluationCompletions'));
    }

    public function setClearanceStatus(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:Approved,Pending'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'submission_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $office = Auth::guard('office')->user();
        $officeRole = $this->getOfficeRole($office);
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($office)->authorize('reviewOffice', $student);

        if ($data['status'] === 'Approved' && ! ClearanceWorkflow::prerequisitesMet($student, $officeRole)) {
            return $this->refuseClearanceChange(
                $request,
                match ($officeRole) {
                    'library' => 'The student must complete the current Library Evaluation before approval.',
                    'guidance office' => 'The student must complete the current Guidance Evaluation before approval.',
                    default => 'This clearance cannot be approved until its required earlier clearances are complete.',
                },
            );
        }

        $officeLabel = ucwords(str_replace('_', ' ', $officeRole));
        DB::transaction(function () use ($data, $office, $officeRole, $officeLabel): void {
            $clearance = DB::table('office_clearance_status')
                ->where('student_id', $data['student_id'])
                ->whereRaw('LOWER(TRIM(office_role)) = ?', [$officeRole])
                ->lockForUpdate()
                ->first();

            abort_unless($clearance, 404);

            $submission = null;
            if (! empty($data['submission_id'])) {
                $submission = DB::table('office_submissions')
                    ->where('id', $data['submission_id'])
                    ->where('student_id', $data['student_id'])
                    ->whereRaw('LOWER(TRIM(office)) = ?', [$officeRole])
                    ->lockForUpdate()
                    ->first();

                abort_unless($submission, 404);
            }

            DB::table('office_clearance_status')
                ->where('id', $clearance->id)
                ->update([
                    'status' => $data['status'],
                    'approver_id' => $office->personnel_id ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'updated_at' => now(),
                ]);

            if ($submission) {
                DB::table('office_submissions')->where('id', $submission->id)->update([
                    'status' => $data['status'] === 'Approved' ? 'Received' : $data['status'],
                    'remarks' => $data['remarks'] ?? null,
                    'reviewed_at' => now(),
                ]);
            }

            if ($data['status'] === 'Approved' && $clearance->status !== 'Approved') {
                Notification::create([
                    'user_id' => $data['student_id'],
                    'recipient_role' => 'student',
                    'message' => "Your {$officeLabel} clearance was approved.",
                    'notif_type' => 'clearance',
                    'link_url' => route('student.clearance-updates'),
                ]);
            }
        });

        $message = $data['status'] === 'Approved'
            ? "{$officeLabel} clearance approved successfully."
            : "{$officeLabel} clearance returned to pending.";

        return back()->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function bulkSetClearanceStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1|max:100',
            'student_ids.*' => 'required|string|max:50|distinct',
            'status' => 'required|string|in:Approved,Pending',
        ]);
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Check the selected clearance records.', 'errors' => $validator->errors()], 422)
                : back()->withErrors($validator);
        }
        $data = $validator->validated();

        $office = Auth::guard('office')->user();
        $officeRole = $this->getOfficeRole($office);
        $recordsQuery = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeRole])
            ->whereIn('office_clearance_status.student_id', $data['student_ids']);
        $this->access()->scopeOfficeStudents($recordsQuery, $office);
        $records = $recordsQuery->get([
            'office_clearance_status.student_id',
            'office_clearance_status.status',
        ]);

        if ($records->count() !== count($data['student_ids'])) {
            $message = 'One or more selected clearance records are not available to this office.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                : back()->withErrors(['student_ids' => $message]);
        }

        if ($data['status'] === 'Approved') {
            $blocked = StudentAccount::whereIn('student_id', $records->pluck('student_id'))
                ->get()
                ->first(fn (StudentAccount $student) => ! ClearanceWorkflow::prerequisitesMet($student, $officeRole));

            if ($blocked) {
                $message = match ($officeRole) {
                    'library' => "Student {$blocked->student_id} must complete the current Library Evaluation before approval.",
                    'guidance office' => "Student {$blocked->student_id} must complete the current Guidance Evaluation before approval.",
                    default => "Student {$blocked->student_id} has incomplete prerequisite clearances.",
                };

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                    : back()->withErrors(['student_ids' => $message]);
            }
        }

        $officeLabel = ucwords(str_replace('_', ' ', $officeRole));
        DB::transaction(function () use ($records, $data, $office, $officeRole, $officeLabel) {
            foreach ($records as $record) {
                DB::table('office_clearance_status')
                    ->where('student_id', $record->student_id)
                    ->whereRaw('LOWER(TRIM(office_role)) = ?', [$officeRole])
                    ->update([
                        'status' => $data['status'],
                        'approver_id' => $office->personnel_id ?? null,
                        'updated_at' => now(),
                    ]);

                if ($data['status'] === 'Approved' && $record->status !== 'Approved') {
                    Notification::create([
                        'user_id' => $record->student_id,
                        'recipient_role' => 'student',
                        'message' => "Your {$officeLabel} clearance was approved.",
                        'notif_type' => 'clearance',
                        'link_url' => route('student.clearance-updates'),
                    ]);
                }
            }
        });

        $updated = $records->count();
        $message = $data['status'] === 'Approved'
            ? "{$updated} {$officeLabel} clearance records approved successfully."
            : "{$updated} {$officeLabel} clearance records returned to pending.";

        return $request->expectsJson()
            ? response()->json(['success' => true, 'updated' => $updated, 'message' => $message])
            : back()->with('flash', ['type' => 'success', 'message' => $message]);
    }

    private function getOfficeRole(?object $office): string
    {
        return $this->access()->officeRole($office);
    }

    private function access(): ClearanceAccess
    {
        return app(ClearanceAccess::class);
    }
}
