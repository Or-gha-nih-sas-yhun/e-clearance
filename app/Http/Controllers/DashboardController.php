<?php

namespace App\Http\Controllers;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\Registrar;
use App\Models\Student;
use App\Models\Treasurer;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // User counts
        $students = Student::count();
        $instructors = Instructor::count();
        $admins = AdminPersonnel::count();
        $registrars = Registrar::count();
        $treasurers = Treasurer::count();

        // System-wide clearance checkpoints. Subject clearances represent the
        // instructor portal; office clearances also contain treasury, registrar,
        // and the remaining institutional offices. The overall clearance_request
        // row is intentionally excluded here to avoid counting one workflow twice.
        $pending = $this->clearanceCheckpoints('clearance_status')->where('c.status', '<>', 'Approved')->count()
            + $this->clearanceCheckpoints('office_clearance_status')->where('c.status', '<>', 'Approved')->count();
        $approved = $this->clearanceCheckpoints('clearance_status')->where('c.status', 'Approved')->count()
            + $this->clearanceCheckpoints('office_clearance_status')->where('c.status', 'Approved')->count();
        $cleared = 0;
        $rejected = 0;

        // Recorded clearance activity across all portals for the last six months.
        $monthlyData = collect(range(5, 0))->map(function ($i) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M Y'),
                'count' => $this->clearanceCheckpoints('clearance_status')->whereBetween('c.updated_at', [$start, $end])->count()
                    + $this->clearanceCheckpoints('office_clearance_status')->whereBetween('c.updated_at', [$start, $end])->count(),
            ];
        });

        // Current pending/approved checkpoint status grouped by latest activity month.
        $stackData = collect(range(5, 0))->map(function ($i) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M'),
                'pending' => $this->clearanceCheckpoints('clearance_status')
                    ->whereBetween('c.updated_at', [$start, $end])->where('c.status', '<>', 'Approved')->count()
                    + $this->clearanceCheckpoints('office_clearance_status')
                        ->whereBetween('c.updated_at', [$start, $end])->where('c.status', '<>', 'Approved')->count(),
                'approved' => $this->clearanceCheckpoints('clearance_status')
                    ->whereBetween('c.updated_at', [$start, $end])->where('c.status', 'Approved')->count()
                    + $this->clearanceCheckpoints('office_clearance_status')
                        ->whereBetween('c.updated_at', [$start, $end])->where('c.status', 'Approved')->count(),
                'cleared' => 0,
                'rejected' => 0,
            ];
        });

        // Pending and approved checkpoints by the student's program, combining
        // both instructor/subject and office-based clearance records.
        $subjectStatusByProgram = DB::table('clearance_status as cs')
            ->join('student_account as sa', 'sa.student_id', '=', 'cs.student_id')
            ->select(
                'sa.program',
                DB::raw("SUM(CASE WHEN cs.status = 'Approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN cs.status <> 'Approved' THEN 1 ELSE 0 END) as pending")
            )
            ->groupBy('sa.program')
            ->get();

        $officeStatusByProgram = DB::table('office_clearance_status as ocs')
            ->join('student_account as sa', 'sa.student_id', '=', 'ocs.student_id')
            ->select(
                'sa.program',
                DB::raw("SUM(CASE WHEN ocs.status = 'Approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN ocs.status <> 'Approved' THEN 1 ELSE 0 END) as pending")
            )
            ->groupBy('sa.program')
            ->get();

        $statusByProgram = $subjectStatusByProgram
            ->concat($officeStatusByProgram)
            ->groupBy(fn ($row) => trim((string) ($row->program ?? '')) ?: 'Unspecified')
            ->map(function ($rows, $program) {
                return [
                    'program' => $program,
                    'pending' => (int) $rows->sum('pending'),
                    'approved' => (int) $rows->sum('approved'),
                ];
            })
            ->sortBy('program')
            ->values();

        // Students by year level
        $byYear = Student::select('year_level', DB::raw('count(*) as c'))
            ->groupBy('year_level')->orderBy('year_level')->get();

        // Subject codes by program
        $bySubjectProg = DB::table('subject_codes')
            ->select(DB::raw("
                CASE
                    WHEN program LIKE '%BSIT%' AND program NOT LIKE '%,%' THEN 'BSIT'
                    WHEN program LIKE '%BSED%' AND program NOT LIKE '%,%' THEN 'BSED'
                    WHEN program LIKE '%BSHM%' AND program NOT LIKE '%,%' THEN 'BSHM'
                    WHEN program LIKE '%BSBA%' AND program NOT LIKE '%,%' THEN 'BSBA'
                    WHEN program LIKE '%BEED%' AND program NOT LIKE '%,%' THEN 'BEED'
                    WHEN program = 'ALL' THEN 'ALL Programs'
                    ELSE 'Multi-Program'
                END AS prog_label, COUNT(*) as c
            "))->groupBy('prog_label')->orderByDesc('c')->get();

        // Notifications read/unread
        $notifRead = DB::table('notifications')->where('is_read', 1)->count();
        $notifUnread = DB::table('notifications')->where('is_read', 0)->count();

        // Approval by role
        $approverMap = DB::table('clearance_approval')
            ->select('approver_role', 'status', DB::raw('count(*) as c'))
            ->groupBy('approver_role', 'status')
            ->get()
            ->groupBy('approver_role');

        // Assignments per instructor
        $instrAssign = InstructorAssignment::with('instructor')
            ->select('instructor_id', DB::raw('count(*) as c'))
            ->groupBy('instructor_id')->orderByDesc('c')->get();

        return view('mainAdmin.dashboard', compact(
            'students', 'instructors', 'admins', 'registrars', 'treasurers',
            'pending', 'approved', 'cleared', 'rejected',
            'monthlyData', 'stackData', 'statusByProgram', 'byYear',
            'bySubjectProg', 'notifRead', 'notifUnread', 'approverMap', 'instrAssign'
        ));
    }

    /**
     * Clearance rows that still belong to a student who exists.
     *
     * Every table is MyISAM, so the `ON DELETE CASCADE` the migrations declare
     * has never once fired — deleting a student can leave their clearance rows
     * behind. Counting those made the dashboard tiles disagree with the
     * by-program chart directly beneath them (22 approved against 11), because
     * only the chart joined `student_account`. Joining here means a leftover row
     * can never inflate a total again, however it came to exist.
     */
    private function clearanceCheckpoints(string $table)
    {
        return DB::table($table.' as c')
            ->join('student_account as sa', 'sa.student_id', '=', 'c.student_id');
    }
}
