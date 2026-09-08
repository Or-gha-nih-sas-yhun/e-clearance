<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\MainAdmin;
use App\Models\ProgramSection;
use App\Models\SubjectCode;
use App\Support\InstructorDepartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The subject assignment page: one browse screen where the department is a
 * filter over the whole assignment list, and one screen per instructor.
 */
class AssignmentDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_browse_screen_offers_every_faculty_and_lists_all_assignments(): void
    {
        $bsit = $this->instructor('1001', 'BSIT', 'Ada', 'Lovelace');
        $bshm = $this->instructor('1002', 'BSHM', 'Chef', 'Ramirez');
        $this->assignment($bsit, 'BSIT', '1', 'A');
        $this->assignment($bshm, 'BSHM', '1', 'B');

        $page = $this->page(route('assignments.index'));

        $this->assertStringContainsString('All Departments', $page);
        $this->assertStringContainsString('BSIT', $page);
        $this->assertStringContainsString(InstructorDepartment::COLLEGE_OF_EDUCATION, $page);
        $this->assertStringContainsString('BSBA', $page);
        $this->assertStringContainsString('BSHM', $page);
        $this->assertStringContainsString('All Assignments', $page);

        // Unfiltered, the table holds every assignment in the college.
        $this->assertStringContainsString('<td>Ada Lovelace</td>', $page);
        $this->assertStringContainsString('<td>Chef Ramirez</td>', $page);
    }

    public function test_the_browse_filters_are_instructor_year_and_section_only(): void
    {
        $page = $this->page(route('assignments.index'));

        $this->assertStringContainsString('name="search"', $page);
        $this->assertStringContainsString('name="year_level"', $page);
        $this->assertStringContainsString('name="section"', $page);
        $this->assertStringContainsString('All Years', $page);

        // Program was dropped from the filter bar; it stays on the assignment
        // forms, which live past the point page() cuts.
        $this->assertStringNotContainsString('All Programs', $page);
        $this->assertStringNotContainsString('name="program"', $page);
    }

    public function test_choosing_a_department_filters_the_assignment_table_and_shows_its_instructors(): void
    {
        $bsit = $this->instructor('1003', 'BSIT', 'Ada', 'Lovelace');
        $bshm = $this->instructor('1004', 'BSHM', 'Chef', 'Ramirez');
        $this->assignment($bsit, 'BSIT', '1', 'A');
        $this->assignment($bshm, 'BSHM', '1', 'B');

        $page = $this->page(route('assignments.index', ['department' => 'BSIT']));

        $this->assertStringContainsString('BSIT Assignments', $page);
        $this->assertStringContainsString('<td>Ada Lovelace</td>', $page);
        $this->assertStringContainsString('<strong>Ada Lovelace</strong>', $page);
        $this->assertStringNotContainsString('Ramirez', $page);
    }

    public function test_a_legacy_beed_instructor_is_filtered_under_the_college_of_education(): void
    {
        $this->instructor('1005', 'BEED', 'Rita', 'Lim');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('assignments.index', ['department' => InstructorDepartment::COLLEGE_OF_EDUCATION]))
            ->assertOk()
            ->assertSee('Rita Lim');
    }

    public function test_the_section_filter_still_narrows_the_table(): void
    {
        $instructor = $this->instructor('1006', 'BSIT');
        $this->assignment($instructor, 'BSIT', '1', 'A');
        $this->assignment($instructor, 'BSIT', '1', 'B', 'IT102');

        $page = $this->page(route('assignments.index', ['department' => 'BSIT', 'section' => 'B']));

        $this->assertStringContainsString('<td>IT102', $page);
        $this->assertStringNotContainsString('<td>IT101', $page);
    }

    public function test_searching_an_instructor_narrows_both_the_cards_and_the_table(): void
    {
        $ada = $this->instructor('1007', 'BSIT', 'Ada', 'Lovelace');
        $alan = $this->instructor('1008', 'BSIT', 'Alan', 'Turing');
        $this->assignment($ada, 'BSIT', '1', 'A');
        $this->assignment($alan, 'BSIT', '1', 'B', 'IT102');

        $page = $this->page(route('assignments.index', ['search' => 'turing']));

        $this->assertStringContainsString('<strong>Alan Turing</strong>', $page);
        $this->assertStringContainsString('<td>IT102', $page);
        $this->assertStringNotContainsString('Lovelace', $page);
    }

    public function test_choosing_an_instructor_opens_their_own_assignment_form_and_subjects(): void
    {
        $instructor = $this->instructor('1009', 'BSIT', 'Ada', 'Lovelace');
        $this->assignment($instructor, 'BSIT', '1', 'A');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('assignments.index', ['department' => 'BSIT', 'instructor' => '1009']))
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('Assign a Subject')
            ->assertSee('IT101')
            ->assertSee('name="instructor_id" value="1009"', false);
    }

    public function test_an_instructor_outside_the_named_department_falls_back_to_the_browse_screen(): void
    {
        $this->instructor('1010', 'BSHM', 'Chef', 'Ramirez');

        // Asking for a BSHM instructor under BSIT must not claim they are BSIT.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('assignments.index', ['department' => 'BSIT', 'instructor' => '1010']))
            ->assertOk()
            ->assertSee('BSIT Assignments')
            ->assertDontSee('Assign a Subject');
    }

    public function test_saving_an_assignment_returns_to_the_screen_it_was_submitted_from(): void
    {
        $instructor = $this->instructor('1011', 'BSIT');
        $subject = $this->subject();
        $this->section('BSIT', '1', 'A');
        $origin = route('assignments.index', ['department' => 'BSIT', 'instructor' => '1011']);

        $this->actingAs($this->admin(), 'admin')
            ->from($origin)
            ->post(route('assignments.store'), [
                'instructor_id' => $instructor->instructor_id,
                'subject_id' => $subject->subject_id,
                'program' => 'BSIT',
                'year_level' => 1,
                'sections' => ['A'],
            ])
            ->assertRedirect($origin)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instructor_assignment', ['instructor_id' => '1011', 'section' => 'A']);
    }

    public function test_deleting_keeps_the_filters_that_were_applied(): void
    {
        $instructor = $this->instructor('1012', 'BSIT');
        $assignment = $this->assignment($instructor, 'BSIT', '1', 'A');
        $origin = route('assignments.index', ['department' => 'BSIT', 'section' => 'A']);

        $this->actingAs($this->admin(), 'admin')
            ->from($origin)
            ->delete(route('assignments.destroy', ['id' => $assignment->assignment_id]))
            ->assertRedirect($origin);

        $this->assertDatabaseCount('instructor_assignment', 0);
    }

    /**
     * What the admin actually sees, with the edit dialog and the scripts after
     * it cut away — those always carry every instructor and every subject, so
     * asserting over the whole response could never tell a filter apart.
     */
    private function page(string $url): string
    {
        $content = $this->actingAs($this->admin(), 'admin')->get($url)->assertOk()->getContent();
        $modal = strpos($content, 'id="editModal"');

        return $modal === false ? $content : substr($content, 0, $modal);
    }

    private function instructor(string $id, string $department, string $first = 'Test', string $last = 'Instructor'): Instructor
    {
        return Instructor::create([
            'instructor_id' => $id,
            'firstname' => $first,
            'lastname' => $last,
            'email' => "instructor-{$id}@example.test",
            'password' => 'InstructorPassword1!',
            'department' => $department,
            'employment_status' => Instructor::EMPLOYMENT_REGULAR,
        ]);
    }

    private function subject(string $code = 'IT101'): SubjectCode
    {
        return SubjectCode::firstOrCreate(
            ['subject_code' => $code],
            ['subject_description' => 'Intro to Computing', 'year_level' => '1', 'program' => 'BSIT', 'semester' => '1st Semester'],
        );
    }

    private function section(string $program, string $year, string $section): ProgramSection
    {
        return ProgramSection::firstOrCreate(['program' => $program, 'year_level' => $year, 'section' => $section]);
    }

    private function assignment(Instructor $instructor, string $program, string $year, string $section, string $code = 'IT101'): InstructorAssignment
    {
        $this->section($program, $year, $section);

        return InstructorAssignment::create([
            'instructor_id' => $instructor->instructor_id,
            'subject_id' => $this->subject($code)->subject_id,
            'program' => $program,
            'year_level' => $year,
            'section' => $section,
        ]);
    }

    private function admin(): MainAdmin
    {
        return MainAdmin::create([
            'email' => 'admin-assignments@example.test',
            'password' => 'AdminPassword1!',
        ]);
    }
}
