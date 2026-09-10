<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\StudentAccount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The dashboard must not count clearance rows whose student is gone.
 *
 * Every table is MyISAM, so the `ON DELETE CASCADE` the migrations declare has
 * never fired and a deleted student can leave clearance rows behind. The tiles
 * counted those while the by-program chart beneath them joined `student_account`
 * and did not, so one page reported 22 approved and 11 approved at once.
 */
class AdminDashboardOrphanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Production is MyISAM, which parses the migrations' FOREIGN KEY clauses
        // and then ignores them, which is why stranded rows exist there at all.
        // SQLite enforces them, and `PRAGMA foreign_keys` is a no-op inside the
        // transaction RefreshDatabase opens — so the two clearance tables are
        // rebuilt here without the constraints, matching how they really behave.
        Schema::dropIfExists('clearance_status');
        Schema::create('clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('instructor_id', 50)->nullable();
            $table->string('status', 20)->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::dropIfExists('office_clearance_status');
        Schema::create('office_clearance_status', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 50);
            $table->string('office_role', 50);
            $table->string('approver_id', 50);
            $table->string('status', 20)->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_orphaned_clearance_rows_are_left_out_of_every_dashboard_figure(): void
    {
        $student = $this->student('2026-0001');

        // Two checkpoints that belong to a student who exists.
        $this->subjectClearance('2026-0001', 1, 'Approved');
        $this->officeClearance('2026-0001', 'library', 'Approved');

        // Three left behind by students who were deleted.
        $this->subjectClearance('GONE-1', 2, 'Approved');
        $this->officeClearance('GONE-1', 'guidance office', 'Approved');
        $this->officeClearance('GONE-2', 'library', 'Pending');

        $data = $this->dashboardData();

        $this->assertSame(1 + 1, $data['approved'], 'Only the living student\'s approved checkpoints count.');
        $this->assertSame(0, $data['pending'], 'A deleted student\'s pending checkpoint must not count.');

        // The tiles and the chart under them read the same rows.
        $byProgram = collect($data['statusByProgram']);
        $this->assertSame($data['approved'], (int) $byProgram->sum('approved'));
        $this->assertSame($data['pending'], (int) $byProgram->sum('pending'));

        // The activity charts are drawn from the same rows too.
        $this->assertSame(2, collect($data['monthlyData'])->sum('count'));
        $this->assertSame(2, collect($data['stackData'])->sum('approved'));
        $this->assertSame(0, collect($data['stackData'])->sum('pending'));

        $this->assertSame('2026-0001', $student->student_id);
    }

    public function test_deleting_a_student_drops_their_rows_out_of_the_totals(): void
    {
        $this->student('2026-0002');
        $this->subjectClearance('2026-0002', 1, 'Approved');
        $this->officeClearance('2026-0002', 'library', 'Approved');

        $this->assertSame(2, $this->dashboardData()['approved']);

        // Delete only the account, the way a direct database edit would, leaving
        // the clearance rows stranded.
        StudentAccount::where('student_id', '2026-0002')->delete();

        $this->assertSame(0, $this->dashboardData()['approved']);
        $this->assertSame(2, DB::table('clearance_status')->count() + DB::table('office_clearance_status')->count());
    }

    private function dashboardData(): array
    {
        $admin = MainAdmin::firstOrCreate(
            ['email' => 'admin-dashboard@example.test'],
            ['password' => 'AdminPassword1!'],
        );

        return $this->actingAs($admin, 'admin')
            ->get(route('dashboard'))
            ->assertOk()
            ->original
            ->getData();
    }

    private function student(string $id): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => "student-{$id}@example.test",
            'password' => 'StudentPassword1!',
            'program' => 'BSIT',
            'year_level' => '1',
            'section' => 'A',
        ]);
    }

    private function subjectClearance(string $studentId, int $subjectId, string $status): void
    {
        DB::table('clearance_status')->insert([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'instructor_id' => 'INS-1',
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function officeClearance(string $studentId, string $role, string $status): void
    {
        DB::table('office_clearance_status')->insert([
            'student_id' => $studentId,
            'office_role' => $role,
            'status' => $status,
            'approver_id' => $studentId,
            'updated_at' => now(),
        ]);
    }
}
