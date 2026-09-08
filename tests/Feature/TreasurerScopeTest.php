<?php

namespace Tests\Feature;

use App\Models\StudentAccount;
use App\Models\Treasurer;
use App\Support\ClearanceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The two treasurer scoping rules, stated plainly:
 *
 * - A department treasurer acts only on students of their own program — the
 *   BSIT department treasurer signs BSIT students and nobody else.
 * - A section treasurer acts only on their exact program + year + section — the
 *   4-EAST treasurer signs 4-EAST students, not 4-WEST and not 3-EAST.
 */
class TreasurerScopeTest extends TestCase
{
    use RefreshDatabase;

    private ClearanceAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->access = new ClearanceAccess();
    }

    public function test_a_department_treasurer_reviews_only_their_own_program(): void
    {
        $treasurer = $this->departmentTreasurer('BSIT');
        $ownProgram = $this->student('2026-0001', 'BSIT', '4', 'EAST');
        $otherProgram = $this->student('2026-0002', 'BSHM', '4', 'EAST');

        $this->requestFor($ownProgram, 'department treasurer');
        $this->requestFor($otherProgram, 'department treasurer');

        $this->assertTrue($this->access->treasurerCanReview($treasurer, $ownProgram));
        $this->assertFalse(
            $this->access->treasurerCanReview($treasurer, $otherProgram),
            'A BSIT department treasurer must not reach a BSHM student.',
        );

        $this->assertSame(['2026-0001'], $this->scopedStudentIds($treasurer));
    }

    public function test_a_section_treasurer_reviews_only_their_own_year_and_section(): void
    {
        $treasurer = $this->sectionTreasurer('BSIT', '4', 'EAST');

        $sameSection = $this->student('2026-0001', 'BSIT', '4', 'EAST');
        $otherSection = $this->student('2026-0002', 'BSIT', '4', 'WEST');
        $otherYear = $this->student('2026-0003', 'BSIT', '3', 'EAST');
        $otherProgram = $this->student('2026-0004', 'BSHM', '4', 'EAST');

        foreach ([$sameSection, $otherSection, $otherYear, $otherProgram] as $student) {
            $this->requestFor($student, 'section treasurer');
        }

        $this->assertTrue($this->access->treasurerCanReview($treasurer, $sameSection));
        $this->assertFalse($this->access->treasurerCanReview($treasurer, $otherSection), 'Wrong section.');
        $this->assertFalse($this->access->treasurerCanReview($treasurer, $otherYear), 'Wrong year level.');
        $this->assertFalse($this->access->treasurerCanReview($treasurer, $otherProgram), 'Wrong program.');

        $this->assertSame(['2026-0001'], $this->scopedStudentIds($treasurer));
    }

    /**
     * Section names are free text on the admin form, so the same section is
     * stored as "SOUTHEAST" by the dropdowns and "South East" by hand. Matching
     * on the raw string silently scoped that treasurer away from their own
     * students, with no error to explain the empty list.
     */
    public function test_section_spelling_differences_do_not_break_the_scope(): void
    {
        $treasurer = $this->sectionTreasurer('BSIT', '4', 'South East');
        $student = $this->student('2026-0001', 'BSIT', '4', 'SOUTHEAST');
        $this->requestFor($student, 'section treasurer');

        $this->assertTrue(
            $this->access->treasurerCanReview($treasurer, $student),
            'A "South East" treasurer must still reach a "SOUTHEAST" student.',
        );
        $this->assertSame(['2026-0001'], $this->scopedStudentIds($treasurer));
    }

    public function test_a_treasurer_cannot_act_before_the_student_submits(): void
    {
        $treasurer = $this->departmentTreasurer('BSIT');
        $student = $this->student('2026-0001', 'BSIT', '4', 'EAST');

        // In scope, but no office_clearance_status row exists yet.
        $this->assertFalse($this->access->treasurerCanReview($treasurer, $student));
    }

    /** @return list<string> */
    private function scopedStudentIds(Treasurer $treasurer): array
    {
        $query = DB::table('student_account');
        $this->access->scopeTreasurerStudents($query, $treasurer);

        return $query->orderBy('student_id')->pluck('student_id')->all();
    }

    private function departmentTreasurer(string $department): Treasurer
    {
        return Treasurer::create([
            'treasurer_id' => 'TR-DEPT-'.$department,
            'firstname' => 'Dept',
            'lastname' => 'Treasurer',
            'email' => strtolower($department).'-dept@example.test',
            'password' => 'Strong-Password-123!',
            'treasurer_type' => 'department',
            'department' => $department,
        ]);
    }

    private function sectionTreasurer(string $program, string $year, string $section): Treasurer
    {
        return Treasurer::create([
            'treasurer_id' => 'TR-SECT-1',
            'firstname' => 'Section',
            'lastname' => 'Treasurer',
            'email' => 'section-treasurer@example.test',
            'password' => 'Strong-Password-123!',
            'treasurer_type' => 'section',
            'program' => $program,
            'year_level' => $year,
            'section' => $section,
        ]);
    }

    private function student(string $id, string $program, string $year, string $section): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $id,
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => strtolower($id).'@example.test',
            'password' => 'Strong-Password-123!',
            'program' => $program,
            'year_level' => $year,
            'section' => $section,
            'student_type' => 'Regular',
        ]);
    }

    private function requestFor(StudentAccount $student, string $officeRole): void
    {
        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => $officeRole,
            'approver_id' => $student->student_id,
            'status' => 'Pending',
        ]);
    }
}
