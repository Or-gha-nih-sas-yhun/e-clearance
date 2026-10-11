<?php

namespace Tests\Feature;

use App\Http\Controllers\Instructors\SubmissionController;
use App\Models\Instructor;
use App\Models\StudentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstructorClearanceStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clearance_status');
        Schema::create('clearance_status', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('notifications', function ($table) {
            $table->id();
            $table->string('user_id');
            $table->string('recipient_role')->default('student');
            $table->text('message');
            $table->string('notif_type')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('student_account', function ($table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->timestamps();
        });

        Schema::create('subject_codes', function ($table) {
            $table->id('subject_id');
            $table->string('subject_code')->unique();
            $table->string('subject_description')->nullable();
            $table->string('year_level');
            $table->string('program');
            $table->string('semester');
        });

        Schema::create('instructor_account', function ($table) {
            $table->id();
            $table->string('instructor_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('department');
            $table->timestamps();
        });

        Schema::create('instructor_assignment', function ($table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->timestamps();
        });

        Schema::create('student_submissions', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });

        Schema::create('instructor_remarks', function ($table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->text('remark');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_instructor_can_set_clearance_back_to_pending(): void
    {
        $instructor = $this->createAssignedContext('student-1', 42, 'inst-1');
        $this->actingAs($instructor, 'instructor');

        $controller = new SubmissionController;
        $request = Request::create('/instructor/submissions/clearance', 'POST', [
            'student_id' => 'student-1',
            'subject_id' => 42,
            'status' => 'Pending',
            'remarks' => 'Please resubmit',
        ]);

        $response = $controller->setClearance($request);

        $this->assertTrue($response->getData()->success);
        $this->assertSame('Clearance Set to Pending', $response->getData()->title);
        $this->assertDatabaseHas('clearance_status', [
            'student_id' => 'student-1',
            'subject_id' => 42,
            'instructor_id' => 'inst-1',
            'status' => 'Pending',
        ]);
    }

    public function test_instructor_clearance_uses_the_instructor_id_field_for_persistence(): void
    {
        $instructor = $this->createAssignedContext('student-1', 42, 'inst-1');
        $this->actingAs($instructor, 'instructor');

        $controller = new SubmissionController;
        $request = Request::create('/instructor/submissions/clearance', 'POST', [
            'student_id' => 'student-1',
            'subject_id' => 42,
            'status' => 'Approved',
            'remarks' => 'Looks good',
        ]);

        $response = $controller->setClearance($request);

        $this->assertTrue($response->getData()->success);
        $this->assertSame('Clearance Approved', $response->getData()->title);
        $this->assertDatabaseHas('clearance_status', [
            'student_id' => 'student-1',
            'subject_id' => 42,
            'instructor_id' => 'inst-1',
            'status' => 'Approved',
        ]);
        $this->assertDatabaseMissing('clearance_status', [
            'student_id' => 'student-1',
            'subject_id' => 42,
            'instructor_id' => 99,
        ]);
    }

    public function test_instructor_submissions_page_shows_pending_clearance_requests_without_files(): void
    {
        $studentId = 'student-2';
        $subjectId = 77;

        DB::table('student_account')->insert([
            'student_id' => $studentId,
            'firstname' => 'Ana',
            'lastname' => 'Lopez',
            'email' => 'ana@example.com',
            'password' => bcrypt('secret'),
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('subject_codes')->insert([
            'subject_id' => $subjectId,
            'subject_code' => 'IT777',
            'subject_description' => 'Capstone',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st',
        ]);

        DB::table('instructor_account')->insert([
            'instructor_id' => 'inst-1',
            'firstname' => 'Liza',
            'lastname' => 'Teacher',
            'email' => 'liza@example.com',
            'password' => bcrypt('secret'),
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => 'inst-1',
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('clearance_status')->insert([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => 'inst-1',
            'status' => 'Pending',
            'remarks' => null,
        ]);

        $this->actingAs(Instructor::where('instructor_id', 'inst-1')->firstOrFail(), 'instructor');

        $controller = new SubmissionController;
        $response = $controller->index(new Request);
        $submissions = $response->getData()['submissions'];

        $this->assertStringContainsString('IT777', $response->render());
        $this->assertStringContainsString('No file yet', $response->render());
        $this->assertStringContainsString($studentId, $response->render());
        $this->assertSame($studentId, $submissions->first()->student_id);
    }

    private function createAssignedContext(string $studentId, int $subjectId, string $instructorId): Instructor
    {
        StudentAccount::create([
            'student_id' => $studentId,
            'firstname' => 'Assigned',
            'lastname' => 'Student',
            'email' => $studentId.'@example.com',
            'password' => 'StrongPassword1!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        DB::table('subject_codes')->insert([
            'subject_id' => $subjectId,
            'subject_code' => 'IT'.$subjectId,
            'subject_description' => 'Assigned Subject',
            'year_level' => '4',
            'program' => 'BSIT',
            'semester' => '1st Semester',
        ]);

        $instructor = Instructor::create([
            'instructor_id' => $instructorId,
            'firstname' => 'Assigned',
            'lastname' => 'Instructor',
            'email' => $instructorId.'@example.com',
            'password' => 'StrongPassword1!',
            'department' => 'BSIT',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructorId,
            'subject_id' => $subjectId,
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
        ]);

        return $instructor;
    }
}
