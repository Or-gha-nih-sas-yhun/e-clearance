<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Models\ProgramSection;
use App\Models\SubjectCode;
use App\Support\AcademicTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The active term: which semester and academic year the college is running.
 *
 * Its one functional effect is on subject assignment — only the active
 * semester's subjects can be assigned — so the tests here care about the
 * dropdown, the server-side gate, and the fallback when no term is set.
 */
class AcademicTermTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AcademicTerm::forget();
    }

    public function test_the_settings_page_offers_the_three_semesters_and_an_academic_year(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Active Term')
            ->assertSee('1st Semester')
            ->assertSee('2nd Semester')
            ->assertSee('Summer')
            ->assertSee('name="academic_year"', false);
    }

    public function test_the_term_is_saved_and_shown_back(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('settings.term'), ['semester' => '2nd Semester', 'academic_year' => '2026 - 2027'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        AcademicTerm::forget();
        $this->assertSame('2nd Semester', AcademicTerm::semester());
        // Spacing is normalised on the way in.
        $this->assertSame('2026-2027', AcademicTerm::academicYear());
        $this->assertSame('2nd Semester · A.Y. 2026-2027', AcademicTerm::label());
    }

    public function test_a_bogus_semester_or_malformed_year_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('settings.term'), ['semester' => 'Third Semester'])
            ->assertSessionHasErrors('semester');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('settings.term'), ['semester' => '1st Semester', 'academic_year' => 'next year'])
            ->assertSessionHasErrors('academic_year');

        AcademicTerm::forget();
        $this->assertNull(AcademicTerm::semester());
    }

    public function test_the_assignment_page_offers_only_the_active_semesters_subjects(): void
    {
        $this->instructor('INS-1');
        $first = $this->subject('IT101', '1st Semester');
        $second = $this->subject('IT201', '2nd Semester');
        $this->assign('INS-1', $first);
        $this->assign('INS-1', $second);

        $this->setTerm('1st Semester');

        $content = $this->actingAs($this->admin(), 'admin')
            ->get(route('assignments.index', ['department' => 'BSIT', 'instructor' => 'INS-1']))
            ->assertOk()
            ->getContent();

        // The dropdown is built client-side from this blob, so the semester has
        // to be on it and the active one has to be named.
        $this->assertStringContainsString('const activeSemester = "1st Semester"', $content);
        $this->assertStringContainsString('1st Semester subjects', $content);
    }

    public function test_assigning_a_subject_from_another_semester_is_refused(): void
    {
        $this->instructor('INS-1');
        $offTerm = $this->subject('IT201', '2nd Semester');
        $this->assign('INS-1', $offTerm);
        $this->section('BSIT', '1', 'A');

        $this->setTerm('1st Semester');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('assignments.store'), [
                'instructor_id' => 'INS-1',
                'subject_id' => $offTerm,
                'program' => 'BSIT',
                'year_level' => 1,
                'sections' => ['A'],
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('instructor_assignment', 1);
    }

    public function test_a_subject_in_the_active_semester_is_accepted(): void
    {
        $this->instructor('INS-1');
        $subject = $this->subject('IT101', '1st Semester');
        $this->assign('INS-1', $subject);
        $this->section('BSIT', '1', 'A');

        $this->setTerm('1st Semester');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('assignments.store'), [
                'instructor_id' => 'INS-1',
                'subject_id' => $subject,
                'program' => 'BSIT',
                'year_level' => 1,
                'sections' => ['A'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instructor_assignment', [
            'instructor_id' => 'INS-1', 'subject_id' => $subject, 'section' => 'A',
        ]);
    }

    public function test_an_existing_assignment_from_another_term_can_still_be_edited(): void
    {
        $this->instructor('INS-1');
        $offTerm = $this->subject('IT201', '2nd Semester');
        $this->assign('INS-1', $offTerm);
        $this->section('BSIT', '1', 'A');
        $this->section('BSIT', '1', 'B');

        $assignment = DB::table('instructor_assignment')->insertGetId([
            'instructor_id' => 'INS-1', 'subject_id' => $offTerm,
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
        ], 'assignment_id');

        $this->setTerm('1st Semester');

        // Creating with that subject is blocked, but correcting the section of a
        // row that already exists must not be a dead end.
        $this->actingAs($this->admin(), 'admin')
            ->put(route('assignments.update', ['id' => $assignment]), [
                'instructor_id' => 'INS-1',
                'subject_id' => $offTerm,
                'program' => 'BSIT',
                'year_level' => 1,
                'sections' => ['B'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('instructor_assignment', ['subject_id' => $offTerm, 'section' => 'B']);
    }

    public function test_with_no_term_set_every_subject_stays_assignable(): void
    {
        $this->instructor('INS-1');
        $subject = $this->subject('IT201', '2nd Semester');
        $this->assign('INS-1', $subject);
        $this->section('BSIT', '1', 'A');

        $this->assertFalse(AcademicTerm::filtersSubjects());

        $this->actingAs($this->admin(), 'admin')
            ->post(route('assignments.store'), [
                'instructor_id' => 'INS-1',
                'subject_id' => $subject,
                'program' => 'BSIT',
                'year_level' => 1,
                'sections' => ['A'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('instructor_assignment', 2);
    }

    public function test_an_irregular_students_picker_follows_the_active_semester(): void
    {
        $student = \App\Models\StudentAccount::create([
            'student_id' => '2026-0001', 'firstname' => 'Ella', 'lastname' => 'Marquez',
            'email' => 'ella@example.test', 'password' => 'StudentPassword1!',
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'A', 'student_type' => 'Irregular',
        ]);
        $this->instructor('INS-1');
        $first = $this->subject('IT101', '1st Semester');
        $second = $this->subject('IT201', '2nd Semester');
        $this->assign('INS-1', $first);
        $this->assign('INS-1', $second);

        $this->setTerm('1st Semester');

        $this->actingAs($student, 'student')
            ->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee('IT101')
            ->assertDontSee('IT201');
    }

    private function setTerm(string $semester, ?string $year = '2026-2027'): void
    {
        AcademicTerm::save($semester, $year);
        AcademicTerm::forget();
    }

    private function admin(): MainAdmin
    {
        return MainAdmin::firstOrCreate(
            ['email' => 'admin-term@example.test'],
            ['password' => 'AdminPassword1!'],
        );
    }

    private function instructor(string $id): Instructor
    {
        return Instructor::create([
            'instructor_id' => $id, 'firstname' => 'Ins', 'lastname' => 'Tructor',
            'email' => "instructor-{$id}@example.test", 'password' => 'InstructorPassword1!',
            'department' => 'BSIT',
        ]);
    }

    private function subject(string $code, string $semester): int
    {
        return SubjectCode::create([
            'subject_code' => $code,
            'subject_description' => "Course {$code}",
            'year_level' => '1',
            'program' => 'BSIT',
            'semester' => $semester,
        ])->subject_id;
    }

    private function section(string $program, string $year, string $section): void
    {
        ProgramSection::firstOrCreate(['program' => $program, 'year_level' => $year, 'section' => $section]);
    }

    private function assign(string $instructorId, int $subjectId): void
    {
        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructorId, 'subject_id' => $subjectId,
            'program' => 'BSIT', 'year_level' => '1', 'section' => 'Z',
        ]);
    }
}
