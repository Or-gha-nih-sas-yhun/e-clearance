<?php

namespace App\Http\Controllers\Instructors;

use App\Support\SectionKey;
use App\Support\StudentSubjects;

use App\Http\Controllers\Controller;
use App\Models\ClearanceStatus;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\InstructorRemark;
use App\Models\Notification;
use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use App\Support\InstructorDepartment;
use App\Support\PersonName;
use App\Support\SecureUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    // GET /instructor/dashboard
    public function index(Request $request)
    {
        $data = $this->buildClearanceContext($request);

        return view('instructor.instructor.dashboard', $data);
    }

    // GET /instructor/clearance
    public function clearance(Request $request)
    {
        $data = $this->buildClearanceContext($request);

        return view('instructor.instructor.clearance', $data);
    }

    private function buildClearanceContext(Request $request): array
    {
        $instructor = Auth::guard('instructor')->user();
        $instructorId = $instructor->instructor_id;

        // Every option list is derived from the students this instructor
        // actually clears, so an irregular student's program, year or section
        // appears in the filters even when it is outside the taught block.
        $optPrograms = $this->clearedStudents($instructorId)
            ->distinct()->orderBy('sa.program')->pluck('sa.program');

        $optYears = $this->clearedStudents($instructorId)
            ->distinct()->orderBy('sa.year_level')->pluck('sa.year_level');

        $optSections = $this->clearedStudents($instructorId)
            ->distinct()->orderBy('sa.section')->pluck('sa.section');

        $fProgram = $request->query('program', '');
        $fYear = $request->query('year_level', '');
        $fSection = $request->query('section', '');
        $fStatus = $request->query('status', '');
        $fSearch = $request->query('search', '');
        $fSort = $request->query('sort', 'desc') === 'asc' ? 'asc' : 'desc';

        $students = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->join('student_account as sa', 'sa.student_id', '=', 'ia.student_id')
            ->join('subject_codes as sc', 'sc.subject_id', '=', 'ia.subject_id')
            ->leftJoin('clearance_status as cs', function ($j) {
                $j->on('cs.student_id', '=', 'sa.student_id')
                    ->on('cs.subject_id', '=', 'sc.subject_id')
                    ->on('cs.instructor_id', '=', 'ia.instructor_id');
            })
            ->where('ia.instructor_id', $instructorId)
            ->when($fProgram, fn ($q) => $q->where('sa.program', $fProgram))
            ->when($fYear, fn ($q) => $q->where('sa.year_level', $fYear))
            ->when($fSection, fn ($q) => $q->whereRaw(SectionKey::sql('sa.section').' = '.SectionKey::sql('?'), [$fSection]))
            ->when($fStatus === 'Approved', fn ($q) => $q->where('cs.status', 'Approved'))
            ->when($fStatus === 'Pending', fn ($q) => $q->whereRaw("COALESCE(cs.status,'Pending') <> 'Approved'"))
            ->when($fSearch, function ($q) use ($fSearch) {
                $q->where(function ($w) use ($fSearch) {
                    $w->where('sa.firstname', 'like', "%{$fSearch}%")
                        ->orWhere('sa.lastname', 'like', "%{$fSearch}%")
                        ->orWhere('sa.student_id', 'like', "%{$fSearch}%");
                });
            })
            ->select([
                'sa.student_id', 'sa.firstname', 'sa.lastname', 'sa.program', 'sa.year_level',
                'sa.section', 'sa.student_type', 'sc.subject_id', 'sc.subject_code',
                'sc.subject_description', 'sc.semester', 'sa.section as assigned_section',
                DB::raw("CASE WHEN cs.status='Approved' THEN 'Approved' ELSE 'Pending' END as clearance_status"),
                'cs.remarks', 'cs.updated_at as cleared_at',
            ])
            ->orderBy('sa.lastname', $fSort)->orderBy('sa.firstname', $fSort)
            ->paginate(25)->withQueryString()
            ->through(function ($row) use ($instructorId) {
                if (empty($row->remarks)) {
                    $row->remarks = DB::table('instructor_remarks')
                        ->where('student_id', $row->student_id)
                        ->where('subject_id', $row->subject_id)
                        ->where('instructor_id', $instructorId)
                        ->latest('created_at')
                        ->value('remark');
                }

                return $row;
            });

        $subjects = InstructorAssignment::with('subject')
            ->select('instructor_assignment.*')
            ->selectSub(function ($query) {
                $query->fromSub(StudentSubjects::pairs(), 'assignment_students')
                    ->selectRaw('COUNT(DISTINCT assignment_students.student_id)')
                    ->whereColumn('assignment_students.subject_id', 'instructor_assignment.subject_id')
                    ->whereColumn('assignment_students.instructor_id', 'instructor_assignment.instructor_id');
            }, 'student_count')
            ->where('instructor_id', $instructorId)
            ->orderBy('program')
            ->orderBy('year_level')
            ->orderBy('section')
            ->get();

        $stats = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'ia')
            ->join('student_account as sa', 'sa.student_id', '=', 'ia.student_id')
            ->leftJoin('clearance_status as cs', function ($j) {
                $j->on('cs.student_id', '=', 'sa.student_id')
                    ->on('cs.subject_id', '=', 'ia.subject_id')
                    ->on('cs.instructor_id', '=', 'ia.instructor_id');
            })
            ->where('ia.instructor_id', $instructorId)
            ->selectRaw("COUNT(DISTINCT sa.student_id) as total_students,
                COALESCE(SUM(CASE WHEN COALESCE(cs.status,'Pending')='Approved' THEN 1 ELSE 0 END), 0) as approved,
                COALESCE(SUM(CASE WHEN COALESCE(cs.status,'Pending')<>'Approved' THEN 1 ELSE 0 END), 0) as pending")
            ->first();

        $totalSubmissions = StudentSubmission::where('instructor_id', $instructorId)->count();

        return compact(
            'instructor', 'optPrograms', 'optYears', 'optSections',
            'students', 'subjects', 'stats', 'totalSubmissions',
            'fProgram', 'fYear', 'fSection', 'fStatus', 'fSearch', 'fSort'
        );
    }

    /** The students this instructor clears, regular block and irregular picks alike. */
    private function clearedStudents(string $instructorId)
    {
        return DB::query()
            ->fromSub(StudentSubjects::pairs(), 'sp')
            ->join('student_account as sa', 'sa.student_id', '=', 'sp.student_id')
            ->where('sp.instructor_id', $instructorId);
    }

    // POST /instructor/remarks  (ajax_send_remark)
    public function sendRemark(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:50'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        $instructor = Auth::guard('instructor')->user();
        $instructorId = $instructor->instructor_id;
        $student = StudentAccount::where('student_id', $data['student_id'])->firstOrFail();

        Gate::forUser($instructor)->authorize('reviewSubject', [$student, (int) $data['subject_id']]);

        InstructorRemark::create(array_merge($data, ['instructor_id' => $instructorId]));

        ClearanceStatus::upsertStatus($data['student_id'], $data['subject_id'], $instructorId, [
            'remarks' => $data['remark'],
        ]);

        Notification::create([
            'user_id' => $data['student_id'],
            'recipient_role' => 'student',
            'message' => 'Your instructor left a remark on a subject. Please check your submissions page.',
            'notif_type' => 'clearance',
            'link_url' => route('student.submission-remark'),
        ]);

        return response()->json(['success' => true, 'message' => 'Remark sent!']);
    }

    // DELETE /instructor/submissions/{submission}  (ajax_delete_submission)
    public function deleteSubmission(StudentSubmission $submission)
    {
        $instructor = Auth::guard('instructor')->user();
        Gate::forUser($instructor)->authorize('delete', $submission);

        $filePath = $submission->getAttribute('file_path');

        SecureUpload::delete($filePath);
        $submission->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    // GET /instructor/notifications  (ajax_get_notifications)
    public function notifications()
    {
        $instructorId = Auth::guard('instructor')->user()->instructor_id;

        $rows = Notification::where('user_id', $instructorId)->where('recipient_role', 'instructor')
            ->latest('created_at')->limit(20)->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->message,
                'is_read' => (int) $n->is_read,
                'created_at' => $n->created_at->format('M d, Y g:i A'),
            ]);

        $unread = Notification::where('user_id', $instructorId)->where('recipient_role', 'instructor')->where('is_read', 0)->count();

        return response()->json(['notifications' => $rows, 'unread' => $unread]);
    }

    // POST /instructor/notifications/read-all  (ajax_mark_notif_read)
    public function markNotificationsRead()
    {
        Notification::where('user_id', Auth::guard('instructor')->user()->instructor_id)
            ->where('recipient_role', 'instructor')->update(['is_read' => 1]);

        return response()->json(['success' => true]);
    }

    // PUT /instructor/account  (ajax_update_account)
    public function updateAccount(Request $request)
    {
        /** @var Instructor $instructor */
        $instructor = Auth::guard('instructor')->user();

        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'lastname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'suffix' => 'nullable|string|max:10',
            'email' => ['required', 'email', Rule::unique('instructor_account', 'email')->ignore($instructor->getAttribute('instructor_id'), 'instructor_id')],
            'department' => ['required', Rule::in(InstructorDepartment::accepted())],
            'current_password' => 'nullable|string',
            'new_password' => ['nullable', Password::min(8)->mixedCase()->numbers()->symbols()],
            'confirm_password' => 'nullable|same:new_password',
        ], PersonName::messages('firstname', 'middlename', 'lastname'));

        $emailChanged = ! hash_equals(
            strtolower((string) $instructor->getAttribute('email')),
            strtolower($data['email']),
        );
        if (($emailChanged || ! empty($data['new_password']))
            && ! Hash::check($data['current_password'] ?? '', $instructor->getAttribute('password'))) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is required to change the email address or password.',
            ]);
        }

        if (! empty($data['new_password'])) {
            $instructor->password = Hash::make($data['new_password']);
        }

        $instructor->fill([
            'firstname' => $data['firstname'], 'middlename' => $data['middlename'] ?? null,
            'lastname' => $data['lastname'],  'suffix' => $data['suffix'] ?? null,
            'email' => $data['email'],     'department' => InstructorDepartment::canonical($data['department']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully!',
            'new_name' => $instructor->getAttribute('full_name'),
            'new_dept' => $instructor->getAttribute('department'),
            'new_init' => strtoupper(substr($instructor->getAttribute('firstname'), 0, 1)),
        ]);
    }
}
