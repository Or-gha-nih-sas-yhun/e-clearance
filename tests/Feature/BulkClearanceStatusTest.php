<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\Registrar;
use App\Models\Treasurer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BulkClearanceStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notifications', 'clearance_status', 'instructor_assignment', 'subject_codes', 'office_clearance_status', 'student_account'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('approver_id')->nullable();
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'office_role']);
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
        Schema::create('subject_codes', function (Blueprint $table) {
            $table->id('subject_id');
            $table->string('subject_code');
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
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['student_id', 'subject_id', 'instructor_id']);
        });

        DB::table('student_account')->insert([
            ['student_id' => 'S-001', 'firstname' => 'Ana', 'lastname' => 'Reyes', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A'],
            ['student_id' => 'S-002', 'firstname' => 'Ben', 'lastname' => 'Santos', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'B'],
        ]);
    }

    public function test_office_bulk_update_changes_only_records_for_the_authenticated_office(): void
    {
        DB::table('office_clearance_status')->insert([
            ['student_id' => 'S-001', 'office_role' => 'library', 'status' => 'Pending'],
            ['student_id' => 'S-002', 'office_role' => 'guidance office', 'status' => 'Pending'],
        ]);
        $office = new AdminPersonnel(['personnel_id' => 'LIB-1', 'office' => 'Library', 'role' => 'library']);
        $office->id = 1;
        $this->actingAs($office, 'office');

        $this->postJson(route('office.clearance.bulk-status'), [
            'student_ids' => ['S-001'],
            'status' => 'Approved',
        ])->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('title', 'Clearances Approved');

        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-001', 'office_role' => 'library', 'status' => 'Approved']);
        $this->actingAs($office, 'office');
        $response = $this->postJson(route('office.clearance.bulk-status'), [
            'student_ids' => ['S-002'],
            'status' => 'Approved',
        ]);
        $this->assertSame(422, $response->status(), $response->getContent());
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-002', 'office_role' => 'guidance office', 'status' => 'Pending']);
    }

    public function test_section_treasurer_bulk_update_respects_program_year_and_section_scope(): void
    {
        DB::table('office_clearance_status')->insert([
            ['student_id' => 'S-001', 'office_role' => 'Section Treasurer', 'status' => 'Pending'],
            ['student_id' => 'S-002', 'office_role' => 'Section Treasurer', 'status' => 'Pending'],
        ]);
        $treasurer = new Treasurer([
            'treasurer_id' => 'TR-1', 'treasurer_type' => 'section', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ]);
        $treasurer->id = 1;
        $this->actingAs($treasurer, 'treasurer');

        $this->postJson(route('treasurer.clearance.bulk-status'), [
            'student_ids' => ['S-001'],
            'status' => 'Approved',
        ])->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('title', 'Clearances Approved');

        $this->actingAs($treasurer, 'treasurer');
        $response = $this->postJson(route('treasurer.clearance.bulk-status'), [
            'student_ids' => ['S-002'],
            'status' => 'Approved',
        ]);
        $this->assertSame(422, $response->status(), $response->getContent());
        $this->assertDatabaseHas('office_clearance_status', ['student_id' => 'S-002', 'status' => 'Pending']);
    }

    public function test_registrar_can_bulk_approve_selected_registrar_records(): void
    {
        DB::table('subject_codes')->insert(['subject_id' => 20, 'subject_code' => 'GE101']);
        DB::table('instructor_assignment')->insert([
            ['instructor_id' => 'INS-2', 'subject_id' => 20, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A'],
            ['instructor_id' => 'INS-2', 'subject_id' => 20, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'B'],
        ]);
        DB::table('clearance_status')->insert([
            ['student_id' => 'S-001', 'subject_id' => 20, 'instructor_id' => 'INS-2', 'status' => 'Approved'],
            ['student_id' => 'S-002', 'subject_id' => 20, 'instructor_id' => 'INS-2', 'status' => 'Approved'],
        ]);

        $officeRows = [];
        $prerequisites = [
            'section treasurer', 'department treasurer', 'property custodian', 'scc adviser',
            'sas director', 'guidance office', 'library', 'dean',
        ];
        foreach (['S-001', 'S-002'] as $studentId) {
            foreach ($prerequisites as $role) {
                $officeRows[] = ['student_id' => $studentId, 'office_role' => $role, 'status' => 'Approved'];
            }
            $officeRows[] = ['student_id' => $studentId, 'office_role' => 'registrar', 'status' => 'Pending'];
        }
        DB::table('office_clearance_status')->insert($officeRows);
        $registrar = new Registrar(['registrar_id' => 'REG-1']);
        $registrar->id = 1;
        $this->actingAs($registrar, 'registrar');

        $this->postJson(route('registrar.student-clearance.bulk-status'), [
            'student_ids' => ['S-001', 'S-002'],
            'status' => 'Approved',
        ])->assertOk()
            ->assertJsonPath('updated', 2)
            ->assertJsonPath('title', 'Clearances Approved');

        $this->assertSame(2, DB::table('office_clearance_status')->where('office_role', 'registrar')->where('status', 'Approved')->count());
    }

    public function test_instructor_bulk_update_rejects_students_outside_assigned_subjects(): void
    {
        DB::table('subject_codes')->insert(['subject_id' => 10, 'subject_code' => 'IT101']);
        DB::table('instructor_assignment')->insert([
            'instructor_id' => 'INS-1', 'subject_id' => 10, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ]);
        $instructor = new Instructor(['instructor_id' => 'INS-1']);
        $instructor->id = 1;
        $this->actingAs($instructor, 'instructor');

        $this->postJson(route('instructor.clearance.bulk'), [
            'items' => [['student' => 'S-001', 'subject' => 10]],
            'status' => 'Approved',
        ])->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('title', 'Clearances Approved');
        $this->assertDatabaseHas('clearance_status', ['student_id' => 'S-001', 'subject_id' => 10, 'status' => 'Approved']);

        $this->actingAs($instructor, 'instructor');
        $response = $this->postJson(route('instructor.clearance.bulk'), [
            'items' => [['student' => 'S-002', 'subject' => 10]],
            'status' => 'Approved',
        ]);
        $this->assertSame(422, $response->status(), $response->getContent());
        $this->assertDatabaseMissing('clearance_status', ['student_id' => 'S-002', 'subject_id' => 10]);
    }

    public function test_bulk_toolbar_contains_select_all_and_both_status_actions(): void
    {
        $html = view('partials.clearance-bulk-toolbar', ['endpoint' => '/clearance/bulk'])->render();

        $this->assertStringContainsString('Approve Selected', $html);
        $this->assertStringContainsString('Set as Pending', $html);
        $this->assertStringContainsString('data-clearance-bulk', $html);
    }
}
