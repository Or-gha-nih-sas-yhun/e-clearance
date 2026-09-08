<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\RecordPurge;
use App\Support\StudentSubjects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An irregular student's own subject list.
 *
 * A regular student's subjects come from their section's block and nobody
 * chooses them. An irregular student is not on that block, so they declare each
 * subject here together with which of that subject's instructors will clear
 * them. Those rows are `irregular_enrollment`, and from that point on every
 * other screen — the student's clearance page, the printed form, the
 * instructor's own listings and the chat directory — reads them through
 * {@see StudentSubjects} exactly as it reads a regular assignment.
 *
 * The instructors offered for a subject are only those the Main Admin has
 * actually assigned to teach it, so a student can never route their clearance
 * to someone who does not teach the subject.
 */
class SubjectEnrollmentController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        if (! StudentSubjects::isIrregular($student)) {
            return redirect()->route('student.clearance-updates')->with('flash', [
                'type' => 'info',
                'title' => 'Subjects are already set',
                'message' => 'Your subjects come from your section, so there is nothing to choose here.',
            ]);
        }

        if (! StudentSubjects::irregularTableAvailable()) {
            return view('student.my-subjects', [
                'student' => $student,
                'available' => false,
                'enrolled' => collect(),
                'offered' => collect(),
            ]);
        }

        return view('student.my-subjects', [
            'student' => $student,
            'available' => true,
            'enrolled' => $this->enrolledRows($student->student_id),
            'offered' => $this->offeredChoices(),
        ]);
    }

    public function store(Request $request)
    {
        $student = $this->enrollingStudent();

        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subject_codes,subject_id'],
            'instructor_id' => ['required', 'string', 'max:50', 'exists:instructor_account,instructor_id'],
        ]);

        $subjectId = (int) $data['subject_id'];
        $instructorId = $data['instructor_id'];

        // The instructor has to actually teach the subject. Without this a
        // student could post any pairing and route their clearance anywhere.
        $teaches = StudentSubjects::instructorsTeaching($subjectId)
            ->contains(fn ($candidate) => (string) $candidate->instructor_id === $instructorId);

        if (! $teaches) {
            throw ValidationException::withMessages([
                'instructor_id' => 'That instructor does not teach the selected subject.',
            ]);
        }

        $alreadyTaking = DB::table('irregular_enrollment')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $subjectId)
            ->exists();

        if ($alreadyTaking) {
            throw ValidationException::withMessages([
                'subject_id' => 'That subject is already on your list. Remove it first to change instructor.',
            ]);
        }

        DB::table('irregular_enrollment')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'enrolled_at' => now(),
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Subject added. You can now submit your clearance to that instructor.',
        ]);
    }

    public function destroy(Request $request)
    {
        $student = $this->enrollingStudent();

        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'instructor_id' => ['required', 'string', 'max:50'],
        ]);

        $subjectId = (int) $data['subject_id'];
        $instructorId = $data['instructor_id'];

        $enrolled = DB::table('irregular_enrollment')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $subjectId)
            ->where('instructor_id', $instructorId)
            ->exists();

        if (! $enrolled) {
            return back()->with('flash', ['type' => 'error', 'message' => 'That subject is no longer on your list.']);
        }

        $approved = DB::table('clearance_status')
            ->where('student_id', $student->student_id)
            ->where('subject_id', $subjectId)
            ->where('instructor_id', $instructorId)
            ->where('status', 'Approved')
            ->exists();

        if ($approved) {
            return back()->with('flash', [
                'type' => 'error',
                'title' => 'Already approved',
                'message' => 'This subject has already been cleared, so it cannot be removed. Ask your instructor to set it back to pending first.',
            ]);
        }

        // Dropping the subject takes its clearance record, remarks and uploaded
        // file with it; a stranded row would keep counting toward the dean and
        // registrar prerequisite for a subject no longer being taken.
        DB::transaction(function () use ($student, $subjectId, $instructorId): void {
            RecordPurge::enrollment((string) $student->student_id, $subjectId, $instructorId);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Subject removed from your list.']);
    }

    /** The signed-in student, refused unless they manage their own subjects. */
    private function enrollingStudent()
    {
        $student = Auth::guard('student')->user();

        abort_unless(StudentSubjects::picksOwnSubjects($student), 403);

        return $student;
    }

    /** This student's chosen subjects, with the clearance state of each. */
    private function enrolledRows(string $studentId)
    {
        return DB::table('irregular_enrollment as ie')
            ->leftJoin('subject_codes as sc', 'sc.subject_id', '=', 'ie.subject_id')
            ->leftJoin('instructor_account as i', 'i.instructor_id', '=', 'ie.instructor_id')
            ->leftJoin('clearance_status as cs', function ($join) use ($studentId) {
                $join->on('cs.subject_id', '=', 'ie.subject_id')
                    ->on('cs.instructor_id', '=', 'ie.instructor_id')
                    ->where('cs.student_id', '=', $studentId);
            })
            ->where('ie.student_id', $studentId)
            ->orderBy('sc.subject_code')
            ->get([
                'ie.subject_id', 'ie.instructor_id',
                'sc.subject_code', 'sc.subject_description', 'sc.year_level', 'sc.semester',
                'i.firstname as instructor_firstname', 'i.lastname as instructor_lastname',
                'cs.status as clearance_status',
            ]);
    }

    /**
     * The menu of subjects, each with the instructors who teach it.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function offeredChoices()
    {
        return StudentSubjects::offeredSubjects()
            ->groupBy('subject_id')
            ->map(fn ($rows) => [
                'subject_id' => $rows->first()->subject_id,
                'subject_code' => $rows->first()->subject_code,
                'subject_description' => $rows->first()->subject_description,
                'year_level' => $rows->first()->year_level,
                'semester' => $rows->first()->semester,
                'instructors' => $rows->map(fn ($row) => [
                    'instructor_id' => $row->instructor_id,
                    'name' => trim("{$row->instructor_firstname} {$row->instructor_lastname}"),
                ])->unique('instructor_id')->values()->all(),
            ])
            ->sortBy('subject_code')
            ->values();
    }
}
