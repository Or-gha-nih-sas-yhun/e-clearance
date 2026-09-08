<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\StudentAccount;
use App\Models\StudentRegistry;
use App\Support\SystemMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private MainAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = MainAdmin::create([
            'name' => 'Maintenance Admin',
            'email' => 'maintenance@example.test',
            'password' => 'Strong-Admin-Password-123!',
        ]);
    }

    public function test_the_settings_page_is_admin_only(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('End-of-Year Promotion')
            ->assertSee('Clear Term Clearance Records');
    }

    public function test_the_page_previews_exactly_what_each_action_would_affect(): void
    {
        $this->student('2026-0001', '1');
        $this->student('2026-0002', '4');
        $this->clearanceRow('2026-0001');

        $this->actingAs($this->admin, 'admin')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('1 row(s)', false)      // one office_clearance_status row
            ->assertSee('2 active student(s) affected');
    }

    public function test_promotion_moves_every_year_up_and_deactivates_fourth_years(): void
    {
        $first = $this->student('2026-0001', '1');
        $third = $this->student('2026-0003', '3');
        $fourth = $this->student('2026-0004', '4');

        StudentRegistry::create([
            'student_id' => '2026-0004',
            'ms_account' => 'grad@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_ACTIVE,
            'registered_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('settings.promote'), ['confirmation' => 'PROMOTE STUDENTS'])
            ->assertRedirect();

        $this->assertSame('2', $first->fresh()->year_level);
        $this->assertSame('4', $third->fresh()->year_level, 'Third years become fourth years.');

        $graduate = $fourth->fresh();
        $this->assertSame('inactive', $graduate->status, 'Fourth years are deactivated, not promoted.');
        $this->assertNotNull($graduate->deactivated_at);

        $this->assertSame('active', $first->fresh()->status);
        $this->assertDatabaseHas('student_registry', [
            'student_id' => '2026-0004',
            'status' => 'inactive',
        ]);
    }

    /**
     * The order matters: promoting 3 -> 4 before deactivating fourth years would
     * sweep the just-promoted third years out along with the real graduates.
     */
    public function test_a_newly_promoted_fourth_year_is_not_deactivated_in_the_same_run(): void
    {
        $third = $this->student('2026-0003', '3');

        SystemMaintenance::promoteStudents();

        $promoted = $third->fresh();
        $this->assertSame('4', $promoted->year_level);
        $this->assertSame('active', $promoted->status);
    }

    public function test_an_inactive_student_is_left_alone_by_promotion(): void
    {
        $student = $this->student('2026-0009', '2');
        DB::table('student_account')->where('student_id', '2026-0009')->update(['status' => 'inactive']);

        SystemMaintenance::promoteStudents();

        $this->assertSame('2', $student->fresh()->year_level, 'Graduated students must not keep advancing.');
    }

    public function test_a_deactivated_student_cannot_sign_in(): void
    {
        $this->student('2026-0001', '4');
        DB::table('student_account')->where('student_id', '2026-0001')->update(['status' => 'inactive']);

        $this->post(route('student.login.submit'), [
            'student_id' => '2026-0001',
            'password' => 'Strong-Password-123!',
        ])->assertSessionHasErrors('student_id');

        $this->assertGuest('student');
    }

    public function test_the_wrong_confirmation_phrase_changes_nothing(): void
    {
        $student = $this->student('2026-0001', '1');
        $this->clearanceRow('2026-0001');

        $this->actingAs($this->admin, 'admin')
            ->post(route('settings.promote'), ['confirmation' => 'promote students'])
            ->assertRedirect();
        $this->assertSame('1', $student->fresh()->year_level, 'Phrase is case-sensitive.');

        $this->actingAs($this->admin, 'admin')
            ->post(route('settings.reset'), ['confirmation' => 'RESET'])
            ->assertRedirect();
        $this->assertSame(1, DB::table('office_clearance_status')->count());
    }

    /** Each action has its own phrase, so one cannot trigger the other. */
    public function test_the_promotion_phrase_does_not_run_the_clearance_reset(): void
    {
        $this->student('2026-0001', '1');
        $this->clearanceRow('2026-0001');

        $this->actingAs($this->admin, 'admin')
            ->post(route('settings.reset'), ['confirmation' => 'PROMOTE STUDENTS'])
            ->assertRedirect();

        $this->assertSame(1, DB::table('office_clearance_status')->count());
    }

    public function test_the_reset_clears_records_and_returns_the_archive(): void
    {
        $this->student('2026-0001', '1');
        $this->clearanceRow('2026-0001');

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('settings.reset'), ['confirmation' => 'RESET CLEARANCE']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('record_type', $csv);
        $this->assertStringContainsString('2026-0001', $csv, 'The archive must contain the deleted row.');

        $this->assertSame(0, DB::table('office_clearance_status')->count());
        $this->assertDatabaseHas('student_account', ['student_id' => '2026-0001'], null);
    }

    public function test_the_archive_download_alone_deletes_nothing(): void
    {
        $this->student('2026-0001', '1');
        $this->clearanceRow('2026-0001');

        $this->actingAs($this->admin, 'admin')
            ->get(route('settings.clearance-archive'))
            ->assertOk();

        $this->assertSame(1, DB::table('office_clearance_status')->count());
    }

    private function student(string $id, string $year): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => strtolower($id).'@example.test',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => $year,
            'section' => 'EAST',
            'student_type' => 'Regular',
        ]);
    }

    private function clearanceRow(string $studentId): void
    {
        DB::table('office_clearance_status')->insert([
            'student_id' => $studentId,
            'office_role' => 'library',
            'approver_id' => $studentId,
            'status' => 'Pending',
        ]);
    }
}
