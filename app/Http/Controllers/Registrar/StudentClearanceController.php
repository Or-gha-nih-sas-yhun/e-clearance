<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Concerns\ReportsClearanceRefusals;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class StudentClearanceController extends Controller
{
    use ReportsClearanceRefusals;

    public function index(Request $request)
    {
        $registrar = Auth::guard('registrar')->user();

        // Inner join: a clearance row whose student was deleted has no name and no
        // student_id to build links from, and would break every route() in the view.
        $baseQuery = DB::table('office_clearance_status')
            ->join('student_account', 'office_clearance_status.student_id', '=', 'student_account.student_id')
            ->whereRaw("LOWER(TRIM(office_clearance_status.office_role)) = 'registrar'");

        $pendingCount = (clone $baseQuery)->where('office_clearance_status.status', '<>', 'Approved')->count();
        $approvedCount = (clone $baseQuery)->where('office_clearance_status.status', 'Approved')->count();
        $totalStudents = (clone $baseQuery)->distinct()->count('student_account.student_id');
        $filterPrograms = (clone $baseQuery)->whereNotNull('student_account.program')->distinct()->orderBy('student_account.program')->pluck('student_account.program');
        $filterYears = (clone $baseQuery)->whereNotNull('student_account.year_level')->distinct()->orderBy('student_account.year_level')->pluck('student_account.year_level');
        $filterSections = (clone $baseQuery)->whereNotNull('student_account.section')->distinct()->orderBy('student_account.section')->pluck('student_account.section');
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['Pending', 'Approved'], true) ? $request->query('status') : '';
        $sort = $request->query('sort') === 'asc' ? 'asc' : 'desc';

        $clearanceRequests = $baseQuery
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
                'office_clearance_status.status',
                'office_clearance_status.updated_at as requested_at',
                'student_account.student_id',
                'student_account.firstname',
                'student_account.lastname',
                'student_account.program',
                'student_account.year_level',
                'student_account.section'
            )
            ->orderBy('office_clearance_status.updated_at', $sort)
            ->paginate(15)->withQueryString();

        return view('registrar.student-clearance', compact('registrar', 'clearanceRequests', 'pendingCount', 'approvedCount', 'totalStudents', 'filterPrograms', 'filterYears', 'filterSections'));
    }

    public function scanner()
    {
        $registrar = Auth::guard('registrar')->user();

        return view('registrar.qr-scanner', compact('registrar'));
    }

    public function setClearanceStatus(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:Approved,Pending'],
        ]);

        $registrar = Auth::guard('registrar')->user();
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($registrar)->authorize('reviewRegistrar', $student);

        if ($data['status'] === 'Approved' && ! ClearanceWorkflow::prerequisitesMet($student, 'registrar')) {
            return $this->refuseClearanceChange(
                $request,
                'Registrar approval requires every earlier clearance to be approved.',
            );
        }

        DB::transaction(function () use ($data, $registrar): void {
            $clearance = DB::table('office_clearance_status')
                ->where('student_id', $data['student_id'])
                ->whereRaw("LOWER(TRIM(office_role)) = 'registrar'")
                ->lockForUpdate()
                ->first();

            abort_unless($clearance, 404);

            DB::table('office_clearance_status')
                ->where('id', $clearance->id)
                ->update([
                    'status' => $data['status'],
                    'approver_id' => $registrar->registrar_id ?? null,
                    'updated_at' => now(),
                ]);

            if ($data['status'] === 'Approved' && $clearance->status !== 'Approved') {
                Notification::create([
                    'user_id' => $data['student_id'],
                    'recipient_role' => 'student',
                    'message' => 'Your Registrar clearance was approved.',
                    'notif_type' => 'clearance',
                    'link_url' => route('student.clearance.form'),
                ]);
            }
        });

        $message = $data['status'] === 'Approved'
            ? 'Registrar clearance approved successfully.'
            : 'Registrar clearance returned to pending.';
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

        $registrar = Auth::guard('registrar')->user();
        $records = DB::table('office_clearance_status')
            ->whereRaw("LOWER(TRIM(office_role)) = 'registrar'")
            ->whereIn('student_id', $data['student_ids'])
            ->get(['student_id', 'status']);

        if ($records->count() !== count($data['student_ids'])) {
            $message = 'One or more selected registrar clearance records are unavailable.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                : back()->withErrors(['student_ids' => $message]);
        }

        if ($data['status'] === 'Approved') {
            $blocked = StudentAccount::whereIn('student_id', $records->pluck('student_id'))
                ->get()
                ->first(fn (StudentAccount $student) => ! ClearanceWorkflow::prerequisitesMet($student, 'registrar'));

            if ($blocked) {
                $message = "Student {$blocked->student_id} has incomplete prerequisite clearances.";

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message, 'errors' => ['student_ids' => [$message]]], 422)
                    : back()->withErrors(['student_ids' => $message]);
            }
        }

        DB::transaction(function () use ($records, $data, $registrar) {
            foreach ($records as $record) {
                DB::table('office_clearance_status')
                    ->where('student_id', $record->student_id)
                    ->whereRaw("LOWER(TRIM(office_role)) = 'registrar'")
                    ->update([
                        'status' => $data['status'],
                        'approver_id' => $registrar->registrar_id ?? null,
                        'updated_at' => now(),
                    ]);

                if ($data['status'] === 'Approved' && $record->status !== 'Approved') {
                    Notification::create([
                        'user_id' => $record->student_id,
                        'recipient_role' => 'student',
                        'message' => 'Your Registrar clearance was approved.',
                        'notif_type' => 'clearance',
                        'link_url' => route('student.clearance.form'),
                    ]);
                }
            }
        });

        $updated = $records->count();
        $message = $data['status'] === 'Approved'
            ? "{$updated} registrar clearance records approved successfully."
            : "{$updated} registrar clearance records returned to pending.";
        $title = $data['status'] === 'Approved'
            ? 'Clearances Approved'
            : 'Clearances Set to Pending';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'updated' => $updated, 'title' => $title, 'message' => $message])
            : back()->with('flash', ['type' => 'success', 'title' => $title, 'message' => $message]);
    }
}
