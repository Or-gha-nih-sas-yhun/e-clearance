<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use App\Support\LibraryEvaluation;
use App\Support\RecordPurge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GuidanceEvaluationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        Schema::create('office_clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->string('office_role');
            $table->string('approver_id');
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'office_role']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('recipient_role')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->string('notif_type')->nullable();
            $table->string('link_url')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('subject_codes', function (Blueprint $table) {
            $table->id('subject_id');
            $table->string('subject_code');
            $table->string('subject_description')->nullable();
        });
        Schema::create('instructor_account', function (Blueprint $table) {
            $table->id();
            $table->string('instructor_id');
            $table->string('firstname');
            $table->string('lastname');
        });
        Schema::create('instructor_assignment', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        Schema::create('clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        (require database_path('migrations/2024_01_01_000016_create_office_submissions_table.php'))->up();
        (require database_path('migrations/2026_09_21_000001_create_library_evaluation_tables.php'))->up();
        (require database_path('migrations/2026_09_21_000002_make_library_evaluation_single_form.php'))->up();
        (require database_path('migrations/2026_09_22_000001_add_library_evaluation_sections.php'))->up();
        (require database_path('migrations/2026_09_22_000002_create_guidance_evaluation_tables.php'))->up();
    }

    public function test_guidance_manages_one_form_with_sections_and_separate_responses(): void
    {
        $student = $this->student();
        $this->student('S-2');
        $this->actingAs($this->staff('guidance'), 'office');
        $this->get(route('office.guidance-evaluations.create'))->assertOk()
            ->assertSee('Guidance Evaluation')->assertSee('Add question set');
        $this->post(route('office.guidance-evaluations.store'), [
            'title' => 'Guidance Services Evaluation',
            'description' => 'Rate your guidance experience.',
            'sections' => [
                ['title' => 'Counseling', 'description' => 'Consider your recent visits.', 'questions' => ['The staff listened to me.', 'The service was helpful.']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $form = LibraryEvaluation::form('guidance');
        $this->assertSame(1, (int) $form->singleton);
        $this->assertSame(['The staff listened to me.', 'The service was helpful.'], LibraryEvaluation::questions($form));
        $this->assertDatabaseCount('library_evaluations', 0);
        $this->postJson(route('office.guidance-evaluations.store'), [
            'title' => 'Another form', 'questions' => ['Another question.'],
        ])->assertUnprocessable()->assertJsonValidationErrors('evaluation');
        $this->get(route('office.guidance-evaluations.index'))->assertOk()
            ->assertSee('View evaluation form')->assertSee('Counseling')->assertSee('Publish evaluation');
        $this->post(route('office.guidance-evaluations.publish', $form->id), ['revision' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(ClearanceWorkflow::prerequisitesMet($student, 'guidance office'));

        $this->actingAs($student, 'student')->get(route('student.guidance-evaluation.index'))->assertOk()
            ->assertSee('Consider your recent visits.')->assertSee('name="ratings[1]"', false);
        $this->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $form->id, 'revision' => 1, 'ratings' => [5, 4],
        ])->assertRedirect(route('student.clearance-updates'))->assertSessionHasNoErrors();
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'guidance office'));
        $this->assertNotNull(LibraryEvaluation::response($form->id, 'S-1', 'guidance'));
        $this->assertDatabaseCount('library_evaluation_responses', 0);

        $this->actingAs($this->staff('guidance'), 'office')->get(route('office.guidance-evaluations.index'))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['completed'] === 1 && $summary['pending'] === 1)
            ->assertSee('data-evaluation-chart="completion"', false)
            ->assertSee('View response');
        $responseId = LibraryEvaluation::response($form->id, 'S-1', 'guidance')->id;
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('office.guidance-evaluations.response', $responseId))
            ->assertOk()->assertSee('Counseling')->assertSee('Strongly Agree');
        $csv = $this->get(route('office.guidance-evaluations.export', $form->id))
            ->assertOk()->assertDownload('guidance-evaluation-responses-'.now()->format('Y-m-d').'.csv')->streamedContent();
        $this->assertStringContainsString('Q1. Counseling', $csv);
        $this->assertStringContainsString('5 - Strongly Agree', $csv);
    }

    public function test_guidance_clearance_requests_and_approvals_require_a_current_response(): void
    {
        $id = $this->guidanceForm();
        $student = $this->student();
        $this->actingAs($student, 'student')->get(route('student.clearance-updates'))
            ->assertOk()->assertSee('Guidance Evaluation: Not completed');
        $this->postJson(route('student.clearance.submit-office'), ['office_role' => 'Guidance Office'])
            ->assertUnprocessable()->assertJsonValidationErrors('office_role');
        DB::table('office_clearance_status')->insert([
            'student_id' => 'S-1', 'office_role' => 'guidance office',
            'approver_id' => 'S-1', 'status' => 'Pending', 'updated_at' => now(),
        ]);
        $this->actingAs($this->staff('guidance'), 'office')
            ->postJson(route('office.clearance.status'), ['student_id' => 'S-1', 'status' => 'Approved'])
            ->assertUnprocessable();
        $this->postJson(route('office.clearance.bulk-status'), ['student_ids' => ['S-1'], 'status' => 'Approved'])
            ->assertUnprocessable();
        $this->get(route('office.clearance.requests'))->assertOk()->assertSee('Not completed');
        $this->actingAs($student, 'student')->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [4, 5],
        ])->assertRedirect();
        $this->get(route('student.clearance-updates'))->assertOk()->assertSee('Guidance Evaluation: Completed');
        $this->actingAs($this->staff('guidance'), 'office')->get(route('office.clearance.requests'))
            ->assertOk()->assertSee('Completed');
        $this->postJson(route('office.clearance.bulk-status'), ['student_ids' => ['S-1'], 'status' => 'Approved'])
            ->assertOk();
        $this->assertDatabaseHas('office_clearance_status', [
            'student_id' => 'S-1', 'office_role' => 'guidance office', 'status' => 'Approved',
        ]);
    }

    public function test_guidance_edits_reset_only_guidance_answers_and_reject_stale_submissions(): void
    {
        $id = $this->guidanceForm();
        $student = $this->student();
        $libraryId = DB::table('library_evaluations')->insertGetId([
            'title' => 'Library survey', 'questions' => json_encode(['Library question.']),
            'created_by' => 'LIB-1', 'singleton' => 1, 'revision' => 1,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('library_evaluation_responses')->insert([
            'evaluation_id' => $libraryId, 'student_id' => 'S-1', 'ratings' => '[5]', 'completed_at' => now(),
        ]);
        $this->actingAs($student, 'student')->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [4, 5],
        ])->assertRedirect();
        $data = ['revision' => 1, 'title' => 'Guidance Evaluation',
            'description' => 'Please rate guidance services.',
            'sections' => [['title' => 'Support', 'description' => 'Consider this semester.',
                'questions' => ['Staff listen carefully.', 'I received useful guidance.']]]];
        $this->actingAs($this->staff('guidance'), 'office')
            ->put(route('office.guidance-evaluations.update', $id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guidance_evaluation_responses', 1);
        $data['sections'][0]['description'] = 'Consider all of your visits.';
        $this->put(route('office.guidance-evaluations.update', $id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guidance_evaluation_responses', 0);
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $this->assertSame(2, (int) LibraryEvaluation::current('guidance')->revision);
        $this->assertFalse(LibraryEvaluation::submissionAllowed('S-1', 'guidance'));
        $this->assertTrue(LibraryEvaluation::submissionAllowed('S-1'));
        $this->actingAs($student, 'student')->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect(route('student.guidance-evaluation.index'));
        $this->assertDatabaseCount('guidance_evaluation_responses', 0);
        $this->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 2, 'ratings' => [5, 5],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guidance_evaluation_responses', 1);
    }

    public function test_guidance_delete_clears_its_answers_and_allows_a_new_form(): void
    {
        $id = $this->guidanceForm();
        $student = $this->student();
        $this->actingAs($student, 'student')->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect();
        $this->actingAs($this->staff('guidance'), 'office')
            ->delete(route('office.guidance-evaluations.destroy', $id), ['revision' => 1])
            ->assertRedirect(route('office.guidance-evaluations.index'));
        $this->assertDatabaseCount('guidance_evaluations', 0);
        $this->assertDatabaseCount('guidance_evaluation_responses', 0);
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'guidance office'));
        $this->post(route('office.guidance-evaluations.store'), [
            'title' => 'New Guidance Evaluation', 'questions' => ['The counseling was helpful.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame($id, LibraryEvaluation::form('guidance')->id);
    }

    public function test_guidance_role_access_and_student_deletion_keep_offices_separate(): void
    {
        $id = $this->guidanceForm();
        $student = $this->student();
        $this->actingAs($student, 'student')->post(route('student.guidance-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [4, 4],
        ])->assertRedirect();
        $this->actingAs($this->staff('library'), 'office')
            ->get(route('office.guidance-evaluations.index'))->assertForbidden();
        $this->get(route('office.guidance-evaluations.export', $id))->assertForbidden();
        $this->postJson(route('office.guidance-evaluations.store'), [
            'title' => 'Forged', 'questions' => ['Question.'],
        ])->assertForbidden();
        $this->actingAs($this->staff('guidance'), 'office')
            ->get(route('office.library-evaluations.index'))->assertForbidden();
        RecordPurge::student('S-1');
        $this->assertDatabaseCount('guidance_evaluation_responses', 0);
        $this->assertDatabaseCount('guidance_evaluations', 1);
    }

    private function staff(string $role): AdminPersonnel
    {
        $office = new AdminPersonnel(['personnel_id' => strtoupper($role).'-1', 'firstname' => 'Office',
            'lastname' => 'Staff', 'role' => $role, 'office' => ucfirst($role)]);
        $office->id = 1;

        return $office;
    }

    private function student(string $id = 'S-1'): StudentAccount
    {
        return StudentAccount::create(['student_id' => $id, 'firstname' => 'Student', 'lastname' => $id,
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A']);
    }

    private function guidanceForm(): int
    {
        return DB::table('guidance_evaluations')->insertGetId([
            'title' => 'Guidance Evaluation', 'description' => 'Please rate guidance services.',
            'questions' => json_encode(['Staff listen carefully.', 'I received useful guidance.']),
            'sections' => json_encode([['title' => 'Support', 'description' => 'Consider this semester.', 'count' => 2]]),
            'singleton' => 1, 'revision' => 1, 'created_by' => 'GUIDANCE-1',
            'published_at' => now()->format('Y-m-d H:i:s.u'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
