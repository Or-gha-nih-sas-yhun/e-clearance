<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Concerns\ReportsClearanceRefusals;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Support\ClearanceAccess;
use App\Support\ClearanceWorkflow;
use App\Support\SubmissionFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    use ReportsClearanceRefusals;

    /**
     * Clearance records that belong to "treasury" are stored in
     * office_clearance_status with office_role = 'Treasurer'.
     * Adjust this constant if your system uses a different label
     * (e.g. 'Treasury', 'Finance Office').
     */
    protected const OFFICE_ROLE = 'treasurer';

    private function getOfficeRole($treasurer): string
    {
        $type = strtolower(trim($treasurer->treasurer_type ?? ''));

        if ($type === 'section') {
            return 'section treasurer';
        }

        if ($type === 'department') {
            return 'department treasurer';
        }

        return self::OFFICE_ROLE;
    }

    public function index()
    {
        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);

        $baseQuery = function () use ($treasurer, $officeRole) {
            $query = DB::table('office_clearance_status')
                ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
                ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeRole]);

            $this->access()->scopeTreasurerStudents($query, $treasurer);

            return $query;
        };

        $pendingCount = (clone $baseQuery())->where('office_clearance_status.status', '<>', 'Approved')->count();
        $approvedCount = (clone $baseQuery())->where('office_clearance_status.status', 'Approved')->count();

        $clearanceList = (clone $baseQuery())
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

        return view('treasurer.dashboard', compact(
            'treasurer',
            'pendingCount',
            'approvedCount',
            'clearanceList'
        ));
    }

    public function clearanceUpdates(?Request $request = null)
    {
        $request ??= request();
        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);

        $query = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeRole]);

        $this->access()->scopeTreasurerStudents($query, $treasurer);

        $pendingCount = (clone $query)->where('office_clearance_status.status', '<>', 'Approved')->count();
        $approvedCount = (clone $query)->where('office_clearance_status.status', 'Approved')->count();
        $totalStudents = (clone $query)->distinct()->count('student_account.student_id');
        $filterPrograms = (clone $query)->whereNotNull('student_account.program')->distinct()->orderBy('student_account.program')->pluck('student_account.program');
        $filterYears = (clone $query)->whereNotNull('student_account.year_level')->distinct()->orderBy('student_account.year_level')->pluck('student_account.year_level');
        $filterSections = (clone $query)->whereNotNull('student_account.section')->distinct()->orderBy('student_account.section')->pluck('student_account.section');
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['Pending', 'Approved'], true) ? $request->query('status') : '';
        $sort = $request->query('sort') === 'asc' ? 'asc' : 'desc';

        $officeClearances = $query
            ->when($search, fn ($builder) => $builder->where(function ($nested) use ($search) {
                $nested->where('student_account.student_id', 'like', "%{$search}%")
                    ->orWhere('student_account.firstname', 'like', "%{$search}%")
                    ->orWhere('student_account.lastname', 'like', "%{$search}%");
            }))
            ->when($request->query('program'), fn ($builder, $program) => $builder->where('student_account.program', $program))
            ->when($request->query('year_level'), fn ($builder, $year) => $builder->where('student_account.year_level', $year))
            ->when($request->query('section'), fn ($builder, $section) => $builder->where('student_account.section', $section))
            ->when($status === 'Approved', fn ($builder) => $builder->where('office_clearance_status.status', 'Approved'))
            ->when($status === 'Pending', fn ($builder) => $builder->where('office_clearance_status.status', '<>', 'Approved'))
            ->select(
                'office_clearance_status.id',
                'office_clearance_status.status',
                'office_clearance_status.remarks',
                'office_clearance_status.updated_at',
                'office_clearance_status.office_role',
                'student_account.student_id',
                'student_account.firstname',
                'student_account.lastname',
                'student_account.program',
                'student_account.year_level',
                'student_account.section'
            )
            ->orderBy('office_clearance_status.updated_at', $sort)
            ->paginate(15)->withQueryString();

        return view('treasurer.clearance-updates', compact('treasurer', 'officeClearances', 'pendingCount', 'approvedCount', 'totalStudents', 'filterPrograms', 'filterYears', 'filterSections'));
    }

    public function submissionRemark()
    {
        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);

        $submissionQuery = DB::table('office_submissions')
            ->join('student_account', 'office_submissions.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_submissions.office)) = ?', [$officeRole]);

        $this->scopeSubmissionsForTreasurer($submissionQuery, $treasurer);

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
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeRole]);
        $this->access()->scopeTreasurerStudents($remarksQuery, $treasurer);

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

        return view('treasurer.submission-remark', compact('treasurer', 'submissions', 'remarks'));
    }

    public function viewSubmissionFile(int $submission, Request $request)
    {
        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);
        $query = DB::table('office_submissions')
            ->join('student_account', 'office_submissions.student_id', '=', 'student_account.student_id')
            ->where('office_submissions.id', $submission)
            ->whereRaw('LOWER(TRIM(office_submissions.office)) = ?', [$officeRole]);

        $this->scopeSubmissionsForTreasurer($query, $treasurer);

        $record = $query->select(
            'office_submissions.file_path',
            'office_submissions.file_name',
            'office_submissions.file_type'
        )->first();

        abort_unless($record, 404);

        return SubmissionFileResponse::make($record, $request);
    }

    public function setClearanceStatus(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:Approved,Pending'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'submission_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($treasurer)->authorize('reviewTreasury', $student);

        if ($data['status'] === 'Approved' && ! ClearanceWorkflow::prerequisitesMet($student, $officeRole)) {
            return $this->refuseClearanceChange(
                $request,
                'This clearance cannot be approved until its required earlier clearances are complete.',
            );
        }

        DB::transaction(function () use ($data, $treasurer, $officeRole): void {
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
                    'approver_id' => $treasurer->treasurer_id ?? null,
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
                    'message' => 'Your '.ucwords($officeRole).' clearance was approved.',
                    'notif_type' => 'clearance',
                    'link_url' => route('student.clearance-updates'),
                ]);
            }
        });

        $treasurerLabel = ucwords($officeRole);
        $message = $data['status'] === 'Approved'
            ? "{$treasurerLabel} clearance approved successfully."
            : "{$treasurerLabel} clearance returned to pending.";
        $title = $data['status'] === 'Approved'
            ? 'Clearance Approved'
            : 'Clearance Set to Pending';

        return back()->with('flash', ['type' => 'success', 'title' => $title, 'message' => $message]);
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

        $treasurer = Auth::guard('treasurer')->user();
        $officeRole = $this->getOfficeRole($treasurer);
        $query = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw('LOWER(TRIM(office_clearance_status.office_role)) = ?', [$officeRole])
            ->whereIn('office_clearance_status.student_id', $data['student_ids']);
        $this->scopeSubmissionsForTreasurer($query, $treasurer);
        $records = $query->get(['office_clearance_status.student_id', 'office_clearance_status.status']);

        if ($records->count() !== count($data['student_ids'])) {
            $message = 'One or more selected clearance records are outside your treasury scope.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                : back()->withErrors(['student_ids' => $message]);
        }

        if ($data['status'] === 'Approved') {
            $blocked = StudentAccount::whereIn('student_id', $records->pluck('student_id'))
                ->get()
                ->first(fn (StudentAccount $student) => ! ClearanceWorkflow::prerequisitesMet($student, $officeRole));

            if ($blocked) {
                $message = "Student {$blocked->student_id} has incomplete prerequisite clearances.";

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                    : back()->withErrors(['student_ids' => $message]);
            }
        }

        DB::transaction(function () use ($records, $data, $treasurer, $officeRole) {
            foreach ($records as $record) {
                DB::table('office_clearance_status')
                    ->where('student_id', $record->student_id)
                    ->whereRaw('LOWER(TRIM(office_role)) = ?', [$officeRole])
                    ->update([
                        'status' => $data['status'],
                        'approver_id' => $treasurer->treasurer_id ?? null,
                        'updated_at' => now(),
                    ]);

                if ($data['status'] === 'Approved' && $record->status !== 'Approved') {
                    Notification::create([
                        'user_id' => $record->student_id,
                        'recipient_role' => 'student',
                        'message' => 'Your '.ucwords($officeRole).' clearance was approved.',
                        'notif_type' => 'clearance',
                        'link_url' => route('student.clearance-updates'),
                    ]);
                }
            }
        });

        $updated = $records->count();
        $label = ucwords($officeRole);
        $message = $data['status'] === 'Approved'
            ? "{$updated} {$label} clearance records approved successfully."
            : "{$updated} {$label} clearance records returned to pending.";
        $title = $data['status'] === 'Approved'
            ? 'Clearances Approved'
            : 'Clearances Set to Pending';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'updated' => $updated, 'title' => $title, 'message' => $message])
            : back()->with('flash', ['type' => 'success', 'title' => $title, 'message' => $message]);
    }

    private function scopeSubmissionsForTreasurer($query, object $treasurer): void
    {
        $this->access()->scopeTreasurerStudents($query, $treasurer);
    }

    private function access(): ClearanceAccess
    {
        return app(ClearanceAccess::class);
    }
}
