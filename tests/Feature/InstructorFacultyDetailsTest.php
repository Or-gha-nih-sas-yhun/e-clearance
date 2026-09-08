<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Support\InstructorDepartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two faculty details the Main Admin instructor CRUD records: whether an
 * instructor is regular or part timer, and which department they belong to now
 * that BSED and BEED are one College of Education.
 */
class InstructorFacultyDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_department_dropdown_offers_the_college_of_education_instead_of_bsed_and_beed(): void
    {
        $content = $this->actingAs($this->admin(), 'admin')->get(route('instructors.index'))->assertOk()->getContent();

        $this->assertStringContainsString('>College of Education</option>', $content);
        $this->assertStringNotContainsString('>BSED</option>', $content);
        $this->assertStringNotContainsString('>BEED</option>', $content);
        $this->assertStringContainsString('>BSIT</option>', $content);
    }

    public function test_the_position_dropdown_offers_regular_and_part_timer(): void
    {
        $content = $this->actingAs($this->admin(), 'admin')->get(route('instructors.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="employment_status"', $content);
        $this->assertStringContainsString('>Regular</option>', $content);
        $this->assertStringContainsString('>Part Timer</option>', $content);
    }

    public function test_an_instructor_is_created_with_a_position_and_the_merged_department(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('instructors.store'), [
                'instructor_id' => '1001',
                'firstname' => 'Grace',
                'lastname' => 'Villanueva',
                'email' => 'grace.villanueva@example.test',
                'department' => InstructorDepartment::COLLEGE_OF_EDUCATION,
                'employment_status' => Instructor::EMPLOYMENT_PART_TIME,
            ])
            ->assertRedirect(route('instructors.index'))
            ->assertSessionHasNoErrors();

        $instructor = Instructor::where('instructor_id', '1001')->sole();

        $this->assertSame(InstructorDepartment::COLLEGE_OF_EDUCATION, $instructor->department);
        $this->assertSame(Instructor::EMPLOYMENT_PART_TIME, $instructor->employment_status);
    }

    public function test_a_position_outside_regular_and_part_timer_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('instructors.store'), [
                'instructor_id' => '1002',
                'firstname' => 'Noel',
                'lastname' => 'Bacus',
                'email' => 'noel.bacus@example.test',
                'department' => 'BSIT',
                'employment_status' => 'Consultant',
            ])
            ->assertSessionHasErrors('employment_status');

        $this->assertDatabaseCount('instructor_account', 0);
    }

    public function test_a_legacy_bsed_row_reads_as_the_college_and_is_found_by_filtering_on_it(): void
    {
        $legacy = $this->instructor('1003', 'BSED');
        $this->instructor('1004', 'BSIT');

        $content = $this->actingAs($this->admin(), 'admin')
            ->get(route('instructors.index', ['department' => InstructorDepartment::COLLEGE_OF_EDUCATION]))
            ->assertOk()
            ->assertSee($legacy->email)
            ->assertDontSee('instructor-1004@example.test')
            ->getContent();

        // The stored 'BSED' is never shown back to the admin as itself.
        $this->assertStringNotContainsString('BSED', $content);
    }

    public function test_saving_a_legacy_row_rewrites_its_department_to_the_college(): void
    {
        $this->instructor('1005', 'BEED');

        $this->actingAs($this->admin(), 'admin')
            ->put(route('instructors.update', '1005'), [
                'firstname' => 'Legacy',
                'lastname' => 'Instructor',
                'email' => 'instructor-1005@example.test',
                'department' => InstructorDepartment::COLLEGE_OF_EDUCATION,
                'employment_status' => Instructor::EMPLOYMENT_REGULAR,
            ])
            ->assertRedirect(route('instructors.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            InstructorDepartment::COLLEGE_OF_EDUCATION,
            Instructor::where('instructor_id', '1005')->sole()->department,
        );
    }

    public function test_the_position_filter_narrows_the_list(): void
    {
        $regular = $this->instructor('1006', 'BSIT', Instructor::EMPLOYMENT_REGULAR);
        $partTimer = $this->instructor('1007', 'BSIT', Instructor::EMPLOYMENT_PART_TIME);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('instructors.index', ['employment' => Instructor::EMPLOYMENT_PART_TIME]))
            ->assertOk()
            ->assertSee($partTimer->email)
            ->assertDontSee($regular->email);
    }

    private function instructor(string $id, string $department, ?string $employmentStatus = null): Instructor
    {
        return Instructor::create([
            'instructor_id' => $id,
            'firstname' => 'Legacy',
            'lastname' => 'Instructor',
            'email' => "instructor-{$id}@example.test",
            'password' => 'InstructorPassword1!',
            'department' => $department,
            'employment_status' => $employmentStatus ?? Instructor::EMPLOYMENT_REGULAR,
        ]);
    }

    private function admin(): MainAdmin
    {
        return MainAdmin::create([
            'email' => 'admin-faculty-details@example.test',
            'password' => 'AdminPassword1!',
        ]);
    }
}
