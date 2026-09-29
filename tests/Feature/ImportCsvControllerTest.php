<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Models\Student;
use App\Models\StudentRegistry;
use App\Support\InstructorDepartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportCsvControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_import_normalizes_valid_data_and_safely_skips_invalid_and_malformed_rows(): void
    {
        $csv = implode("\n", [
            'student_id,firstname,lastname,email,password,program,year_level,section,student_type',
            '2026-0001,Ana,Reyes,ANA.REYES@EXAMPLE.TEST,StrongPass1!,bsit,1,a,regular',
            '2026-0002,Ben,Santos,ben.santos@example.test,StrongPass2!,BSCS,2,B,Regular',
            '2026-0003,Cara,Cruz,cara.cruz@example.test,StrongPass3!,BSIT,2,C,Irregular,unexpected',
        ]);

        $response = $this->postImport('students', $csv);

        $response
            ->assertOk()
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('skipped', 2);

        $student = Student::query()->sole();
        $this->assertSame('ana.reyes@example.test', $student->email);
        $this->assertSame('BSIT', $student->program);
        $this->assertSame('A', $student->section);
        $this->assertSame('Regular', $student->student_type);
        $this->assertTrue(Hash::check('StrongPass1!', $student->password));
    }

    public function test_instructor_import_folds_the_education_programs_and_reads_the_position_column(): void
    {
        $csv = implode("\n", [
            'instructor_id,firstname,lastname,email,password,department,employment_status',
            '1001,Grace,Villanueva,grace@example.test,StrongPass1!,BSED,part-time',
            '1002,Noel,Bacus,noel@example.test,StrongPass2!,College of Education,Regular',
            '1003,Rita,Lim,rita@example.test,StrongPass3!,BSIT,',
            '1004,Omar,Diaz,omar@example.test,StrongPass4!,BSIT,Consultant',
        ]);

        $this->postImport('instructors', $csv)
            ->assertOk()
            ->assertJsonPath('inserted', 3)
            ->assertJsonPath('skipped', 1);

        $this->assertSame(
            [InstructorDepartment::COLLEGE_OF_EDUCATION, Instructor::EMPLOYMENT_PART_TIME],
            $this->facultyDetails('1001'),
        );
        $this->assertSame(
            [InstructorDepartment::COLLEGE_OF_EDUCATION, Instructor::EMPLOYMENT_REGULAR],
            $this->facultyDetails('1002'),
        );
        // A blank position column falls back to the college's default hire.
        $this->assertSame(['BSIT', Instructor::EMPLOYMENT_REGULAR], $this->facultyDetails('1003'));
    }

    public function test_import_rejects_duplicate_or_unknown_headers_before_writing(): void
    {
        $csv = implode("\n", [
            'student_id,firstname,lastname,email,email,password,program,year_level,section,unexpected',
            '2026-0001,Ana,Reyes,a@example.test,a@example.test,StrongPass1!,BSIT,1,A,value',
        ]);

        $this->postImport('students', $csv)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('student_account', 0);
    }

    public function test_import_rejects_binary_content_even_when_named_csv(): void
    {
        $this->postImport('registrar', "firstname,lastname,email,password\nAna,Reyes,a@example.test,StrongPass1!\0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('registrar', 0);
    }

    public function test_registration_import_accepts_an_excel_utf16_tab_separated_export(): void
    {
        $csv = "student_id\tms_account\tstatus\r\n"
            ."2026-0042\tstudent42@mcc.edu.ph\tinactive\r\n";
        $utf16 = "\xFF\xFE".mb_convert_encoding($csv, 'UTF-16LE', 'UTF-8');

        $this->postImport('student_registry', $utf16)
            ->assertOk()
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('skipped', 0);

        $entry = StudentRegistry::query()->sole();
        $this->assertSame('2026-0042', $entry->student_id);
        $this->assertSame('student42@mcc.edu.ph', $entry->ms_account);
    }

    public function test_import_accepts_windows_encoded_spreadsheet_text(): void
    {
        $csv = "firstname,lastname,email,password\r\nJosé,Reyes,jose@example.test,StrongPass1!\r\n";
        $windowsText = mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');

        $this->postImport('registrar', $windowsText)
            ->assertOk()
            ->assertJsonPath('inserted', 1);

        $this->assertDatabaseHas('registrar', ['firstname' => 'José', 'email' => 'jose@example.test']);
    }

    public function test_a_renamed_excel_workbook_receives_a_specific_csv_instruction(): void
    {
        $this->postImport('student_registry', "PK\x03\x04".str_repeat("\x20", 32))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.csv_file.0',
                'This is an Excel workbook, not CSV text. In Excel, use Save As and choose CSV UTF-8 (Comma delimited) (*.csv), then upload the saved CSV file.',
            );
    }

    public function test_import_rejects_control_characters_and_non_csv_extensions(): void
    {
        $csv = "firstname,lastname,email,password\nAna,Reyes,a@example.test,Strong\x01Pass1!";

        $this->postImport('registrar', $csv)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->postImport('registrar', str_replace("\x01", '', $csv), 'import.txt')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('registrar', 0);
    }

    public function test_row_limit_is_enforced_before_any_account_is_created(): void
    {
        $rows = ['firstname,lastname,email,password'];
        for ($row = 1; $row <= 2001; $row++) {
            $rows[] = "Test,Registrar,test{$row}@example.test,StrongPass1!";
        }

        $this->postImport('registrar', implode("\n", $rows))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('csv_file');

        $this->assertDatabaseCount('registrar', 0);
    }

    /** @return array{0: string, 1: string} the stored department and position. */
    private function facultyDetails(string $instructorId): array
    {
        $instructor = Instructor::where('instructor_id', $instructorId)->sole();

        return [$instructor->department, $instructor->employment_status];
    }

    private function postImport(string $type, string $contents, string $fileName = 'import.csv')
    {
        $adminId = DB::table('main_admin')
            ->where('email', 'csv-admin@example.test')
            ->value('id');

        if ($adminId === null) {
            $adminId = DB::table('main_admin')->insertGetId([
                'email' => 'csv-admin@example.test',
                'password' => Hash::make('StrongAdminPassword1!'),
            ]);
        }

        $admin = MainAdmin::findOrFail($adminId);

        return $this->actingAs($admin, 'admin')->postJson(route('import.csv'), [
            'type' => $type,
            'csv_file' => UploadedFile::fake()->createWithContent($fileName, $contents),
        ]);
    }
}
