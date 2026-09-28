<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\ClearanceUpdatesController;
use App\Models\AdminPersonnel;
use App\Models\StudentAccount;
use App\Support\ClearanceWorkflow;
use App\Support\LibraryEvaluation;
use App\Support\RecordPurge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UploadFixtures;
use Tests\TestCase;

class LibraryEvaluationTest extends TestCase
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
    }

    public function test_librarian_can_create_edit_preview_and_publish_a_form(): void
    {
        $this->actingAs($this->librarian(), 'office');
        $this->get(route('office.library-evaluations.create'))->assertOk()->assertSee('Strongly Agree');
        $this->post(route('office.library-evaluations.store'), [
            'title' => 'Library Survey', 'description' => 'Rate our service.',
            'questions' => "Staff are helpful.\n\nResources support my studies.", 'created_by' => 'FORGED',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $evaluation = DB::table('library_evaluations')->first();
        $this->assertSame('LIB-1', $evaluation->created_by);
        $this->assertNull($evaluation->published_at);
        $this->assertCount(2, LibraryEvaluation::questions($evaluation));
        $this->get(route('office.library-evaluations.edit', $evaluation->id))->assertOk();
        $this->put(route('office.library-evaluations.update', $evaluation->id), [
            'revision' => 1, 'title' => 'Updated Library Survey', 'questions' => "Staff are helpful.\nThe library is comfortable.",
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertSee('Publish evaluation');
        $this->post(route('office.library-evaluations.publish', $evaluation->id), ['revision' => 2])->assertRedirect();
        $this->assertSame($evaluation->id, LibraryEvaluation::current()->id);
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertSee('Student completion');
        $this->get(route('office.library-evaluations.edit', $evaluation->id))->assertOk()->assertSee('Saving changes clears all responses');
        $this->post(route('office.library-evaluations.publish', $evaluation->id), ['revision' => 2])->assertStatus(409);
        $this->assertDatabaseHas('library_evaluations', ['id' => $evaluation->id, 'title' => 'Updated Library Survey']);
    }

    public function test_editor_places_rating_scale_above_separate_question_fields_and_saves_them(): void
    {
        $this->actingAs($this->librarian(), 'office');
        $create = $this->get(route('office.library-evaluations.create'))->assertOk();
        $markup = $create->getContent();
        $this->assertTrue(strpos($markup, 'Student rating scale') < strpos($markup, 'Question sets'));
        $this->assertStringContainsString('data-add-question', $markup);

        $this->post(route('office.library-evaluations.store'), [
            'title' => 'Library Survey',
            'questions' => ['Staff are helpful.', 'Library materials are accessible.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $evaluation = DB::table('library_evaluations')->first();
        $this->assertSame(['Staff are helpful.', 'Library materials are accessible.'], LibraryEvaluation::questions($evaluation));

        $edit = $this->get(route('office.library-evaluations.edit', $evaluation->id))->assertOk();
        $edit->assertSee('id="evaluation-question-0"', false)->assertSee('id="evaluation-question-1"', false);
        $edit->assertSee('Staff are helpful.')->assertSee('Library materials are accessible.');
    }

    public function test_evaluation_form_opens_from_a_compact_button_for_drafts_and_published_forms(): void
    {
        $id = $this->evaluation(false);
        $this->actingAs($this->librarian(), 'office');
        $draft = $this->get(route('office.library-evaluations.index'))->assertOk()
            ->assertSee('data-evaluation-form', false)
            ->assertSee('aria-controls="evaluationResponseViewer"', false)
            ->assertSee('id="evaluation-form-preview"', false)
            ->assertSee('Publish evaluation')
            ->assertSee('Edit evaluation')
            ->assertSee('Delete evaluation');
        $this->assertSame(1, substr_count($draft->getContent(), 'data-evaluation-form aria-haspopup'));
        $this->assertTrue(strpos($draft->getContent(), '<template id="evaluation-form-preview">') < strpos($draft->getContent(), 'Staff are helpful.'));
        $this->post(route('office.library-evaluations.publish', $id), ['revision' => 1])->assertRedirect();
        $this->get(route('office.library-evaluations.index'))->assertOk()
            ->assertSee('data-evaluation-form', false)
            ->assertSee('id="evaluationResponseViewer"', false)
            ->assertSee('Download responses CSV')
            ->assertDontSee('Publish evaluation');
    }

    public function test_empty_question_field_is_rejected_and_other_answers_remain_in_the_editor(): void
    {
        $this->actingAs($this->librarian(), 'office');
        $this->followingRedirects()->from(route('office.library-evaluations.create'))
            ->post(route('office.library-evaluations.store'), [
                'title' => 'Library Survey',
                'questions' => ['Staff are helpful.', ''],
            ])->assertOk()->assertSee('Enter a statement for every question.')
            ->assertSee('Staff are helpful.')->assertSee('Question 2');
        $this->assertDatabaseCount('library_evaluations', 0);
    }

    public function test_other_office_roles_cannot_manage_forms_or_read_responses(): void
    {
        $id = $this->evaluation();
        foreach (array_diff(array_keys(AdminPersonnel::$validRoles), ['library']) as $role) {
            $this->actingAs($this->librarian($role), 'office');
            $this->get(route('office.library-evaluations.index'))->assertForbidden();
            $this->get(route('office.library-evaluations.create'))->assertForbidden();
            $this->get(route('office.library-evaluations.edit', $id))->assertForbidden();
            $this->post(route('office.library-evaluations.store'), [])->assertForbidden();
            $this->put(route('office.library-evaluations.update', $id), [])->assertForbidden();
            $this->post(route('office.library-evaluations.publish', $id))->assertForbidden();
            $this->get(route('office.library-evaluations.response', 1))->assertForbidden();
            $this->delete(route('office.library-evaluations.destroy', $id))->assertForbidden();
            $this->get(route('office.library-evaluations.export', $id))->assertForbidden();
        }
        $this->assertDatabaseCount('library_evaluations', 1);
    }

    public function test_guests_and_students_cannot_access_librarian_management(): void
    {
        $this->get(route('office.library-evaluations.index'))->assertRedirect(route('office.login'));
        $this->get(route('office.library-evaluations.export', 1))->assertRedirect(route('office.login'));
        $this->delete(route('office.library-evaluations.destroy', 1))->assertRedirect(route('office.login'));
        $this->get(route('student.library-evaluation.index'))->assertRedirect(route('student.login'));
        $this->post(route('student.library-evaluation.store'), [])->assertRedirect(route('student.login'));
        $this->actingAs($this->student(), 'student')
            ->get(route('office.library-evaluations.index'))->assertRedirect(route('office.login'));
        $this->get(route('office.library-evaluations.export', 1))->assertRedirect(route('office.login'));
        $this->delete(route('office.library-evaluations.destroy', 1))->assertRedirect(route('office.login'));
    }

    public function test_incomplete_evaluation_blocks_requests_uploads_and_student_ui(): void
    {
        Storage::fake('local');
        $this->evaluation();
        $student = $this->student();
        $this->actingAs($student, 'student');
        $this->postJson(route('student.clearance.submit-office'), ['office_role' => 'Library'])
            ->assertUnprocessable()->assertJsonValidationErrors('office_role');
        $this->postJson(route('student.clearance.upload-office'), [
            'office_role' => 'library',
            'submission_file' => UploadedFile::fake()->createWithContent('proof.pdf', UploadFixtures::pdf()),
        ])->assertUnprocessable()->assertJsonValidationErrors('office_role');
        $this->assertDatabaseCount('office_clearance_status', 0);
        $this->assertDatabaseCount('office_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertFalse($this->libraryItem($student)['can_submit']);
        $this->get(route('student.clearance-updates'))->assertOk()->assertSee('Answer evaluation')
            ->assertSee('Evaluation: Not completed');
    }

    public function test_valid_response_is_bound_to_student_preserved_on_retry_and_unlocks_clearance(): void
    {
        Storage::fake('local');
        $id = $this->evaluation();
        $student = $this->student();
        $this->student('S-2');
        $this->actingAs($student, 'student');
        $this->get(route('student.library-evaluation.index'))->assertOk()->assertSee('Strongly Agree')->assertSee('Strongly Disagree');
        $this->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 1], 'student_id' => 'S-2',
        ])->assertRedirect(route('student.clearance-updates'))->assertSessionHasNoErrors();
        $response = LibraryEvaluation::response($id, 'S-1');
        $this->assertSame([5, 1], json_decode($response->ratings, true));
        $this->assertNull(LibraryEvaluation::response($id, 'S-2'));
        $this->post(route('student.library-evaluation.store'), ['evaluation_id' => $id, 'revision' => 1, 'ratings' => [2, 2]])->assertRedirect();
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $this->assertSame($response->ratings, LibraryEvaluation::response($id, 'S-1')->ratings);
        $this->assertTrue($this->libraryItem($student)['can_submit']);
        $this->get(route('student.clearance-updates'))->assertOk()->assertSee('Evaluation: Completed');
        $this->get(route('student.library-evaluation.index'))->assertOk()->assertSee('Evaluation completed')->assertDontSee('Submit evaluation');
        $this->post(route('student.clearance.submit-office'), ['office_role' => 'Library'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-1', 'office_role' => 'library', 'status' => 'Pending']);
        $this->post(route('student.clearance.upload-office'), [
            'office_role' => 'library',
            'submission_file' => UploadedFile::fake()->createWithContent('proof.pdf', UploadFixtures::pdf()),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('office_submissions', ['student_id' => 'S-1', 'office' => 'library']);
    }

    public function test_missing_extra_invalid_and_misnumbered_ratings_are_rejected(): void
    {
        $id = $this->evaluation();
        $this->actingAs($this->student(), 'student');
        foreach ([[], [5], [5, 4, 3], [0, 5], [6, 5], [4.5, 5], ['agree', 5], [1 => 5, 2 => 4], [[5], 4]] as $ratings) {
            $this->postJson(route('student.library-evaluation.store'), ['evaluation_id' => $id, 'revision' => 1, 'ratings' => $ratings])
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('library_evaluation_responses', 0);
    }

    public function test_student_validation_errors_are_visible_after_redirect(): void
    {
        $id = $this->evaluation();
        $this->actingAs($this->student(), 'student');
        $page = $this->followingRedirects()->from(route('student.library-evaluation.index'))
            ->post(route('student.library-evaluation.store'), ['evaluation_id' => $id, 'revision' => 1, 'ratings' => [5]])->assertOk();
        $this->assertTrue(str_contains($page->getContent(), 'Please answer every question on this evaluation.'));
        $this->postJson(route('student.library-evaluation.store'), ['evaluation_id' => [$id], 'ratings' => [5, 5]])
            ->assertUnprocessable()->assertJsonValidationErrors('evaluation_id');
        $this->assertDatabaseCount('library_evaluation_responses', 0);
    }

    public function test_editing_published_form_deletes_responses_and_requires_new_answers(): void
    {
        $id = $this->evaluation();
        $firstStudent = $this->student();
        $secondStudent = $this->student('S-2');
        foreach ([$firstStudent, $secondStudent] as $student) {
            $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
                'evaluation_id' => $id, 'revision' => 1, 'ratings' => [4, 3],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $oldResponse = LibraryEvaluation::response($id, 'S-1')->id;
        $this->requestFor('S-1');
        $this->requestFor('S-2');
        DB::table('office_clearance_status')->where('student_id', 'S-2')->update(['status' => 'Approved']);

        $this->actingAs($this->librarian(), 'office')->put(route('office.library-evaluations.update', $id), [
            'revision' => 1, 'title' => 'Updated survey', 'description' => 'Please rate our service.',
            'questions' => ['The collection supports my studies.', 'The library is comfortable.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluations', 1);
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        $this->assertSame(2, (int) LibraryEvaluation::current()->revision);
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-2', 'status' => 'Approved']);
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertSee('0 completed')->assertSee('2 not completed');
        $this->get(route('office.library-evaluations.response', $oldResponse))->assertNotFound();
        $this->postJson(route('office.clearance.status'), ['student_id' => 'S-1', 'status' => 'Approved'])->assertUnprocessable();

        $this->actingAs($firstStudent, 'student');
        $this->assertFalse(LibraryEvaluation::submissionAllowed('S-1'));
        $this->assertFalse($this->libraryItem($firstStudent)['evaluation_completed']);
        $this->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect(route('student.library-evaluation.index'));
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        $this->get(route('student.library-evaluation.index'))->assertOk()->assertSee('The collection supports my studies.');

        $this->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 2, 'ratings' => [5, 5],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(LibraryEvaluation::submissionAllowed('S-1'));
        $this->assertFalse(LibraryEvaluation::submissionAllowed('S-2'));
    }

    public function test_only_one_form_can_be_created_whether_draft_or_published(): void
    {
        $id = $this->evaluation(false);
        $this->actingAs($this->librarian(), 'office');
        $this->get(route('office.library-evaluations.create'))->assertRedirect(route('office.library-evaluations.edit', $id));
        $this->postJson(route('office.library-evaluations.store'), [
            'title' => 'Second form', 'questions' => ['Another question.'],
        ])->assertUnprocessable()->assertJsonValidationErrors('evaluation');
        $this->post(route('office.library-evaluations.publish', $id), ['revision' => 1])->assertRedirect();
        $this->postJson(route('office.library-evaluations.store'), [
            'title' => 'Second form', 'questions' => ['Another question.'],
        ])->assertUnprocessable();
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertDontSee('href="'.route('office.library-evaluations.create').'"', false)
            ->assertSee('Edit evaluation')->assertSee('Delete evaluation')->assertSee('Download responses CSV');
        $this->assertDatabaseCount('library_evaluations', 1);
    }

    public function test_draft_is_not_required_and_other_offices_are_unaffected(): void
    {
        $draft = $this->evaluation(false);
        $student = $this->student();
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'library'));
        $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $draft, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect(route('student.library-evaluation.index'));
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        DB::table('library_evaluations')->where('id', $draft)->update(['published_at' => now()]);
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'property custodian'));
        $this->post(route('student.clearance.submit-office'), ['office_role' => 'property custodian'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_librarian_sees_completion_and_individual_answers_before_student_requests_clearance(): void
    {
        $id = $this->evaluation();
        $this->actingAs($this->student(), 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 4],
        ])->assertRedirect();
        $this->student('S-2');
        $this->actingAs($this->librarian(), 'office');
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertSee('S-1')->assertSee('S-2')
            ->assertSee('1 completed')->assertSee('1 not completed');
        $this->get(route('office.library-evaluations.index', ['completion' => 'completed']))
            ->assertOk()->assertSee('S-1')->assertDontSee('S-2');
        $this->get(route('office.library-evaluations.index', ['completion' => 'pending']))
            ->assertOk()->assertSee('S-2')->assertDontSee('S-1');
        $this->get(route('office.library-evaluations.response', LibraryEvaluation::response($id, 'S-1')->id))
            ->assertOk()->assertSee('S-1')->assertSee('Strongly Agree');
        $this->requestFor('S-1');
        $this->requestFor('S-2');
        $this->get(route('office.clearance.requests'))->assertOk()->assertSee('Completed')->assertSee('Not completed');
    }

    public function test_librarian_response_viewer_loads_answers_inside_a_modal(): void
    {
        $id = $this->evaluation();
        $this->actingAs($this->student(), 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 1],
        ])->assertRedirect();
        $this->actingAs($this->librarian(), 'office');
        $this->get(route('office.library-evaluations.index'))->assertOk()
            ->assertSee('evaluationResponseViewer')->assertSee('View response');

        $url = route('office.library-evaluations.response', LibraryEvaluation::response($id, 'S-1')->id);
        $this->get($url)->assertOk()->assertSee('Back to student completion');
        $fragment = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get($url)
            ->assertOk()->assertSee('Strongly Agree')->assertSee('Strongly Disagree')
            ->assertDontSee('Back to student completion');
        $this->assertStringNotContainsString('<html', $fragment->getContent());
    }

    public function test_single_and_bulk_approval_require_evaluation_but_pending_is_allowed(): void
    {
        $id = $this->evaluation();
        $student = $this->student();
        $this->requestFor('S-1');
        $this->actingAs($this->librarian(), 'office');
        $this->postJson(route('office.clearance.status'), ['student_id' => 'S-1', 'status' => 'Approved'])->assertUnprocessable();
        $this->postJson(route('office.clearance.bulk-status'), ['student_ids' => ['S-1'], 'status' => 'Approved'])->assertUnprocessable();
        $this->post(route('office.clearance.status'), ['student_id' => 'S-1', 'status' => 'Pending'])->assertRedirect();
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-1', 'status' => 'Pending']);
        $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), ['evaluation_id' => $id, 'revision' => 1, 'ratings' => [1, 1]])->assertRedirect();
        $this->actingAs($this->librarian(), 'office')->postJson(route('office.clearance.bulk-status'), [
            'student_ids' => ['S-1'], 'status' => 'Approved',
        ])->assertOk();
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-1', 'status' => 'Approved']);
    }

    public function test_invalid_form_is_not_saved_and_errors_are_visible(): void
    {
        $this->actingAs($this->librarian(), 'office');
        $page = $this->followingRedirects()->from(route('office.library-evaluations.create'))->post(route('office.library-evaluations.store'), [
            'title' => 'Library', 'questions' => str_repeat('Long statement. ', 50),
        ])->assertOk();
        $this->assertTrue(str_contains($page->getContent(), '500 characters or fewer'), 'The validation message should be visible in the form.');
        $this->postJson(route('office.library-evaluations.store'), ['title' => 'Library', 'questions' => " \n "])->assertUnprocessable();
        $this->assertDatabaseCount('library_evaluations', 0);
    }

    public function test_missing_tables_preserve_existing_clearance_and_show_setup_message(): void
    {
        Schema::drop('library_evaluation_responses');
        Schema::drop('library_evaluations');
        $student = $this->student();
        $this->assertTrue(ClearanceWorkflow::prerequisitesMet($student, 'library'));
        $this->actingAs($this->librarian(), 'office')->get(route('office.library-evaluations.index'))
            ->assertOk()->assertSee('contact the system administrator');
        $this->actingAs($student, 'student')->get(route('student.library-evaluation.index'))->assertOk()->assertSee('No library evaluation');
    }

    public function test_missing_response_table_does_not_bypass_an_existing_published_form(): void
    {
        $this->evaluation();
        Schema::drop('library_evaluation_responses');
        $this->assertFalse(ClearanceWorkflow::prerequisitesMet($this->student(), 'library'));
    }

    public function test_deleting_student_cleans_only_their_evaluation_responses(): void
    {
        $id = $this->evaluation();
        foreach (['S-1', 'S-2'] as $studentId) {
            $this->actingAs($this->student($studentId), 'student')->post(route('student.library-evaluation.store'), [
                'evaluation_id' => $id, 'revision' => 1, 'ratings' => [3, 3],
            ])->assertRedirect();
        }
        RecordPurge::student('S-1');
        $this->assertDatabaseMissing('library_evaluation_responses', ['student_id' => 'S-1']);
        $this->assertNotNull(LibraryEvaluation::response($id, 'S-2'));
        $this->assertDatabaseCount('library_evaluations', 1);
    }

    public function test_only_actual_changes_clear_answers_and_stale_edits_cannot_delete_new_answers(): void
    {
        $id = $this->evaluation();
        $student = $this->student();
        $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 4],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $data = ['revision' => 1, 'title' => 'Library Services Evaluation',
            'description' => 'Please rate our service.', 'questions' => ['Staff are helpful.', 'Resources support my studies.']];
        $this->actingAs($this->librarian(), 'office')->put(route('office.library-evaluations.update', $id), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $this->assertSame(1, (int) LibraryEvaluation::current()->revision);
        $this->putJson(route('office.library-evaluations.update', $id), array_replace($data, ['questions' => ['']]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('library_evaluation_responses', 1);

        foreach ([['title' => 'Revised title'], ['description' => 'Revised instructions'],
            ['questions' => ['Resources support my studies.', 'Staff are helpful.']]] as $change) {
            $data = array_replace($data, $change);
            $this->actingAs($this->librarian(), 'office')->put(route('office.library-evaluations.update', $id), $data)
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseCount('library_evaluation_responses', 0);
            $data['revision']++;
            $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
                'evaluation_id' => $id, 'revision' => $data['revision'], 'ratings' => [3, 2],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->actingAs($this->librarian(), 'office')->putJson(route('office.library-evaluations.update', $id),
            array_replace($data, ['revision' => 1, 'title' => 'Stale edit']))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->deleteJson(route('office.library-evaluations.destroy', $id), ['revision' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $this->assertDatabaseHas('library_evaluations', ['id' => $id, 'title' => 'Revised title', 'revision' => 4]);
    }

    public function test_deleting_form_removes_its_responses_and_allows_a_new_form(): void
    {
        $id = $this->evaluation();
        $student = $this->student();
        $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect();
        $this->actingAs($this->librarian(), 'office')->delete(route('office.library-evaluations.destroy', $id), ['revision' => 1])
            ->assertRedirect(route('office.library-evaluations.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        $this->assertDatabaseCount('library_evaluations', 0);
        $this->assertNull(LibraryEvaluation::current());
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertSee('Create evaluation');
        $this->get(route('office.library-evaluations.export', $id))->assertNotFound();
        $this->post(route('office.library-evaluations.store'), [
            'title' => 'New evaluation', 'questions' => ['The library supports my studies.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $newId = LibraryEvaluation::form()->id;
        $this->assertNotSame($id, $newId);
        $this->post(route('office.library-evaluations.publish', $newId), ['revision' => 1])->assertRedirect();
        $this->actingAs($student, 'student')->post(route('student.library-evaluation.store'), [
            'evaluation_id' => $id, 'revision' => 1, 'ratings' => [5, 5],
        ])->assertRedirect(route('student.library-evaluation.index'));
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        $this->assertFalse(LibraryEvaluation::submissionAllowed('S-1'));
    }

    public function test_csv_download_contains_all_answers_and_handles_spreadsheet_text(): void
    {
        $id = $this->evaluation();
        DB::table('library_evaluations')->where('id', $id)->update([
            'title' => '=SUM(1,2)', 'questions' => json_encode(["Staff are \"helpful\", and welcoming.\nPlease rate.", 'Resources support my studies.']),
        ]);
        for ($number = 1; $number <= 17; $number++) {
            $student = $this->student('S-'.$number);
            if ($number === 1) {
                $student->update(['firstname' => '=1+1', 'lastname' => 'Peña, "Library"']);
            }
            DB::table('library_evaluation_responses')->insert([
                'evaluation_id' => $id, 'student_id' => $student->student_id,
                'ratings' => json_encode([5, 1]), 'completed_at' => now(),
            ]);
        }
        $this->student('UNANSWERED');
        $response = $this->actingAs($this->librarian(), 'office')
            ->get(route('office.library-evaluations.export', ['evaluation' => $id, 'page' => 2, 'completion' => 'pending']))
            ->assertOk()->assertDownload('library-evaluation-responses-'.now()->format('Y-m-d').'.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = $this->csvRows($csv);
        $this->assertCount(18, $rows);
        $this->assertCount(9, $rows[0]);
        $this->assertSame('Timestamp', $rows[0][0]);
        $this->assertSame("Q1. Staff are \"helpful\", and welcoming.\nPlease rate.", $rows[0][7]);
        $this->assertSame('S-1', $rows[1][1]);
        $this->assertSame("'=1+1 Peña, \"Library\"", $rows[1][2]);
        $this->assertSame("'=SUM(1,2)", $rows[1][6]);
        $this->assertSame('5 - Strongly Agree', $rows[1][7]);
        $this->assertSame('1 - Strongly Disagree', $rows[1][8]);
        $this->assertNotContains('UNANSWERED', array_column($rows, 1));
    }

    public function test_csv_with_no_responses_still_has_question_headers(): void
    {
        $id = $this->evaluation();
        $response = $this->actingAs($this->librarian(), 'office')->get(route('office.library-evaluations.export', $id))->assertOk();
        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(1, $rows);
        $this->assertSame('Q2. Resources support my studies.', $rows[0][8]);
    }

    public function test_single_form_upgrade_preserves_existing_answers_and_enforces_unique_slot(): void
    {
        $id = $this->evaluation();
        $this->student();
        DB::table('library_evaluation_responses')->insert([
            'evaluation_id' => $id, 'student_id' => 'S-1', 'ratings' => '[5,4]', 'completed_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_09_21_000002_make_library_evaluation_single_form.php');
        $migration->down();
        DB::table('library_evaluations')->insert([
            'title' => 'Older form', 'questions' => '["Old question"]', 'created_by' => 'LIB-1',
            'published_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration->up();
        $this->assertSame($id, LibraryEvaluation::current()->id);
        $this->assertNotNull(LibraryEvaluation::response($id, 'S-1'));
        $this->assertDatabaseCount('library_evaluations', 2);
        $this->assertSame(1, DB::table('library_evaluations')->where('singleton', 1)->count());
        $this->expectException(UniqueConstraintViolationException::class);
        $this->evaluation();
    }

    public function test_question_sets_keep_instructions_and_answers_together_and_instruction_changes_reset_responses(): void
    {
        $data = ['title' => 'Library experience', 'description' => 'Rate each statement.', 'sections' => [
            ['title' => 'Staff services', 'description' => 'Consider your visits this semester.', 'questions' => ['Staff are helpful.', 'Service is prompt.']],
            ['title' => 'Study spaces', 'description' => 'Consider the reading areas.', 'questions' => ['The reading area is comfortable.']],
        ]];
        $office = $this->librarian();
        $this->actingAs($office, 'office')->post(route('office.library-evaluations.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $evaluation = LibraryEvaluation::form();
        $this->assertSame(['Staff are helpful.', 'Service is prompt.', 'The reading area is comfortable.'], LibraryEvaluation::questions($evaluation));
        $this->assertSame([2 => 'The reading area is comfortable.'], LibraryEvaluation::sections($evaluation)[1]['questions']);
        $this->get(route('office.library-evaluations.edit', $evaluation->id))->assertOk()
            ->assertSee('Consider your visits this semester.')->assertSee('sections[1][questions][0]', false);
        $this->post(route('office.library-evaluations.publish', $evaluation->id), ['revision' => 1])->assertRedirect();
        $student = $this->student();
        $this->actingAs($student, 'student')->get(route('student.library-evaluation.index'))->assertOk()
            ->assertSeeInOrder(['Staff services', 'Consider your visits this semester.', 'Staff are helpful.', 'Service is prompt.',
                'Study spaces', 'Consider the reading areas.', 'The reading area is comfortable.'])
            ->assertSee('name="ratings[2]"', false);
        $this->post(route('student.library-evaluation.store'), ['evaluation_id' => $evaluation->id, 'revision' => 1, 'ratings' => [5, 4, 1]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $responseId = LibraryEvaluation::response($evaluation->id, 'S-1')->id;
        $this->actingAs($office, 'office')->get(route('office.library-evaluations.response', $responseId), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSeeInOrder(['Staff services', 'Consider your visits this semester.', 'Staff are helpful.', 'Strongly Agree',
                'Service is prompt.', 'Agree', 'Study spaces', 'Consider the reading areas.', 'The reading area is comfortable.', 'Strongly Disagree']);
        $csv = $this->get(route('office.library-evaluations.export', $evaluation->id))->assertOk();
        $rows = $this->csvRows($csv->streamedContent());
        $this->assertSame('Q3. Study spaces — The reading area is comfortable.', $rows[0][9]);
        $this->assertSame('1 - Strongly Disagree', $rows[1][9]);
        $data['revision'] = 1;
        $this->put(route('office.library-evaluations.update', $evaluation->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $data['sections'][1]['description'] = 'Consider the quiet reading areas only.';
        $this->put(route('office.library-evaluations.update', $evaluation->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluation_responses', 0);
        $this->assertSame(2, (int) LibraryEvaluation::current()->revision);
        $this->assertFalse(LibraryEvaluation::submissionAllowed('S-1'));
        $this->get(route('office.library-evaluations.index'))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['completed'] === 0 && $summary['pending'] === 1 && $summary['average'] === null);
        $this->get(route('office.library-evaluations.response', $responseId))->assertNotFound();
    }

    public function test_existing_unsectioned_forms_keep_answers_when_saved_as_an_unchanged_question_set(): void
    {
        $id = $this->evaluation();
        $this->student();
        DB::table('library_evaluation_responses')->insert(['evaluation_id' => $id, 'student_id' => 'S-1', 'ratings' => '[5,4]', 'completed_at' => now()]);
        $this->actingAs($this->librarian(), 'office')->put(route('office.library-evaluations.update', $id), [
            'revision' => 1, 'title' => 'Library Services Evaluation', 'description' => 'Please rate our service.',
            'sections' => [['title' => '', 'description' => '', 'questions' => ['Staff are helpful.', 'Resources support my studies.']]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('library_evaluation_responses', 1);
        $this->assertSame(1, (int) LibraryEvaluation::current()->revision);
    }

    public function test_question_set_validation_enforces_global_limits_and_preserves_input(): void
    {
        $this->actingAs($this->librarian(), 'office');
        $section = ['title' => 'Services', 'description' => 'Think about this semester.', 'questions' => ['Staff are helpful.']];
        $this->postJson(route('office.library-evaluations.store'), ['title' => 'Library', 'sections' => array_fill(0, 11, $section)])
            ->assertUnprocessable()->assertJsonValidationErrors('sections');
        $largeSection = array_replace($section, ['questions' => array_fill(0, 26, 'A library statement.')]);
        $this->postJson(route('office.library-evaluations.store'), ['title' => 'Library', 'sections' => [$largeSection, $largeSection]])
            ->assertUnprocessable()->assertJsonValidationErrors('questions');
        $this->postJson(route('office.library-evaluations.store'), ['title' => 'Library', 'sections' => [array_replace($section, ['description' => str_repeat('x', 3001)])]])
            ->assertUnprocessable()->assertJsonValidationErrors('sections.0.description');
        $this->followingRedirects()->from(route('office.library-evaluations.create'))->post(route('office.library-evaluations.store'), [
            'title' => 'Library', 'sections' => [array_replace($section, ['questions' => ['Staff are helpful.', '']])],
        ])->assertOk()->assertSee('Think about this semester.')->assertSee('Staff are helpful.')->assertSee('Question 2');
        $this->assertDatabaseCount('library_evaluations', 0);
    }

    public function test_summary_charts_use_all_responses_and_students_independent_of_filters(): void
    {
        $id = $this->evaluation();
        foreach (['S-1', 'S-2', 'S-3'] as $studentId) {
            $this->student($studentId);
        }
        $this->student('S-4')->update(['program' => 'BSBA']);
        foreach (['S-1' => [5, 1], 'S-4' => [3, 5]] as $studentId => $ratings) {
            DB::table('library_evaluation_responses')->insert(['evaluation_id' => $id, 'student_id' => $studentId, 'ratings' => json_encode($ratings), 'completed_at' => now()]);
        }
        $page = $this->actingAs($this->librarian(), 'office')->get(route('office.library-evaluations.index', ['completion' => 'pending', 'search' => 'S-2']))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 1)
            ->assertSee('data-evaluation-chart="completion"', false)->assertSee('data-evaluation-chart="ratings"', false)
            ->assertSee('data-evaluation-chart="pending"', false)->assertSee('data-evaluation-chart="averages"', false);
        $summary = $page->viewData('summary');
        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(2, $summary['pending']);
        $this->assertEquals(50, $summary['completionPercent']);
        $this->assertSame(4, $summary['answerCount']);
        $this->assertEquals(3.5, $summary['average']);
        $this->assertSame([5 => 2, 4 => 0, 3 => 1, 2 => 0, 1 => 1], $summary['ratingCounts']);
        $this->assertEquals([4, 3], array_column($summary['questions'], 'average'));
        $this->assertSame([['name' => 'BSBA', 'total' => 1, 'pending' => 0], ['name' => 'BSIT', 'total' => 3, 'pending' => 2]], $summary['programs']);
    }

    public function test_summary_charts_handle_no_students_and_no_responses(): void
    {
        $this->evaluation();
        $this->actingAs($this->librarian(), 'office')->get(route('office.library-evaluations.index'))->assertOk()
            ->assertSee('No students yet')->assertSee('No responses yet')
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 0 && $summary['average'] === null && $summary['completionPercent'] === 0);
        $this->student();
        $this->get(route('office.library-evaluations.index'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && $summary['pending'] === 1 && $summary['average'] === null);
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function librarian(string $role = 'library'): AdminPersonnel
    {
        $office = new AdminPersonnel(['personnel_id' => 'LIB-1', 'firstname' => 'Library', 'lastname' => 'Staff', 'role' => $role, 'office' => 'Library']);
        $office->id = 1;

        return $office;
    }

    private function student(string $id = 'S-1'): StudentAccount
    {
        return StudentAccount::create(['student_id' => $id, 'firstname' => 'Student', 'lastname' => $id,
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A']);
    }

    private function evaluation(bool $published = true): int
    {
        return DB::table('library_evaluations')->insertGetId([
            'title' => 'Library Services Evaluation', 'description' => 'Please rate our service.',
            'questions' => json_encode(['Staff are helpful.', 'Resources support my studies.']),
            'singleton' => 1, 'revision' => 1,
            'created_by' => 'LIB-1', 'published_at' => $published ? now()->format('Y-m-d H:i:s.u') : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function requestFor(string $studentId): void
    {
        DB::table('office_clearance_status')->insert(['student_id' => $studentId, 'office_role' => 'library',
            'approver_id' => $studentId, 'status' => 'Pending', 'updated_at' => now()]);
    }

    private function libraryItem(StudentAccount $student): array
    {
        return collect(app(ClearanceUpdatesController::class)->buildWorkflowData($student)['officeItems'])->firstWhere('key', 'library');
    }
}
