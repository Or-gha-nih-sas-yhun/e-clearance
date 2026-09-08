<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\StudentAccount;
use App\Models\StudentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentRegistryAdminTest extends TestCase
{
    use RefreshDatabase;

    private MainAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = MainAdmin::create([
            'name' => 'Registry Administrator',
            'email' => 'registry@example.test',
            'password' => 'Strong-Admin-Password-123!',
        ]);
    }

    public function test_the_registration_list_page_renders_for_the_main_admin(): void
    {
        StudentRegistry::create([
            'student_id' => '2026-0001',
            'ms_account' => 'jovan@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_INACTIVE,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('student-registry.index'))
            ->assertOk()
            ->assertSee('Student Registration List')
            ->assertSee('2026-0001')
            ->assertSee('jovan@mcc.edu.ph');
    }

    public function test_a_guest_cannot_reach_the_registration_list(): void
    {
        $this->get(route('student-registry.index'))->assertRedirect(route('login'));
    }

    public function test_the_admin_can_add_and_update_an_entry(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('student-registry.store'), [
                'student_id' => '2026-0007',
                'ms_account' => 'newstudent@mcc.edu.ph',
                'status' => 'inactive',
            ])->assertRedirect(route('student-registry.index'));

        $entry = StudentRegistry::where('student_id', '2026-0007')->firstOrFail();
        $this->assertSame('inactive', $entry->status);

        $this->actingAs($this->admin, 'admin')
            ->put(route('student-registry.update', $entry->id), [
                'student_id' => '2026-0007',
                'ms_account' => 'renamed@mcc.edu.ph',
                'status' => 'active',
            ])->assertRedirect(route('student-registry.index'));

        $this->assertDatabaseHas('student_registry', [
            'id' => $entry->id,
            'ms_account' => 'renamed@mcc.edu.ph',
            'status' => 'active',
        ]);
    }

    public function test_duplicate_student_id_or_ms_account_is_rejected(): void
    {
        StudentRegistry::create([
            'student_id' => '2026-0001',
            'ms_account' => 'jovan@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_INACTIVE,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('student-registry.store'), [
                'student_id' => '2026-0001',
                'ms_account' => 'different@mcc.edu.ph',
                'status' => 'inactive',
            ])->assertSessionHasErrors('student_id');

        $this->actingAs($this->admin, 'admin')
            ->post(route('student-registry.store'), [
                'student_id' => '2026-0002',
                'ms_account' => 'jovan@mcc.edu.ph',
                'status' => 'inactive',
            ])->assertSessionHasErrors('ms_account');

        $this->assertSame(1, StudentRegistry::count());
    }

    /** Re-opening an entry whose account still exists would allow a duplicate account. */
    public function test_an_entry_cannot_be_reopened_or_deleted_while_its_student_account_exists(): void
    {
        $entry = StudentRegistry::create([
            'student_id' => '2026-0001',
            'ms_account' => 'jovan@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_ACTIVE,
        ]);

        StudentAccount::create([
            'student_id' => '2026-0001',
            'firstname' => 'Jovan',
            'lastname' => 'Mahusay',
            'email' => 'jovan@mcc.edu.ph',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
            'student_type' => 'Regular',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('student-registry.update', $entry->id), [
                'student_id' => '2026-0001',
                'ms_account' => 'jovan@mcc.edu.ph',
                'status' => 'inactive',
            ]);

        $this->assertDatabaseHas('student_registry', ['id' => $entry->id, 'status' => 'active']);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('student-registry.destroy', $entry->id));

        $this->assertDatabaseHas('student_registry', ['id' => $entry->id]);
    }

    public function test_csv_import_creates_registry_entries_and_skips_duplicates(): void
    {
        StudentRegistry::create([
            'student_id' => '2026-0001',
            'ms_account' => 'existing@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_INACTIVE,
        ]);

        $csv = "student_id,ms_account,status\n"
            ."2026-0002,alpha@mcc.edu.ph,inactive\n"
            ."2026-0003,beta@mcc.edu.ph,active\n"
            ."2026-0001,duplicate@mcc.edu.ph,inactive\n";

        $this->actingAs($this->admin, 'admin')
            ->post(route('import.csv'), [
                'type' => 'student_registry',
                'csv_file' => UploadedFile::fake()->createWithContent('registry.csv', $csv),
            ])->assertRedirect();

        $this->assertDatabaseHas('student_registry', ['student_id' => '2026-0002', 'status' => 'inactive']);
        $this->assertDatabaseHas('student_registry', ['student_id' => '2026-0003', 'status' => 'active']);
        $this->assertSame(3, StudentRegistry::count(), 'The duplicate student ID row should be skipped.');
    }

    public function test_csv_import_rejects_a_malformed_row(): void
    {
        $csv = "student_id,ms_account,status\n"
            ."not-an-id,alpha@mcc.edu.ph,inactive\n"
            ."2026-0004,not-an-email,inactive\n";

        $this->actingAs($this->admin, 'admin')
            ->post(route('import.csv'), [
                'type' => 'student_registry',
                'csv_file' => UploadedFile::fake()->createWithContent('registry.csv', $csv),
            ])->assertRedirect();

        $this->assertSame(0, StudentRegistry::count());
    }
}
