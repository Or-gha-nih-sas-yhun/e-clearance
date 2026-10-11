<?php

namespace Tests\Feature;

use App\Models\Registrar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrarReapprovalTest extends TestCase
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
            'student_id' => 'S-001', 'firstname' => 'Ana', 'lastname' => 'Reyes',
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ]);
        DB::table('subject_codes')->insert(['subject_id' => 20, 'subject_code' => 'GE101']);
        DB::table('instructor_assignment')->insert([
            'instructor_id' => 'INS-2', 'subject_id' => 20, 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ]);
        DB::table('clearance_status')->insert([
            'student_id' => 'S-001', 'subject_id' => 20, 'instructor_id' => 'INS-2', 'status' => 'Approved',
        ]);

        $rows = [];
        foreach ([
            'section treasurer', 'department treasurer', 'property custodian', 'scc adviser',
            'sas director', 'guidance office', 'library', 'dean',
        ] as $role) {
            $rows[] = ['student_id' => 'S-001', 'office_role' => $role, 'status' => 'Approved'];
        }
        $rows[] = ['student_id' => 'S-001', 'office_role' => 'registrar', 'status' => 'Pending'];
        DB::table('office_clearance_status')->insert($rows);
    }

    private function actAsRegistrar(): void
    {
        // The email matters: the portal header renders @section('user-label', $registrar->full_name
        // ?? $registrar->email), and Blade opens an unclosed output buffer when that value is null.
        $registrar = new Registrar(['registrar_id' => 'REG-1', 'email' => 'registrar@mcc.test']);
        $registrar->id = 1;
        $this->actingAs($registrar, 'registrar');
    }

    private function setStatus(string $status)
    {
        $this->actAsRegistrar();

        return $this->from(route('registrar.student-clearance'))
            ->post(route('registrar.student-clearance.status'), [
                'student_id' => 'S-001',
                'status' => $status,
            ]);
    }

    private function registrarStatus(): ?string
    {
        return DB::table('office_clearance_status')
            ->where('student_id', 'S-001')->where('office_role', 'registrar')
            ->value('status');
    }

    public function test_blocked_reapproval_tells_the_registrar_why(): void
    {
        // A student whose earlier offices are not all cleared: the exact state of a
        // record approved before the prerequisite rule existed.
        DB::table('office_clearance_status')
            ->where('student_id', 'S-001')->where('office_role', 'library')
            ->update(['status' => 'Pending']);
        DB::table('office_clearance_status')
            ->where('student_id', 'S-001')->where('office_role', 'registrar')
            ->update(['status' => 'Approved']);

        $this->setStatus('Pending');
        $this->assertSame('Pending', $this->registrarStatus(), 'revert to pending failed');

        $response = $this->setStatus('Approved');
        $this->assertSame('Pending', $this->registrarStatus(), 'the approval should still be refused');
        $response->assertSessionHasErrors('status');

        // The refusal has to reach the screen, not just the session.
        $this->actAsRegistrar();
        $html = $this->get(route('registrar.student-clearance'))->assertOk()->getContent();

        $this->assertStringContainsString('requires every earlier clearance', $html);
        $this->assertStringContainsString("DOMContentLoaded', () => window.showFeedbackModal({", $html);
        $this->assertStringContainsString('Approval Blocked', $html);
    }

    public function test_blocked_approval_still_returns_422_for_json_callers(): void
    {
        DB::table('office_clearance_status')
            ->where('student_id', 'S-001')->where('office_role', 'library')
            ->update(['status' => 'Pending']);

        $this->actAsRegistrar();

        $this->postJson(route('registrar.student-clearance.status'), [
            'student_id' => 'S-001',
            'status' => 'Approved',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_approve_then_pending_then_approve_again(): void
    {
        $firstApproval = $this->setStatus('Approved');
        $firstApproval->assertSessionHas('flash', fn (array $flash) => $flash['title'] === 'Clearance Approved');
        $this->assertSame('Approved', $this->registrarStatus(), 'first approve failed');

        $pending = $this->setStatus('Pending');
        $pending->assertSessionHas('flash', fn (array $flash) => $flash['title'] === 'Clearance Set to Pending');
        $this->assertSame('Pending', $this->registrarStatus(), 'revert to pending failed');

        $response = $this->setStatus('Approved');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('flash', fn (array $flash) => $flash['title'] === 'Clearance Approved');
        $this->assertSame('Approved', $this->registrarStatus(), 'RE-APPROVE FAILED');
    }
}
