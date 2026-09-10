<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The College of Education Department Head sits above the BSED and BEED program
 * heads. Those students clear their own program head first, then the department
 * head, and the registrar waits on both. No other program has that step at all,
 * which makes it the first office that is not part of every student's chain.
 */
class EducationDepartmentHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_bsed_and_beed_students_have_the_department_head_step(): void
    {
        foreach (['BSED', 'BEED'] as $program) {
            $keys = array_column(ClearanceWorkflow::officeChainFor($this->student("E-{$program}", $program)), 'key');
            $this->assertContains('education department head', $keys, "{$program} must clear the department head.");
        }

        foreach (['BSIT', 'BSBA', 'BSHM'] as $program) {
            $keys = array_column(ClearanceWorkflow::officeChainFor($this->student("O-{$program}", $program)), 'key');
            $this->assertNotContains('education department head', $keys, "{$program} must not have that step.");
        }
    }

    public function test_it_sits_directly_below_the_program_head_and_above_the_registrar(): void
    {
        $keys = array_column(ClearanceWorkflow::officeChainFor($this->student('E-1', 'BSED')), 'key');

        $this->assertSame(
            ['dean', 'education department head', 'registrar'],
            array_values(array_slice($keys, -3)),
            'The department head is printed and listed between the program head and the registrar.',
        );
    }

    public function test_the_department_head_waits_for_the_students_own_program_head(): void
    {
        $student = $this->student('E-2', 'BSED');
        $this->clearEverythingUpToDean($student);

        // The program head has not signed yet.
        $this->assertFalse(ClearanceWorkflow::prerequisitesMet($student, 'education department head'));

        $this->approve($student, 'dean');
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'education department head'));
    }

    public function test_the_registrar_waits_for_the_department_head_too(): void
    {
        $student = $this->student('E-3', 'BSED');
        $this->clearEverythingUpToDean($student);
        $this->approve($student, 'dean');

        // Everything except the new office is signed.
        $this->assertFalse(ClearanceWorkflow::prerequisitesMet($student, 'registrar'));

        $this->approve($student, 'education department head');
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'registrar'));
    }

    public function test_a_student_outside_the_college_reaches_the_registrar_without_it(): void
    {
        $student = $this->student('O-1', 'BSIT');
        $this->clearEverythingUpToDean($student);
        $this->approve($student, 'dean');

        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'registrar'));
        $this->assertFalse(ClearanceWorkflow::prerequisitesMet($student, 'education department head'));
    }

    public function test_a_bsit_student_cannot_submit_to_the_department_head(): void
    {
        $student = $this->student('O-2', 'BSIT');
        $this->clearEverythingUpToDean($student);
        $this->approve($student, 'dean');

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-office'), ['office_role' => 'education department head'])
            ->assertSessionHasErrors('office_role');

        $this->assertDatabaseMissing('office_clearance_status', [
            'student_id' => 'O-2', 'office_role' => 'education department head',
        ]);
    }

    public function test_a_bsed_student_submits_once_their_program_head_has_signed(): void
    {
        $student = $this->student('E-4', 'BSED');
        $this->clearEverythingUpToDean($student);

        // Too early — the program head has not signed.
        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-office'), ['office_role' => 'education department head'])
            ->assertSessionHasErrors('office_role');

        $this->approve($student, 'dean');

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-office'), ['office_role' => 'education department head'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('office_clearance_status', [
            'student_id' => 'E-4', 'office_role' => 'education department head', 'status' => 'Pending',
        ]);
    }

    public function test_the_office_holder_reviews_only_college_of_education_students(): void
    {
        $head = AdminPersonnel::create([
            'personnel_id' => 'AP-EDU', 'firstname' => 'Elena', 'lastname' => 'Bautista',
            'email' => 'edu.head@example.test', 'password' => 'PersonnelPassword1!',
            'role' => 'education_department_head',
        ]);

        $bsed = $this->student('E-5', 'BSED');
        $bsit = $this->student('O-5', 'BSIT');
        foreach ([$bsed, $bsit] as $student) {
            DB::table('office_clearance_status')->insert([
                'student_id' => $student->student_id, 'office_role' => 'education department head',
                'approver_id' => $student->student_id, 'status' => 'Pending',
            ]);
        }

        $access = app(\App\Support\ClearanceAccess::class);
        $this->assertTrue($access->officeCanReview($head, $bsed));
        $this->assertFalse($access->officeCanReview($head, $bsit), 'A BSIT student is outside this office.');
    }

    public function test_the_position_is_offered_in_the_personnel_role_list(): void
    {
        $this->assertArrayHasKey('education_department_head', AdminPersonnel::$validRoles);
        $this->assertSame(
            'College of Education Department Head',
            AdminPersonnel::$validRoles['education_department_head'],
        );
    }

    public function test_the_role_name_does_not_collapse_into_the_program_head(): void
    {
        // 'department head' on its own still means the program head, so the
        // education one has to be recognised ahead of it.
        $this->assertSame('education department head', ClearanceWorkflow::normalizeOfficeRole('education_department_head'));
        $this->assertSame('education department head', ClearanceWorkflow::normalizeOfficeRole('College of Education Department Head'));
        $this->assertSame('dean', ClearanceWorkflow::normalizeOfficeRole('department head'));
        $this->assertSame('dean', ClearanceWorkflow::normalizeOfficeRole('program_head_bsed'));
    }

    private function clearEverythingUpToDean(StudentAccount $student): void
    {
        foreach ([
            'section treasurer', 'department treasurer', 'property custodian',
            'scc adviser', 'sas director', 'guidance office', 'library',
        ] as $role) {
            $this->approve($student, $role);
        }

        // The dean and registrar also wait on every instructor clearance.
        $instructor = \App\Models\Instructor::firstOrCreate(
            ['instructor_id' => 'INS-1'],
            ['firstname' => 'Ins', 'lastname' => 'Tructor', 'email' => 'ins-1@example.test',
             'password' => 'InstructorPassword1!', 'department' => 'BSIT'],
        );
        $subject = \App\Models\SubjectCode::firstOrCreate(
            ['subject_code' => 'GEN101'],
            ['subject_description' => 'General Subject', 'year_level' => '1', 'program' => 'BSIT', 'semester' => '1st Semester'],
        );

        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructor->instructor_id, 'subject_id' => $subject->subject_id,
            'program' => $student->program, 'year_level' => $student->year_level, 'section' => $student->section,
        ]);
        DB::table('clearance_status')->insert([
            'student_id' => $student->student_id, 'subject_id' => $subject->subject_id,
            'instructor_id' => $instructor->instructor_id, 'status' => 'Approved',
        ]);
    }

    private function approve(StudentAccount $student, string $role): void
    {
        DB::table('office_clearance_status')->updateOrInsert(
            ['student_id' => $student->student_id, 'office_role' => $role],
            ['status' => 'Approved', 'approver_id' => $student->student_id, 'updated_at' => now()],
        );
    }

    private function student(string $id, string $program): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => "student-{$id}@example.test",
            'password' => 'StudentPassword1!',
            'program' => $program,
            'year_level' => '1',
            'section' => 'A',
        ]);
    }
}
