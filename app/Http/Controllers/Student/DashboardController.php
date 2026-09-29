<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\StudentSubjects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $clearanceRequest = DB::table('clearance_request')
            ->where('student_id', $student->student_id)
            ->orderByDesc('requested_at')
            ->first();

        $subjectClearances = DB::query()
            ->fromSub(StudentSubjects::pairs(), 'sp')
            ->join('clearance_status as cs', function ($join) {
                $join->on('cs.student_id', '=', 'sp.student_id')
                    ->on('cs.subject_id', '=', 'sp.subject_id')
                    ->on('cs.instructor_id', '=', 'sp.instructor_id');
            })
            ->leftJoin('subject_codes', 'sp.subject_id', '=', 'subject_codes.subject_id')
            ->leftJoin('instructor_account', 'sp.instructor_id', '=', 'instructor_account.instructor_id')
            ->where('sp.student_id', $student->student_id)
            ->select(
                'cs.status',
                'cs.remarks',
                'cs.updated_at',
                'subject_codes.subject_code',
                'subject_codes.subject_description',
                'instructor_account.firstname as instructor_firstname',
                'instructor_account.lastname as instructor_lastname'
            )
            ->orderBy('subject_codes.subject_code')
            ->get();

        $officeClearances = DB::table('office_clearance_status')
            ->where('student_id', $student->student_id)
            ->orderBy('office_role')
            ->get();

        $subjectsTotal = $subjectClearances->count();
        $subjectsApproved = $subjectClearances->where('status', 'Approved')->count();

        $officesTotal = $officeClearances->count();
        $officesApproved = $officeClearances->where('status', 'Approved')->count();

        $clearanceItems = $subjectClearances->map(function ($clearance) {
            $instructor = trim(($clearance->instructor_firstname ?? '').' '.($clearance->instructor_lastname ?? ''));

            return (object) [
                'type' => 'Subject',
                'label' => trim(($clearance->subject_code ?? 'Subject').' — '.($clearance->subject_description ?? '')),
                'owner' => $instructor ?: 'Instructor',
                'status' => $clearance->status ?? 'Pending',
                'remarks' => $clearance->remarks,
                'updated_at' => $clearance->updated_at,
            ];
        })->concat($officeClearances->map(function ($clearance) {
            return (object) [
                'type' => 'Office',
                'label' => ucwords(str_replace(['_', '-'], ' ', $clearance->office_role ?? 'Office clearance')),
                'owner' => 'Office review',
                'status' => $clearance->status ?? 'Pending',
                'remarks' => $clearance->remarks ?? null,
                'updated_at' => $clearance->updated_at ?? null,
            ];
        }));

        $statusGroup = static function ($status): string {
            $status = strtolower(trim((string) $status));

            if (in_array($status, ['approved', 'cleared', 'complete'], true)) {
                return 'approved';
            }

            if (in_array($status, ['rejected', 'disapproved', 'returned', 'needs revision', 'for revision'], true)) {
                return 'action';
            }

            return 'pending';
        };

        $statusBreakdown = [
            'approved' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'approved')->count(),
            'pending' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'pending')->count(),
            'action' => $clearanceItems->filter(fn ($item) => $statusGroup($item->status) === 'action')->count(),
        ];
        $totalClearances = $clearanceItems->count();
        $overallProgress = $totalClearances ? (int) round(($statusBreakdown['approved'] / $totalClearances) * 100) : 0;
        $actionItems = $clearanceItems
            ->filter(fn ($item) => $statusGroup($item->status) !== 'approved')
            ->sortByDesc('updated_at')
            ->values();
        $recentActivity = $clearanceItems
            ->filter(fn ($item) => ! empty($item->updated_at))
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        return view('student.dashboard', compact(
            'student',
            'clearanceRequest',
            'subjectClearances',
            'officeClearances',
            'subjectsTotal',
            'subjectsApproved',
            'officesTotal',
            'officesApproved',
            'clearanceItems',
            'statusBreakdown',
            'totalClearances',
            'overallProgress',
            'actionItems',
            'recentActivity'
        ));
    }
}
