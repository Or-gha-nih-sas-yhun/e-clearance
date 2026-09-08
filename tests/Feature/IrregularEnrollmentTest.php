<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use App\Support\StudentSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Irregular students declare their own subjects, and every screen has to agree.
 *
 * A regular student's subjects come from their section's block. An irregular
 * student's come from `irregular_enrollment`, which they fill in themselves —
 * and once a row is there, submitting, being approved and chatting all have to
 * work exactly as they do for a regular student.
 */
class IrregularEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_student_has_no_subject_picker(): void
    {
        $student = $this->student('2026-0001', 'Regular');

        $this->actingAs($student, 'student')
            ->get(route('student.subjects.index'))
            ->assertRedirect(route('student.clearance-updates'));

        $this->actingAs($student, 'student')
            ->post(route('student.subjects.store'), ['subject_id' => 1, 'instructor_id' => 'INS-1'])
            ->assertForbidden();
    }

    public function test_an_irregular_student_picks_a_subject_and_one_of_its_instructors(): void
    {
        $student = $this->student('2026-0002');
        $instructor = $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign($instructor, $subject, 'BSIT', '1', 'A');

        $this->actingAs($student, 'student')
            ->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee('Your Subjects')
            ->assertSee('IT101');

        $this->actingAs($student, 'student')
            ->post(route('student.subjects.store'), [
                'subject_id' => $subject,
                'instructor_id' => 'INS-1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('irregular_enrollment', [
            'student_id' => '2026-0002', 'subject_id' => $subject, 'instructor_id' => 'INS-1',
        ]);
    }

    public function test_an_instructor_who_does_not_teach_the_subject_is_refused(): void
    {
        $student = $this->student('2026-0003');
        $this->instructor('INS-1');
        $stranger = $this->instructor('INS-2');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSIT', '1', 'A');

        $this->actingAs($student, 'student')
            ->post(route('student.subjects.store'), [
                'subject_id' => $subject,
                'instructor_id' => 'INS-2',
            ])
            ->assertSessionHasErrors('instructor_id');

        $this->assertDatabaseCount('irregular_enrollment', 0);
    }

    public function test_the_chosen_subjects_replace_the_section_block_on_the_clearance_page(): void
    {
        $student = $this->student('2026-0004');
        $blockInstructor = $this->instructor('INS-BLOCK');
        $chosenInstructor = $this->instructor('INS-CHOSEN');
        $blockSubject = $this->subject('BLOCK1');
        $chosenSubject = $this->subject('CHOSEN1');

        // The student's own section teaches BLOCK1 — but they are irregular.
        $this->assign('INS-BLOCK', $blockSubject, 'BSIT', '1', 'A');
        $this->assign('INS-CHOSEN', $chosenSubject, 'BSIT', '3', 'B');
        $this->enroll('2026-0004', $chosenSubject, 'INS-CHOSEN');

        $this->actingAs($student, 'student')
            ->get(route('student.clearance-updates'))
            ->assertOk()
            ->assertSee('CHOSEN1')
            ->assertDontSee('BLOCK1');
    }

    public function test_the_student_submits_to_a_chosen_instructor_without_a_forbidden_error(): void
    {
        $student = $this->student('2026-0005');
        $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        // Assigned to a section that is not this student's, which is the whole point.
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0005', $subject, 'INS-1');

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-instructor'), [
                'subject_id' => $subject,
                'instructor_id' => 'INS-1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clearance_status', [
            'student_id' => '2026-0005', 'subject_id' => $subject,
            'instructor_id' => 'INS-1', 'status' => 'Pending',
        ]);
    }

    public function test_a_subject_the_student_never_enrolled_in_is_still_refused(): void
    {
        $student = $this->student('2026-0006');
        $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSIT', '1', 'A');

        // Their own section teaches it, but an irregular student takes only what
        // they declared.
        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-instructor'), [
                'subject_id' => $subject,
                'instructor_id' => 'INS-1',
            ])
            ->assertForbidden();
    }

    public function test_the_chosen_instructor_can_approve_and_then_set_it_back_to_pending(): void
    {
        $student = $this->student('2026-0007');
        $instructor = $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0007', $subject, 'INS-1');

        $this->actingAs($instructor, 'instructor')
            ->post(route('instructor.clearance.approve'), [
                'student_id' => '2026-0007', 'subject_id' => $subject,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('clearance_status', [
            'student_id' => '2026-0007', 'subject_id' => $subject, 'status' => 'Approved',
        ]);

        $this->actingAs($instructor, 'instructor')
            ->post(route('instructor.clearance.pending'), [
                'student_id' => '2026-0007', 'subject_id' => $subject,
            ])
            ->assertOk();

        $this->assertDatabaseHas('clearance_status', [
            'student_id' => '2026-0007', 'subject_id' => $subject, 'status' => 'Pending',
        ]);
    }

    public function test_the_irregular_student_appears_in_their_chosen_instructors_listing(): void
    {
        $student = $this->student('2026-0008', 'Irregular', 'Ella', 'Marquez');
        $instructor = $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0008', $subject, 'INS-1');

        $this->actingAs($instructor, 'instructor')
            ->get(route('instructor.clearance'))
            ->assertOk()
            ->assertSee('Marquez');
    }

    public function test_the_student_and_their_chosen_instructor_can_reach_each_other_in_chat(): void
    {
        $student = $this->student('2026-0009', 'Irregular', 'Ella', 'Marquez');
        $instructor = $this->instructor('INS-1', 'Ana', 'Cruz');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0009', $subject, 'INS-1');

        // Student -> instructor.
        $this->actingAs($student, 'student')
            ->get(route('student.chat-support'))
            ->assertOk()
            ->assertSee('Cruz');

        // Instructor -> student.
        $this->actingAs($instructor, 'instructor')
            ->get(route('instructor.chat'))
            ->assertOk()
            ->assertSee('Marquez');
    }

    public function test_removing_a_subject_takes_its_clearance_record_with_it(): void
    {
        $student = $this->student('2026-0010');
        $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0010', $subject, 'INS-1');

        DB::table('clearance_status')->insert([
            'student_id' => '2026-0010', 'subject_id' => $subject,
            'instructor_id' => 'INS-1', 'status' => 'Pending',
        ]);

        $this->actingAs($student, 'student')
            ->delete(route('student.subjects.destroy'), [
                'subject_id' => $subject, 'instructor_id' => 'INS-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('irregular_enrollment', 0);
        $this->assertDatabaseCount('clearance_status', 0);
    }

    public function test_an_approved_subject_cannot_be_dropped(): void
    {
        $student = $this->student('2026-0011');
        $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0011', $subject, 'INS-1');

        DB::table('clearance_status')->insert([
            'student_id' => '2026-0011', 'subject_id' => $subject,
            'instructor_id' => 'INS-1', 'status' => 'Approved',
        ]);

        $this->actingAs($student, 'student')
            ->delete(route('student.subjects.destroy'), [
                'subject_id' => $subject, 'instructor_id' => 'INS-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('irregular_enrollment', 1);
        $this->assertDatabaseCount('clearance_status', 1);
    }

    public function test_the_dean_prerequisite_counts_the_chosen_subjects_not_the_section_block(): void
    {
        $student = $this->student('2026-0012');
        $this->instructor('INS-BLOCK');
        $this->instructor('INS-CHOSEN');
        $blockSubject = $this->subject('BLOCK1');
        $chosenSubject = $this->subject('CHOSEN1');
        $this->assign('INS-BLOCK', $blockSubject, 'BSIT', '1', 'A');
        $this->assign('INS-CHOSEN', $chosenSubject, 'BSHM', '4', 'Z');
        $this->enroll('2026-0012', $chosenSubject, 'INS-CHOSEN');

        $this->assertFalse(ClearanceWorkflow::allInstructorClearancesApproved($student));

        // Approving only the section block subject must not clear them.
        DB::table('clearance_status')->insert([
            'student_id' => '2026-0012', 'subject_id' => $blockSubject,
            'instructor_id' => 'INS-BLOCK', 'status' => 'Approved',
        ]);
        $this->assertFalse(ClearanceWorkflow::allInstructorClearancesApproved($student->fresh()));

        // Approving what they actually chose does.
        DB::table('clearance_status')->insert([
            'student_id' => '2026-0012', 'subject_id' => $chosenSubject,
            'instructor_id' => 'INS-CHOSEN', 'status' => 'Approved',
        ]);
        $this->assertTrue(ClearanceWorkflow::allInstructorClearancesApproved($student->fresh()));
    }

    public function test_a_regular_student_still_clears_their_section_block(): void
    {
        $student = $this->student('2026-0013', 'Regular');
        $this->instructor('INS-1');
        $subject = $this->subject('IT101');
        $this->assign('INS-1', $subject, 'BSIT', '1', 'A');

        $this->assertTrue(StudentSubjects::covers($student, $subject, 'INS-1'));
        $this->assertSame(1, StudentSubjects::forStudent($student)->count());

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-instructor'), [
                'subject_id' => $subject, 'instructor_id' => 'INS-1',
            ])
            ->assertSessionHasNoErrors();
    }

    private function student(string $id, string $type = 'Irregular', string $first = 'Test', string $last = 'Student'): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => $first,
            'lastname' => $last,
            'email' => "student-{$id}@example.test",
            'password' => 'StudentPassword1!',
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => 'A',
            'student_type' => $type,
        ]);
    }

    private function instructor(string $id, string $first = 'Ins', string $last = 'Tructor'): Instructor
    {
        return Instructor::create([
            'instructor_id' => $id,
            'firstname' => $first,
            'lastname' => $last,
            'email' => "instructor-{$id}@example.test",
            'password' => 'InstructorPassword1!',
            'department' => 'BSIT',
        ]);
    }

    private function subject(string $code): int
    {
        return DB::table('subject_codes')->insertGetId([
            'subject_code' => $code,
            'subject_description' => "Course {$code}",
            'year_level' => '1',
            'program' => 'BSIT',
            'semester' => '1st Semester',
        ], 'subject_id');
    }

    private function assign(string|Instructor $instructor, int $subjectId, string $program, string $year, string $section): void
    {
        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructor instanceof Instructor ? $instructor->instructor_id : $instructor,
            'subject_id' => $subjectId,
            'program' => $program,
            'year_level' => $year,
            'section' => $section,
        ]);
    }

    private function enroll(string $studentId, int $subjectId, string $instructorId): void
    {
        DB::table('irregular_enrollment')->insert([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
        ]);
    }
}
