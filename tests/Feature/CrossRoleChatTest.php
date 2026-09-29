<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\ChatMessage;
use App\Models\Instructor;
use App\Models\Registrar;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Chat now spans five portals. What matters is not only that a message can be
 * sent, but that each staff portal reaches exactly the students it is scoped to:
 * a section treasurer must not reach another section, and a program head must not
 * reach another program.
 */
class CrossRoleChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'chat_messages', 'notifications', 'irregular_enrollment', 'instructor_assignment', 'subject_codes',
            'treasurers', 'registrar', 'admin_personnel', 'instructor_account', 'student_account',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('suffix')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
            $table->string('student_type')->default('Regular');
        });
        Schema::create('subject_codes', function (Blueprint $table) {
            $table->bigIncrements('subject_id');
            $table->string('subject_code');
            $table->string('subject_description')->nullable();
            $table->string('year_level')->nullable();
            $table->string('program')->nullable();
            $table->string('semester')->nullable();
        });
        Schema::create('instructor_account', function (Blueprint $table) {
            $table->id();
            $table->string('instructor_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('department')->nullable();
        });
        Schema::create('admin_personnel', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('office')->nullable();
            $table->string('role');
        });
        Schema::create('treasurers', function (Blueprint $table) {
            $table->id();
            $table->string('treasurer_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('treasurer_type');
            $table->string('department')->nullable();
            $table->string('program')->nullable();
            $table->string('year_level')->nullable();
            $table->string('section')->nullable();
            $table->timestamps();
        });
        Schema::create('registrar', function (Blueprint $table) {
            $table->id();
            $table->string('registrar_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
        });
        Schema::create('instructor_assignment', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->string('instructor_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        Schema::create('irregular_enrollment', function (Blueprint $table) {
            $table->id();
            $table->string('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('instructor_id');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('recipient_role')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('notif_type')->nullable();
            $table->string('link_url')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id', 50);
            $table->string('sender_role', 20)->default('student');
            $table->string('receiver_id', 50);
            $table->string('receiver_role', 20)->default('instructor');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_student_contact_list_spans_every_staff_portal(): void
    {
        $student = $this->student();
        $this->officePersonnel('library');
        $this->treasurer('section', ['program' => 'BSIT', 'year_level' => '3', 'section' => 'A']);
        $this->registrar();
        $this->instructorTeaching('BSIT', '3', 'A');

        $response = $this->actingAs($student, 'student')->get(route('student.chat-support'));

        $response->assertOk();
        $contacts = $response->viewData('contacts');

        $this->assertEqualsCanonicalizing(
            ['instructor', 'office', 'treasurer', 'registrar'],
            $contacts->pluck('role')->unique()->all(),
        );
    }

    public function test_student_can_message_an_office_and_the_office_sees_the_thread(): void
    {
        $student = $this->student();
        $office = $this->officePersonnel('library');

        $this->actingAs($student, 'student')
            ->postJson(route('student.chat.send'), [
                'receiver_id' => $office->personnel_id,
                'partner_role' => 'office',
                'message' => 'Is my library clearance cleared?',
            ])->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'sender_role' => 'student',
            'sender_id' => $student->student_id,
            'receiver_role' => 'office',
            'receiver_id' => $office->personnel_id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_role' => 'office',
            'user_id' => $office->personnel_id,
        ]);

        $messages = $this->actingAs($office, 'office')
            ->getJson(route('office.chat.messages', ['with' => $student->student_id]))
            ->assertOk()->json();

        $this->assertCount(1, $messages);
        $this->assertSame('Is my library clearance cleared?', $messages[0]['message']);
        $this->assertFalse($messages[0]['mine']);
        $this->assertSame(1, ChatMessage::where('receiver_role', 'office')->where('is_read', 1)->count());
    }

    public function test_office_reply_reaches_the_student(): void
    {
        $student = $this->student();
        $office = $this->officePersonnel('guidance');

        $this->actingAs($office, 'office')
            ->postJson(route('office.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'Please submit the signed form.',
            ])->assertOk();

        $messages = $this->actingAs($student, 'student')
            ->getJson(route('student.chat.messages', ['with' => $office->personnel_id, 'partner_role' => 'office']))
            ->assertOk()->json();

        $this->assertCount(1, $messages);
        $this->assertSame('Please submit the signed form.', $messages[0]['message']);
        $this->assertDatabaseHas('notifications', [
            'recipient_role' => 'student',
            'user_id' => $student->student_id,
        ]);
    }

    public function test_users_can_delete_only_messages_they_sent(): void
    {
        $student = $this->student();
        $office = $this->officePersonnel('guidance');

        $studentMessage = ChatMessage::create([
            'sender_id' => $student->student_id,
            'sender_role' => 'student',
            'receiver_id' => $office->personnel_id,
            'receiver_role' => 'office',
            'message' => 'I will remove this question.',
        ]);
        $officeMessage = ChatMessage::create([
            'sender_id' => $office->personnel_id,
            'sender_role' => 'office',
            'receiver_id' => $student->student_id,
            'receiver_role' => 'student',
            'message' => 'Only the office may remove this reply.',
        ]);

        $this->actingAs($student, 'student')
            ->deleteJson(route('student.chat.destroy', $studentMessage->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('chat_messages', ['id' => $studentMessage->id]);

        $officeThread = $this->actingAs($office, 'office')
            ->getJson(route('office.chat.messages', ['with' => $student->student_id, 'sync' => 1]))
            ->assertOk()
            ->json();
        $this->assertSame([$officeMessage->id], collect($officeThread)->pluck('id')->all());

        $this->actingAs($student, 'student')
            ->deleteJson(route('student.chat.destroy', $officeMessage->id))
            ->assertNotFound();

        $this->actingAs($office, 'office')
            ->deleteJson(route('office.chat.destroy', $officeMessage->id))
            ->assertOk();

        $this->assertDatabaseMissing('chat_messages', ['id' => $officeMessage->id]);
    }

    public function test_student_and_treasurer_of_the_same_section_can_converse(): void
    {
        $student = $this->student();
        $treasurer = $this->treasurer('section', ['program' => 'BSIT', 'year_level' => '3', 'section' => 'A']);

        $this->actingAs($student, 'student')
            ->postJson(route('student.chat.send'), [
                'receiver_id' => $treasurer->treasurer_id,
                'partner_role' => 'treasurer',
                'message' => 'What is my remaining balance?',
            ])->assertOk();

        $this->actingAs($treasurer, 'treasurer')
            ->postJson(route('treasurer.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'You have no outstanding balance.',
            ])->assertOk();

        $this->assertSame(2, ChatMessage::count());
    }

    public function test_registrar_can_message_any_student(): void
    {
        $student = $this->student();
        $registrar = $this->registrar();

        $this->actingAs($registrar, 'registrar')
            ->postJson(route('registrar.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'Your clearance is ready for release.',
            ])->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'sender_role' => 'registrar',
            'receiver_role' => 'student',
            'receiver_id' => $student->student_id,
        ]);
    }

    public function test_treasurer_cannot_message_a_student_from_another_section(): void
    {
        $student = $this->student();
        $treasurer = $this->treasurer('section', ['program' => 'BSIT', 'year_level' => '3', 'section' => 'B']);

        $this->actingAs($treasurer, 'treasurer')
            ->postJson(route('treasurer.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'Out of scope.',
            ])->assertForbidden();

        $this->actingAs($student, 'student')
            ->postJson(route('student.chat.send'), [
                'receiver_id' => $treasurer->treasurer_id,
                'partner_role' => 'treasurer',
                'message' => 'Out of scope.',
            ])->assertForbidden();

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_program_head_cannot_message_a_student_from_another_program(): void
    {
        $student = $this->student();
        $otherProgramHead = $this->officePersonnel('program_head_bsba');

        $this->actingAs($otherProgramHead, 'office')
            ->postJson(route('office.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'Out of scope.',
            ])->assertForbidden();

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_an_irregular_student_reaches_the_instructor_enrolled_for_them(): void
    {
        $student = $this->student(['student_type' => 'Irregular']);
        // The instructor teaches a different section, so only the per-student
        // irregular enrollment can connect the two.
        $instructor = $this->instructorTeaching('BSIT', '4', 'Z', 'INS-IRREG');
        DB::table('irregular_enrollment')->insert([
            'student_id' => $student->student_id,
            'subject_id' => 1,
            'instructor_id' => 'INS-IRREG',
        ]);

        $contacts = $this->actingAs($student, 'student')
            ->get(route('student.chat-support'))->assertOk()->viewData('contacts');

        $this->assertContains('INS-IRREG', $contacts->where('role', 'instructor')->pluck('id')->all());

        $this->actingAs($instructor, 'instructor')
            ->postJson(route('instructor.chat.send'), [
                'receiver_id' => $student->student_id,
                'message' => 'Your make-up requirement is recorded.',
            ])->assertOk();
    }

    public function test_contact_list_excludes_out_of_scope_staff(): void
    {
        $student = $this->student();
        $this->instructorTeaching('BSBA', '1', 'Z', 'INS-OTHER');
        $this->treasurer('section', ['program' => 'BSIT', 'year_level' => '3', 'section' => 'B']);
        $this->officePersonnel('program_head_bsba');

        $contacts = $this->actingAs($student, 'student')
            ->get(route('student.chat-support'))->assertOk()->viewData('contacts');

        $this->assertSame([], $contacts->pluck('id')->all());
    }

    public function test_student_cannot_message_an_unassigned_instructor(): void
    {
        $student = $this->student();
        $stranger = $this->instructorTeaching('BSBA', '1', 'Z', 'INS-STRANGER');

        $this->actingAs($student, 'student')
            ->postJson(route('student.chat.send'), [
                'receiver_id' => $stranger->instructor_id,
                'partner_role' => 'instructor',
                'message' => 'Hello?',
            ])->assertForbidden();
    }

    public function test_student_cannot_smuggle_a_main_admin_into_a_conversation(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'student')
            ->postJson(route('student.chat.send'), [
                'receiver_id' => '1',
                'partner_role' => 'admin',
                'message' => 'Hello?',
            ])->assertStatus(422);
    }

    public function test_every_staff_messenger_page_renders_with_its_own_endpoints(): void
    {
        $this->student();

        $pages = [
            ['instructor', $this->instructorTeaching('BSIT', '3', 'A'), 'instructor.chat', 'instructor/chat/messages'],
            ['office', $this->officePersonnel('library'), 'office.chat', 'office/chat/messages'],
            ['treasurer', $this->treasurer('department', ['department' => 'BSIT']), 'treasurer.chat', 'treasurer/chat/messages'],
            ['registrar', $this->registrar(), 'registrar.chat', 'registrar/chat/messages'],
        ];

        foreach ($pages as [$guard, $account, $route, $endpoint]) {
            $response = $this->actingAs($account, $guard)->get(route($route));

            $response->assertOk();
            $response->assertSee('Ana Cruz');
            $response->assertSee($endpoint);
            $response->assertSee('data-role="student"', false);
            // The shared component pushes its own polling script; without it the
            // page would render a messenger that never loads a thread.
            $response->assertSee('messenger.dataset.messagesUrl', false);
            $response->assertSee('data-messenger-composer', false);
            $response->assertSee('data-delete-url', false);
            $response->assertSee('Delete this message');
        }
    }

    public function test_staff_contact_list_is_limited_to_assigned_students(): void
    {
        $this->student();
        $this->student(['student_id' => '2023-9999', 'section' => 'B', 'email' => 'other@example.com']);
        $treasurer = $this->treasurer('section', ['program' => 'BSIT', 'year_level' => '3', 'section' => 'A']);

        $contacts = $this->actingAs($treasurer, 'treasurer')
            ->get(route('treasurer.chat'))->assertOk()->viewData('contacts');

        $this->assertSame(['2023-0001'], $contacts->pluck('id')->all());
    }

    public function test_an_instructor_is_labelled_by_the_subjects_they_teach_this_student(): void
    {
        $student = $this->student();
        $instructor = $this->instructorTeaching('BSIT', '3', 'A');

        $contacts = app(\App\Support\ChatDirectory::class)->staffContactsFor($student, 'instructor');
        $contact = $contacts->firstWhere('id', (string) $instructor->instructor_id);

        $this->assertNotNull($contact, 'The teaching instructor must be reachable.');
        $this->assertSame(
            'Intro to Computing',
            $contact->title,
            'The label under an instructor name is the subject they handle, not their department.',
        );
    }

    public function test_a_student_is_labelled_by_year_level_and_section(): void
    {
        $student = $this->student();
        $instructor = $this->instructorTeaching('BSIT', '3', 'A');

        $contacts = app(\App\Support\ChatDirectory::class)->studentContactsFor('instructor', $instructor);
        $contact = $contacts->firstWhere('id', (string) $student->student_id);

        $this->assertNotNull($contact);
        $this->assertSame('Year 3 · Section A', $contact->title);
        $this->assertContains((string) $student->student_id, $contact->meta, 'The ID stays visible as a chip.');
    }

    private function student(array $overrides = []): StudentAccount
    {
        return StudentAccount::create(array_merge([
            'student_id' => '2023-0001',
            'firstname' => 'Ana',
            'lastname' => 'Cruz',
            'email' => 'ana@example.com',
            'password' => 'secret',
            'program' => 'BSIT',
            'year_level' => '3',
            'section' => 'A',
        ], $overrides));
    }

    private function officePersonnel(string $role): AdminPersonnel
    {
        return AdminPersonnel::create([
            'personnel_id' => 'AP-'.strtoupper($role),
            'firstname' => 'Office',
            'lastname' => ucfirst($role),
            'email' => $role.'@example.com',
            'password' => 'secret',
            'office' => '',
            'role' => $role,
        ]);
    }

    private function treasurer(string $type, array $scope): Treasurer
    {
        return Treasurer::create(array_merge([
            'treasurer_id' => 'TR-'.strtoupper($type).'-'.($scope['section'] ?? $scope['department'] ?? 'X'),
            'firstname' => 'Tina',
            'lastname' => 'Treasury',
            'email' => uniqid('treasurer').'@example.com',
            'password' => 'secret',
            'treasurer_type' => $type,
        ], $scope));
    }

    private function registrar(): Registrar
    {
        return Registrar::create([
            'registrar_id' => 'RG-0001',
            'firstname' => 'Rita',
            'lastname' => 'Registrar',
            'email' => 'rita@example.com',
            'password' => 'secret',
            'role' => 'registrar',
        ]);
    }

    private function instructorTeaching(string $program, string $year, string $section, string $id = 'INS-0001'): Instructor
    {
        $instructor = Instructor::create([
            'instructor_id' => $id,
            'firstname' => 'Ivan',
            'lastname' => 'Instructor',
            'email' => $id.'@example.com',
            'password' => 'secret',
            'department' => 'Computer Studies',
        ]);

        DB::table('subject_codes')->updateOrInsert(
            ['subject_id' => 1],
            ['subject_code' => 'IT101', 'subject_description' => 'Intro to Computing',
             'year_level' => $year, 'program' => $program, 'semester' => '1st Semester'],
        );

        DB::table('instructor_assignment')->insert([
            'instructor_id' => $id,
            'subject_id' => 1,
            'program' => $program,
            'year_level' => $year,
            'section' => $section,
        ]);

        return $instructor;
    }
}
