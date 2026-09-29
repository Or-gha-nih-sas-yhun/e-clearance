<?php

namespace App\Http\Controllers;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\ProgramSection;
use App\Models\Registrar;
use App\Models\Student;
use App\Models\StudentRegistry;
use App\Models\SubjectCode;
use App\Models\Treasurer;
use App\Support\InstructorDepartment;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportCsvController extends Controller
{
    private const MAX_IMPORT_ROWS = 2000;

    private const MAX_COLUMNS = 16;

    private const MAX_RECORD_BYTES = 65536;

    private const MAX_FIELD_BYTES = 2048;

    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private const PROGRAMS = ['BSIT', 'BSBA', 'BSHM', 'BSED', 'BEED'];

    private const YEAR_LEVELS = ['1', '2', '3', '4'];

    private const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer', 'Bridging'];

    private const TEXT_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'text/x-csv',
        'application/csv',
        'application/x-csv',
        'application/vnd.ms-excel',
    ];

    /** @var array<string, array{allowed: list<string>, required: list<string>}> */
    private const HEADERS = [
        'students' => [
            'allowed' => ['student_id', 'firstname', 'middlename', 'lastname', 'suffix', 'email', 'password', 'program', 'year_level', 'section', 'student_type'],
            'required' => ['student_id', 'firstname', 'lastname', 'email', 'password', 'program', 'year_level', 'section'],
        ],
        'student_registry' => [
            'allowed' => ['student_id', 'ms_account', 'status'],
            'required' => ['student_id', 'ms_account'],
        ],
        'instructors' => [
            'allowed' => ['instructor_id', 'firstname', 'middlename', 'lastname', 'suffix', 'email', 'password', 'department', 'employment_status'],
            'required' => ['instructor_id', 'firstname', 'lastname', 'email', 'password', 'department'],
        ],
        'admin_personnel' => [
            'allowed' => ['firstname', 'lastname', 'email', 'password', 'office', 'role'],
            'required' => ['firstname', 'lastname', 'email', 'password', 'role'],
        ],
        'registrar' => [
            'allowed' => ['firstname', 'lastname', 'email', 'password'],
            'required' => ['firstname', 'lastname', 'email', 'password'],
        ],
        'subject_codes' => [
            'allowed' => ['subject_code', 'subject_description', 'year_level', 'program', 'semester'],
            'required' => ['subject_code', 'subject_description', 'year_level', 'program', 'semester'],
        ],
        'sections' => [
            'allowed' => ['program', 'section', 'year_level', 'year_levels'],
            'required' => ['program', 'section'],
        ],
        'assignments' => [
            'allowed' => ['instructor_id', 'subject_id', 'program', 'year_level', 'section'],
            'required' => ['instructor_id', 'subject_id', 'program', 'year_level', 'section'],
        ],
        'treasurers' => [
            'allowed' => ['firstname', 'middlename', 'lastname', 'suffix', 'email', 'password', 'treasurer_type', 'department', 'program', 'year_level', 'section'],
            'required' => ['firstname', 'lastname', 'email', 'password', 'treasurer_type'],
        ],
    ];

    public function import(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::HEADERS))],
            'csv_file' => ['required', 'file', 'extensions:csv', 'max:5120'],
        ]);

        $type = $validated['type'];
        /** @var UploadedFile $file */
        $file = $validated['csv_file'];
        $this->assertAcceptableUpload($file);

        [$rows, $errors, $skipped] = $this->parseCsv($file, $type);
        $inserted = 0;

        DB::transaction(function () use ($rows, $type, &$inserted, &$skipped, &$errors): void {
            foreach ($rows as $parsedRow) {
                $data = $parsedRow['data'];
                $rowNumber = $parsedRow['row'];

                match ($type) {
                    'students' => $this->importStudent($data, $rowNumber, $inserted, $skipped, $errors),
                    'student_registry' => $this->importStudentRegistry($data, $rowNumber, $inserted, $skipped, $errors),
                    'instructors' => $this->importInstructor($data, $rowNumber, $inserted, $skipped, $errors),
                    'admin_personnel' => $this->importPersonnel($data, $rowNumber, $inserted, $skipped, $errors),
                    'registrar' => $this->importRegistrar($data, $rowNumber, $inserted, $skipped, $errors),
                    'subject_codes' => $this->importSubjectCode($data, $rowNumber, $inserted, $skipped, $errors),
                    'sections' => $this->importSection($data, $rowNumber, $inserted, $skipped, $errors),
                    'assignments' => $this->importAssignment($data, $rowNumber, $inserted, $skipped, $errors),
                    'treasurers' => $this->importTreasurer($data, $rowNumber, $inserted, $skipped, $errors),
                };
            }
        });

        $message = "Import complete. Inserted: {$inserted}, Skipped: {$skipped}.";
        if ($errors !== []) {
            $message .= ' First errors: '.implode(' | ', array_slice($errors, 0, 3));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        }

        return redirect()->back()->with('flash', [
            'type' => $errors === [] ? 'success' : 'warning',
            'message' => $message,
        ]);
    }

    private function importStudent(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['email'] = $this->normalizeEmail($data['email'] ?? '');
        $data['program'] = strtoupper($data['program'] ?? '');
        $data['year_level'] = $this->normalizeYearLevel($data['year_level'] ?? '');
        $data['section'] = strtoupper($data['section'] ?? '');
        $data['student_type'] = $this->canonicalStudentType($data['student_type'] ?? '') ?? ($data['student_type'] ?? '');

        if (($data['student_type'] ?? '') === '') {
            $data['student_type'] = 'Regular';
        }

        if ($message = $this->rowError($data, [
            'student_id' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'firstname' => $this->requiredNameRules(),
            'middlename' => $this->middleNameRules(),
            'lastname' => $this->requiredNameRules(),
            'suffix' => $this->suffixRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'program' => ['required', Rule::in(self::PROGRAMS)],
            'year_level' => ['required', Rule::in(self::YEAR_LEVELS)],
            'section' => ['required', 'string', 'max:20'],
            'student_type' => ['required', Rule::in(['Regular', 'Irregular'])],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if (Student::where('student_id', $data['student_id'])->exists() || $this->emailExists(Student::class, $data['email'])) {
            $this->skip($row, 'student ID or email already exists.', $skipped, $errors);

            return;
        }

        Student::create([
            'student_id' => $data['student_id'],
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'] ?? '',
            'lastname' => $data['lastname'],
            'suffix' => $data['suffix'] ?? '',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'program' => $data['program'],
            'year_level' => $data['year_level'],
            'section' => $data['section'],
            'student_type' => $data['student_type'],
        ]);
        $inserted++;
    }

    private function importStudentRegistry(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        if (! Schema::hasTable('student_registry')) {
            $this->skip($row, 'the student_registry table does not exist yet.', $skipped, $errors);

            return;
        }

        $data['ms_account'] = $this->normalizeEmail($data['ms_account'] ?? '');
        $data['status'] = strtolower(trim($data['status'] ?? '')) ?: StudentRegistry::STATUS_INACTIVE;

        if ($message = $this->rowError($data, [
            'student_id' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'ms_account' => ['required', 'string', 'email', 'max:150'],
            'status' => ['required', Rule::in([StudentRegistry::STATUS_ACTIVE, StudentRegistry::STATUS_INACTIVE])],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        $exists = StudentRegistry::where('student_id', $data['student_id'])
            ->orWhereRaw('LOWER(ms_account) = ?', [$data['ms_account']])
            ->exists();

        if ($exists) {
            $this->skip($row, 'student ID or Microsoft account is already on the registration list.', $skipped, $errors);

            return;
        }

        StudentRegistry::create([
            'student_id' => $data['student_id'],
            'ms_account' => $data['ms_account'],
            'status' => $data['status'],
            'registered_at' => $data['status'] === StudentRegistry::STATUS_ACTIVE ? now() : null,
        ]);
        $inserted++;
    }

    private function importInstructor(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['email'] = $this->normalizeEmail($data['email'] ?? '');
        // BSED and BEED instructors are one faculty; a file still naming either
        // program is accepted and stored as the College of Education.
        $data['department'] = InstructorDepartment::canonical($data['department'] ?? '');
        $data['employment_status'] = $this->canonicalEmploymentStatus($data['employment_status'] ?? '')
            ?? ($data['employment_status'] ?? '');

        if (($data['employment_status'] ?? '') === '') {
            $data['employment_status'] = Instructor::EMPLOYMENT_REGULAR;
        }

        if ($message = $this->rowError($data, [
            'instructor_id' => ['required', 'regex:/^\d{4}$/'],
            'firstname' => $this->requiredNameRules(),
            'middlename' => $this->middleNameRules(),
            'lastname' => $this->requiredNameRules(),
            'suffix' => $this->suffixRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'department' => ['required', Rule::in(InstructorDepartment::accepted())],
            'employment_status' => ['required', Rule::in(Instructor::EMPLOYMENT_STATUSES)],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if (Instructor::where('instructor_id', $data['instructor_id'])->exists() || $this->emailExists(Instructor::class, $data['email'])) {
            $this->skip($row, 'instructor ID or email already exists.', $skipped, $errors);

            return;
        }

        $attributes = [
            'instructor_id' => $data['instructor_id'],
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'] ?? '',
            'lastname' => $data['lastname'],
            'suffix' => $data['suffix'] ?? '',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'department' => $data['department'],
        ];

        // Deploys never migrate, so the column may not exist here yet.
        if (Instructor::tracksEmploymentStatus()) {
            $attributes['employment_status'] = $data['employment_status'];
        }

        Instructor::create($attributes);
        $inserted++;
    }

    private function importPersonnel(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['email'] = $this->normalizeEmail($data['email'] ?? '');
        $data['role'] = strtolower($data['role'] ?? '');

        if ($message = $this->rowError($data, [
            'firstname' => $this->requiredNameRules(),
            'lastname' => $this->requiredNameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'office' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(AdminPersonnel::$validRoles))],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if ($this->emailExists(AdminPersonnel::class, $data['email'])) {
            $this->skip($row, 'email already exists.', $skipped, $errors);

            return;
        }

        AdminPersonnel::create([
            'personnel_id' => $this->generateUniqueId(AdminPersonnel::class, 'personnel_id', 'AP-'),
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'office' => $data['office'] ?? '',
            'role' => $data['role'],
        ]);
        $inserted++;
    }

    private function importRegistrar(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['email'] = $this->normalizeEmail($data['email'] ?? '');

        if ($message = $this->rowError($data, [
            'firstname' => $this->requiredNameRules(),
            'lastname' => $this->requiredNameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if ($this->emailExists(Registrar::class, $data['email'])) {
            $this->skip($row, 'email already exists.', $skipped, $errors);

            return;
        }

        Registrar::create([
            'registrar_id' => $this->generateUniqueId(Registrar::class, 'registrar_id', 'REG-'),
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'registrar',
        ]);
        $inserted++;
    }

    private function importSubjectCode(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['subject_code'] = strtoupper($data['subject_code'] ?? '');
        $data['year_level'] = $this->normalizeYearLevel($data['year_level'] ?? '');
        $data['program'] = $this->canonicalPrograms($data['program'] ?? '') ?? '';
        $data['semester'] = $this->canonicalSemester($data['semester'] ?? '') ?? '';

        if ($message = $this->rowError($data, [
            'subject_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9 .&\/-]*$/'],
            'subject_description' => ['required', 'string', 'max:500'],
            'year_level' => ['required', Rule::in(self::YEAR_LEVELS)],
            'program' => ['required', 'string', 'max:50'],
            'semester' => ['required', Rule::in(self::SEMESTERS)],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if (SubjectCode::where('subject_code', $data['subject_code'])
            ->where('year_level', $data['year_level'])
            ->where('program', $data['program'])
            ->where('semester', $data['semester'])
            ->exists()) {
            $this->skip($row, 'subject and academic assignment already exist.', $skipped, $errors);

            return;
        }

        SubjectCode::create([
            'subject_code' => $data['subject_code'],
            'subject_description' => $data['subject_description'],
            'year_level' => $data['year_level'],
            'program' => $data['program'],
            'semester' => $data['semester'],
        ]);
        $inserted++;
    }

    private function importSection(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['program'] = strtoupper($data['program'] ?? '');
        $data['section'] = strtoupper($data['section'] ?? '');

        if ($message = $this->rowError($data, [
            'program' => ['required', Rule::in(self::PROGRAMS)],
            'section' => ['required', 'string', 'max:50'],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        $rawYears = ($data['year_levels'] ?? '') !== ''
            ? explode(',', $data['year_levels'])
            : [$data['year_level'] ?? ''];
        $yearLevels = [];

        foreach ($rawYears as $rawYear) {
            $year = $this->normalizeYearLevel($rawYear);
            if (! in_array($year, self::YEAR_LEVELS, true)) {
                $this->skip($row, 'year levels must only contain 1, 2, 3, or 4.', $skipped, $errors);

                return;
            }

            $yearLevels[] = $year;
        }

        $yearLevels = array_values(array_unique($yearLevels));
        sort($yearLevels);
        $saved = 0;

        foreach ($yearLevels as $level) {
            if (! ProgramSection::where([
                'program' => $data['program'],
                'year_level' => $level,
                'section' => $data['section'],
            ])->exists()) {
                ProgramSection::create([
                    'program' => $data['program'],
                    'year_level' => $level,
                    'section' => $data['section'],
                ]);
                $inserted++;
                $saved++;
            }
        }

        if ($saved === 0) {
            $this->skip($row, 'section already exists for the provided year levels.', $skipped, $errors);
        }
    }

    private function importAssignment(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['program'] = strtoupper($data['program'] ?? '');
        $data['year_level'] = $this->normalizeYearLevel($data['year_level'] ?? '');
        $data['section'] = strtoupper($data['section'] ?? '');

        if ($message = $this->rowError($data, [
            'instructor_id' => ['required', 'string', 'max:50'],
            'subject_id' => ['required', 'string', 'max:30'],
            'program' => ['required', Rule::in(self::PROGRAMS)],
            'year_level' => ['required', Rule::in(self::YEAR_LEVELS)],
            'section' => ['required', 'string', 'max:20'],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if (! Instructor::where('instructor_id', $data['instructor_id'])->exists()) {
            $this->skip($row, 'instructor was not found.', $skipped, $errors);

            return;
        }

        $subject = $this->resolveSubject($data['subject_id'], $data['program'], $data['year_level']);
        if (! $subject) {
            $this->skip($row, 'subject was not found for the selected program and year level, or its code is ambiguous.', $skipped, $errors);

            return;
        }

        if (InstructorAssignment::where([
            'instructor_id' => $data['instructor_id'],
            'subject_id' => $subject->subject_id,
            'program' => $data['program'],
            'year_level' => $data['year_level'],
            'section' => $data['section'],
        ])->exists()) {
            $this->skip($row, 'assignment already exists.', $skipped, $errors);

            return;
        }

        InstructorAssignment::create([
            'instructor_id' => $data['instructor_id'],
            'subject_id' => $subject->subject_id,
            'program' => $data['program'],
            'year_level' => $data['year_level'],
            'section' => $data['section'],
        ]);
        $inserted++;
    }

    private function importTreasurer(array $data, int $row, int &$inserted, int &$skipped, array &$errors): void
    {
        $data['email'] = $this->normalizeEmail($data['email'] ?? '');
        $data['treasurer_type'] = strtolower($data['treasurer_type'] ?? '');
        $data['department'] = strtoupper($data['department'] ?? '');
        $data['program'] = strtoupper($data['program'] ?? '');
        $data['year_level'] = $this->normalizeYearLevel($data['year_level'] ?? '');
        $data['section'] = strtoupper($data['section'] ?? '');

        if ($message = $this->rowError($data, [
            'firstname' => $this->requiredNameRules(),
            'middlename' => $this->middleNameRules(),
            'lastname' => $this->requiredNameRules(),
            'suffix' => $this->suffixRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'treasurer_type' => ['required', Rule::in(['department', 'section'])],
            'department' => ['nullable', 'required_if:treasurer_type,department', Rule::in(self::PROGRAMS)],
            'program' => ['nullable', 'required_if:treasurer_type,section', Rule::in(self::PROGRAMS)],
            'year_level' => ['nullable', 'required_if:treasurer_type,section', Rule::in(self::YEAR_LEVELS)],
            'section' => ['nullable', 'required_if:treasurer_type,section', 'string', 'max:50'],
        ])) {
            $this->skip($row, $message, $skipped, $errors);

            return;
        }

        if ($this->emailExists(Treasurer::class, $data['email'])) {
            $this->skip($row, 'email already exists.', $skipped, $errors);

            return;
        }

        $isDepartment = $data['treasurer_type'] === 'department';
        Treasurer::create([
            'treasurer_id' => $this->generateUniqueId(Treasurer::class, 'treasurer_id', 'TR-'),
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'] ?? '',
            'lastname' => $data['lastname'],
            'suffix' => $data['suffix'] ?? '',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'treasurer_type' => $data['treasurer_type'],
            'department' => $isDepartment ? $data['department'] : null,
            'program' => $isDepartment ? null : $data['program'],
            'year_level' => $isDepartment ? null : $data['year_level'],
            'section' => $isDepartment ? null : $data['section'],
        ]);
        $inserted++;
    }

    private function assertAcceptableUpload(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        $mimeType = strtolower((string) $file->getMimeType());

        if (! $path || ! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'csv_file' => 'The uploaded CSV could not be read.',
            ]);
        }

        if (! in_array($mimeType, self::TEXT_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'csv_file' => 'Upload a plain-text CSV file.',
            ]);
        }
    }

    /**
     * @return array{list<array{row: int, data: array<string, string>}>, list<string>, int}
     */
    private function parseCsv(UploadedFile $file, string $type): array
    {
        $handle = @fopen($file->getRealPath(), 'rb');
        $parseHandle = null;
        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv_file' => 'The uploaded CSV could not be opened.',
            ]);
        }

        try {
            $contents = stream_get_contents($handle, self::MAX_FILE_BYTES + 1);
            if ($contents === false || $contents === '') {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV file is empty or unreadable.',
                ]);
            }

            if (strlen($contents) > self::MAX_FILE_BYTES) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV file may not be larger than 5 MB.',
                ]);
            }

            if (str_starts_with($contents, "PK\x03\x04")
                || str_starts_with($contents, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
                throw ValidationException::withMessages([
                    'csv_file' => 'This is an Excel workbook, not CSV text. In Excel, use Save As and choose CSV UTF-8 (Comma delimited) (*.csv), then upload the saved CSV file.',
                ]);
            }

            $contents = $this->normalizeCsvEncoding($contents);

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $contents) === 1
                || preg_match('//u', $contents) !== 1) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV text encoding could not be read. Save it as CSV UTF-8 (Comma delimited) and upload it again.',
                ]);
            }

            $parseHandle = fopen('php://temp', 'w+b');
            if ($parseHandle === false || fwrite($parseHandle, $contents) !== strlen($contents)) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The uploaded CSV could not be prepared for reading.',
                ]);
            }

            rewind($parseHandle);
            $delimiter = $this->detectCsvDelimiter($contents);
            $rawHeader = fgetcsv($parseHandle, self::MAX_RECORD_BYTES, $delimiter, '"', '');
            if ($rawHeader === false) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV must contain a header row.',
                ]);
            }

            $header = array_map(
                static fn ($value): string => strtolower(trim((string) $value)),
                $rawHeader,
            );
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }

            $this->validateHeader($header, $type);

            $rows = [];
            $errors = [];
            $skipped = 0;
            $record = 0;

            while (($row = fgetcsv($parseHandle, self::MAX_RECORD_BYTES, $delimiter, '"', '')) !== false) {
                $record++;
                $rowNumber = $record + 1;

                if ($record > self::MAX_IMPORT_ROWS) {
                    throw ValidationException::withMessages([
                        'csv_file' => 'A CSV import is limited to '.self::MAX_IMPORT_ROWS.' data rows.',
                    ]);
                }

                if ($this->isBlankRow($row)) {
                    $this->skip($rowNumber, 'row is empty.', $skipped, $errors);

                    continue;
                }

                if (count($row) !== count($header)) {
                    $this->skip($rowNumber, 'column count does not match the header.', $skipped, $errors);

                    continue;
                }

                $normalized = [];
                $malformed = false;
                foreach ($row as $field) {
                    $value = trim((string) $field);
                    if (strlen($value) > self::MAX_FIELD_BYTES) {
                        $this->skip($rowNumber, 'a field exceeds the maximum allowed length.', $skipped, $errors);
                        $malformed = true;

                        break;
                    }

                    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                        $this->skip($rowNumber, 'a field contains unsupported control characters.', $skipped, $errors);
                        $malformed = true;

                        break;
                    }

                    $normalized[] = $value;
                }

                if ($malformed) {
                    continue;
                }

                /** @var array<string, string> $data */
                $data = array_combine($header, $normalized);
                $rows[] = ['row' => $rowNumber, 'data' => $data];
            }

            if (! feof($parseHandle)) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV could not be parsed completely.',
                ]);
            }

            return [$rows, $errors, $skipped];
        } finally {
            if (is_resource($parseHandle)) {
                fclose($parseHandle);
            }
            fclose($handle);
        }
    }

    /**
     * Spreadsheet programs commonly export CSV as UTF-16 or the local Windows
     * code page. Convert those text formats before applying the same control
     * character, header, row, and field validation used for UTF-8 uploads.
     */
    private function normalizeCsvEncoding(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        if (str_starts_with($contents, "\xFF\xFE")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
        }

        if (preg_match('//u', $contents) === 1) {
            return $contents;
        }

        return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    /** Accepts regional Excel exports while preferring a regular comma CSV. */
    private function detectCsvDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");
        if ($firstLine === false) {
            return ',';
        }

        $selected = ',';
        $mostColumns = 1;
        foreach ([',', ';', "\t"] as $candidate) {
            $columns = count(str_getcsv($firstLine, $candidate, '"', ''));
            if ($columns > $mostColumns) {
                $selected = $candidate;
                $mostColumns = $columns;
            }
        }

        return $selected;
    }

    /** @param list<string> $header */
    private function validateHeader(array $header, string $type): void
    {
        if ($header === [] || count($header) > self::MAX_COLUMNS || in_array('', $header, true)) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV header must contain between 1 and '.self::MAX_COLUMNS.' named columns.',
            ]);
        }

        foreach ($header as $column) {
            if (strlen($column) > 64) {
                throw ValidationException::withMessages([
                    'csv_file' => 'A CSV header name is too long.',
                ]);
            }
        }

        if (count(array_unique($header)) !== count($header)) {
            throw ValidationException::withMessages([
                'csv_file' => 'CSV header names must be unique.',
            ]);
        }

        $configuration = self::HEADERS[$type];
        $unknown = array_diff($header, $configuration['allowed']);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV contains unsupported header columns: '.implode(', ', $unknown).'.',
            ]);
        }

        $missing = array_diff($configuration['required'], $header);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV is missing required header columns: '.implode(', ', $missing).'.',
            ]);
        }

        if ($type === 'sections' && ! in_array('year_level', $header, true) && ! in_array('year_levels', $header, true)) {
            throw ValidationException::withMessages([
                'csv_file' => 'A sections CSV must contain year_level or year_levels.',
            ]);
        }
    }

    private function resolveSubject(string $subjectReference, string $program, string $yearLevel): ?SubjectCode
    {
        if (ctype_digit($subjectReference)) {
            $subjectId = filter_var($subjectReference, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($subjectId === false) {
                return null;
            }

            $query = SubjectCode::whereKey($subjectId);
        } else {
            $query = SubjectCode::where('subject_code', strtoupper($subjectReference));
        }

        $subjects = $query
            ->where('year_level', $yearLevel)
            ->where(function ($query) use ($program) {
                $query->where('program', $program)
                    ->orWhere('program', 'like', $program.',%')
                    ->orWhere('program', 'like', '%,'.$program)
                    ->orWhere('program', 'like', '%,'.$program.',%');
            })
            ->limit(2)
            ->get();

        return $subjects->count() === 1 ? $subjects->first() : null;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizeYearLevel(string $yearLevel): string
    {
        $yearLevel = trim($yearLevel);

        return preg_match('/^[1-4]$/', $yearLevel) === 1 ? $yearLevel : '';
    }

    private function canonicalPrograms(string $programs): ?string
    {
        $values = array_map(
            static fn (string $program): string => strtoupper(trim($program)),
            explode(',', $programs),
        );

        if ($values === [] || in_array('', $values, true)) {
            return null;
        }

        $values = array_values(array_unique($values));
        if (array_diff($values, self::PROGRAMS) !== []) {
            return null;
        }

        sort($values);

        return implode(',', $values);
    }

    private function canonicalSemester(string $semester): ?string
    {
        foreach (self::SEMESTERS as $allowedSemester) {
            if (strcasecmp(trim($semester), $allowedSemester) === 0) {
                return $allowedSemester;
            }
        }

        return null;
    }

    private function canonicalStudentType(string $studentType): ?string
    {
        foreach (['Regular', 'Irregular'] as $allowedType) {
            if (strcasecmp(trim($studentType), $allowedType) === 0) {
                return $allowedType;
            }
        }

        return null;
    }

    /** Accepts the spellings a spreadsheet uses for a part-time hire. */
    private function canonicalEmploymentStatus(string $status): ?string
    {
        $status = trim($status);

        foreach (['part timer', 'part-timer', 'part time', 'part-time', 'parttime', 'parttimer'] as $partTime) {
            if (strcasecmp($status, $partTime) === 0) {
                return Instructor::EMPLOYMENT_PART_TIME;
            }
        }

        foreach (Instructor::EMPLOYMENT_STATUSES as $allowedStatus) {
            if (strcasecmp($status, $allowedStatus) === 0) {
                return $allowedStatus;
            }
        }

        return null;
    }

    /** @param class-string<Model> $model */
    private function emailExists(string $model, string $email): bool
    {
        return $model::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    /** @param class-string<Model> $model */
    private function generateUniqueId(string $model, string $column, string $prefix): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $id = $prefix.random_int(10000, 99999);
            if (! $model::query()->where($column, $id)->exists()) {
                return $id;
            }
        }

        throw new RuntimeException('A unique account identifier could not be generated.');
    }

    /** @return list<string|Password> */
    private function passwordRules(): array
    {
        return ['required', 'string', 'max:128', Password::min(8)->mixedCase()->numbers()->symbols()];
    }

    /** @return list<string> */
    private function emailRules(): array
    {
        return ['required', 'string', 'max:100', 'email:rfc'];
    }

    /** @return list<string> */
    private function requiredNameRules(): array
    {
        return PersonName::requiredRules();
    }

    /** @return list<string> */
    private function middleNameRules(): array
    {
        return PersonName::optionalRules();
    }

    /** @return list<string> */
    private function suffixRules(): array
    {
        return ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'];
    }

    private function rowError(array $data, array $rules): ?string
    {
        $validator = Validator::make($data, $rules);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    private function skip(int $row, string $message, int &$skipped, array &$errors): void
    {
        $errors[] = "Row {$row}: {$message}";
        $skipped++;
    }

    /** @param list<string|null> $row */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
