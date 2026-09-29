<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\StudentAccount;
use App\Support\StudentSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UploadFixtures;
use Tests\TestCase;

class BridgingSubjectEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bridging_subject_is_hidden_until_the_student_confirms_it(): void
    {
        Storage::fake('local');
        $student = $this->student('2026-1001');
        $instructor = $this->instructor('INS-BRIDGE');
        $subject = $this->subject('BRI101');
        $this->assign($instructor->instructor_id, $subject, 'A');

        $this->assertFalse(StudentSubjects::covers($student, $subject, $instructor->instructor_id));

        $this->actingAs($student, 'student')
            ->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee('BRI101')
            ->assertSee('Add Bridging Subjects');

        $this->actingAs($student, 'student')->get(route('student.clearance-updates'))
            ->assertOk()->assertDontSee('BRI101');
        $this->actingAs($student, 'student')->get(route('student.submission-remark'))
            ->assertOk()->assertDontSee('BRI101');
        $this->actingAs($student, 'student')->get(route('student.dashboard'))
            ->assertOk()->assertDontSee('BRI101');

        $studentContacts = $this->actingAs($student, 'student')
            ->get(route('student.chat-support'))->assertOk()->viewData('contacts');
        $this->assertNotContains($instructor->instructor_id, $studentContacts->pluck('id')->all());

        $this->actingAs($student, 'student')
            ->post(route('student.chat.send'), [
                'receiver_id' => $instructor->instructor_id,
                'partner_role' => 'instructor',
                'message' => 'Can I submit my bridging requirement?',
            ])->assertForbidden();

        $this->actingAs($instructor, 'instructor')
            ->post(route('instructor.clearance.approve'), [
                'student_id' => $student->student_id,
                'subject_id' => $subject,
            ])->assertForbidden();

        $this->actingAs($student, 'student')
            ->post(route('student.subjects.bridging.store'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bridging_enrollment', [
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
        ]);
        $this->assertTrue(StudentSubjects::covers($student, $subject, $instructor->instructor_id));

        $this->actingAs($student, 'student')->get(route('student.clearance-updates'))
            ->assertOk()->assertSee('BRI101');
        $this->actingAs($student, 'student')->get(route('student.submission-remark'))
            ->assertOk()->assertSee('BRI101');

        $studentContacts = $this->actingAs($student, 'student')
            ->get(route('student.chat-support'))->assertOk()->viewData('contacts');
        $this->assertContains($instructor->instructor_id, $studentContacts->pluck('id')->all());

        $instructorContacts = $this->actingAs($instructor, 'instructor')
            ->get(route('instructor.chat'))->assertOk()->viewData('contacts');
        $this->assertContains($student->student_id, $instructorContacts->pluck('id')->all());

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-instructor'), [
                'subject_id' => $subject,
                'instructor_id' => $instructor->instructor_id,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($student, 'student')
            ->post(route('student.submission-remark.upload'), [
                'subject_id' => $subject,
                'instructor_id' => $instructor->instructor_id,
                'description' => 'Bridging completion document',
                'submission_file' => UploadedFile::fake()->createWithContent('bridging.pdf', UploadFixtures::pdf()),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clearance_status', [
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('student_submissions', [
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
        ]);

        $this->actingAs($instructor, 'instructor')
            ->post(route('instructor.clearance.approve'), [
                'student_id' => $student->student_id,
                'subject_id' => $subject,
            ])->assertOk()->assertJsonPath('success', true);

        $this->actingAs($student, 'student')->get(route('student.dashboard'))
            ->assertOk()->assertSee('BRI101');
    }

    public function test_removing_a_pending_bridging_subject_purges_its_clearance_and_submission(): void
    {
        Storage::fake('local');
        $student = $this->student('2026-1002');
        $instructor = $this->instructor('INS-REMOVE');
        $subject = $this->subject('BRI102');
        $this->assign($instructor->instructor_id, $subject, 'A');
        $this->enroll($student, $subject, $instructor);

        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
            'status' => 'Pending',
            'updated_at' => now(),
        ]);
        $path = "student_submissions/{$student->student_id}/bridging.pdf";
        Storage::disk('local')->put($path, UploadFixtures::pdf());
        DB::table('student_submissions')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
            'file_path' => $path,
            'file_name' => 'bridging.pdf',
            'file_type' => 'application/pdf',
            'submitted_at' => now(),
        ]);

        $this->actingAs($student, 'student')
            ->delete(route('student.subjects.bridging.destroy'), [
                'subject_id' => $subject,
                'instructor_id' => $instructor->instructor_id,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('bridging_enrollment', ['student_id' => $student->student_id, 'subject_id' => $subject]);
        $this->assertDatabaseMissing('clearance_status', ['student_id' => $student->student_id, 'subject_id' => $subject]);
        $this->assertDatabaseMissing('student_submissions', ['student_id' => $student->student_id, 'subject_id' => $subject]);
        Storage::disk('local')->assertMissing($path);
        $this->assertFalse(StudentSubjects::covers($student, $subject, $instructor->instructor_id));
    }

    public function test_an_approved_bridging_subject_cannot_be_removed(): void
    {
        $student = $this->student('2026-1003');
        $instructor = $this->instructor('INS-APPROVED');
        $subject = $this->subject('BRI103');
        $this->assign($instructor->instructor_id, $subject, 'A');
        $this->enroll($student, $subject, $instructor);
        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
            'status' => 'Approved',
            'updated_at' => now(),
        ]);

        $this->actingAs($student, 'student')
            ->delete(route('student.subjects.bridging.destroy'), [
                'subject_id' => $subject,
                'instructor_id' => $instructor->instructor_id,
            ])->assertRedirect()->assertSessionHas('flash.type', 'error');

        $this->assertDatabaseHas('bridging_enrollment', [
            'student_id' => $student->student_id,
            'subject_id' => $subject,
            'instructor_id' => $instructor->instructor_id,
        ]);
    }

    public function test_confirming_adds_only_bridging_subjects_for_the_students_exact_section(): void
    {
        $student = $this->student('2026-1004');
        $instructor = $this->instructor('INS-SECTIONS');
        $forA = $this->subject('BRI-A');
        $forB = $this->subject('BRI-B');
        $this->assign($instructor->instructor_id, $forA, 'A');
        $this->assign($instructor->instructor_id, $forB, 'B');

        $this->actingAs($student, 'student')
            ->post(route('student.subjects.bridging.store'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bridging_enrollment', [
            'student_id' => $student->student_id,
            'subject_id' => $forA,
        ]);
        $this->assertDatabaseMissing('bridging_enrollment', [
            'student_id' => $student->student_id,
            'subject_id' => $forB,
        ]);
    }

    private function student(string $id): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => 'Bridge',
            'lastname' => 'Student',
            'email' => "{$id}@example.test",
            'password' => 'StudentPassword1!',
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);
    }

    private function instructor(string $id): Instructor
    {
        return Instructor::create([
            'instructor_id' => $id,
            'firstname' => 'Bridging',
            'lastname' => 'Instructor',
            'email' => "{$id}@example.test",
            'password' => 'InstructorPassword1!',
            'department' => 'BSIT',
        ]);
    }

    private function subject(string $code): int
    {
        return DB::table('subject_codes')->insertGetId([
            'subject_code' => $code.' (BRIDGING)',
            'subject_description' => "Course {$code}",
            'year_level' => '1',
            'program' => 'BSIT',
            'semester' => '1st Semester',
        ], 'subject_id');
    }

    private function assign(string $instructorId, int $subjectId, string $section): void
    {
        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructorId,
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => $section,
        ]);
    }

    private function enroll(StudentAccount $student, int $subjectId, Instructor $instructor): void
    {
        DB::table('bridging_enrollment')->insert([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => $instructor->instructor_id,
            'enrolled_at' => now(),
        ]);
    }
}
