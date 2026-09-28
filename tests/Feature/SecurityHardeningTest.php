<?php

namespace Tests\Feature;

use App\Models\ClearanceVerificationToken;
use App\Models\Esignature;
use App\Models\Instructor;
use App\Models\Registrar;
use App\Models\SecurityAuditLog;
use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionProperty;
use Tests\Support\UploadFixtures;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // AuditLogger caches schema availability for a normal request lifecycle.
        // Reset it because PHPUnit reuses PHP statics while recreating databases.
        $tableAvailable = new ReflectionProperty(AuditLogger::class, 'tableAvailable');
        $tableAvailable->setValue(null, null);
    }

    public function test_sensitive_routes_reject_guests_and_users_from_the_wrong_portal(): void
    {
        $this->getJson(route('notifications.api'))
            ->assertUnauthorized();

        $student = $this->createStudent('STU-ROLE-001');

        $this->actingAs($student, 'student')
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->get(route('instructor.dashboard'))
            ->assertRedirect(route('instructor.login'));
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $request = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.41']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $request->post(route('login.post'), [
                'email' => 'rate-limit@example.test',
                'password' => 'Definitely-Wrong-Password-123!',
            ])->assertRedirect();
        }

        $request->post(route('login.post'), [
            'email' => 'rate-limit@example.test',
            'password' => 'Definitely-Wrong-Password-123!',
        ])->assertTooManyRequests();
    }

    public function test_clearance_upload_rejects_executables_and_keeps_allowed_files_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $student = $this->createStudent('STU-UPLOAD-001');
        [$instructor, $subjectId] = $this->createAssignedInstructor($student, 'INS-UPLOAD-001');

        $this->actingAs($student, 'student')
            ->post(route('student.submission-remark.upload'), [
                'subject_id' => $subjectId,
                'instructor_id' => $instructor->instructor_id,
                'submission_file' => UploadedFile::fake()->create(
                    'payload.php',
                    2,
                    'application/pdf'
                ),
            ])
            ->assertSessionHasErrors('submission_file');

        $this->assertDatabaseCount('student_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->post(route('student.submission-remark.upload'), [
            'subject_id' => $subjectId,
            'instructor_id' => $instructor->instructor_id,
            'description' => 'Valid private supporting document.',
            'submission_file' => UploadedFile::fake()->createWithContent(
                'clearance-proof.pdf',
                UploadFixtures::pdf(),
            ),
        ])->assertRedirect();

        $submission = StudentSubmission::query()->sole();

        $this->assertNotSame('clearance-proof.pdf', basename($submission->file_path));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', basename($submission->file_path));
        Storage::disk('local')->assertExists($submission->file_path);
        Storage::disk('public')->assertMissing($submission->file_path);
    }

    public function test_instructor_cannot_download_another_instructors_submission(): void
    {
        $student = $this->createStudent('STU-IDOR-001');
        [$assignedInstructor, $subjectId] = $this->createAssignedInstructor($student, 'INS-OWNER-001');
        $otherInstructor = $this->createInstructor('INS-OTHER-001');

        $submission = StudentSubmission::create([
            'student_id' => $student->student_id,
            'subject_id' => $subjectId,
            'instructor_id' => $assignedInstructor->instructor_id,
            'file_path' => 'student_submissions/STU-IDOR-001/proof.pdf',
            'file_name' => 'proof.pdf',
            'file_type' => 'application/pdf',
        ]);

        $this->actingAs($otherInstructor, 'instructor')
            ->get(route('instructor.submissions.download', $submission))
            ->assertForbidden();
    }

    public function test_signature_images_are_reencoded_before_they_are_saved(): void
    {
        $instructor = $this->createInstructor('INS-SIGNATURE-001');
        $payload = '<?php echo "signature-payload";';
        $dataUri = 'data:image/png;base64,'.base64_encode(UploadFixtures::png().$payload);

        $this->actingAs($instructor, 'instructor')
            ->postJson(route('esignature.save'), ['signature_data' => $dataUri])
            ->assertOk()
            ->assertJsonPath('success', true);

        $storedData = Esignature::query()->sole()->signature_data;
        $storedBinary = base64_decode(substr($storedData, strlen('data:image/png;base64,')), true);

        $this->assertIsString($storedBinary);
        $this->assertStringNotContainsString($payload, $storedBinary);
        $this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $storedBinary);
        $this->assertStringEndsWith("\x00\x00\x00\x00IEND\xAE\x42\x60\x82", $storedBinary);
    }

    public function test_responses_include_browser_security_headers(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_clearance_qr_uses_an_opaque_token_and_public_verification_is_limited(): void
    {
        $student = $this->createStudent('STU-QR-PRIVATE-1001', 'qr-private@example.test');

        DB::table('office_clearance_status')->insert([
            'student_id' => $student->student_id,
            'office_role' => 'registrar',
            'approver_id' => 'REG-001',
            'status' => 'Approved',
            'remarks' => 'CONFIDENTIAL REGISTRAR REMARK',
        ]);

        $this->actingAs($student, 'student')
            ->get(route('student.clearance.form'))
            ->assertOk();

        $verification = ClearanceVerificationToken::query()->sole();
        $plainToken = Crypt::decryptString($verification->token_encrypted);
        $verificationUrl = route('clearance.verify', ['token' => $plainToken]);

        $this->assertSame(64, strlen($plainToken));
        $this->assertTrue(ctype_alnum($plainToken));
        $this->assertSame(hash('sha256', $plainToken), $verification->token_hash);
        $this->assertStringNotContainsString($student->student_id, $verificationUrl);
        $this->assertStringNotContainsString($student->student_id, $verification->token_encrypted);

        $publicResponse = $this->get($verificationUrl);

        $publicResponse
            ->assertOk()
            ->assertSee('QR Test')
            ->assertSee('1001')
            ->assertDontSee($student->student_id)
            ->assertDontSee($student->email)
            ->assertDontSee('CONFIDENTIAL REGISTRAR REMARK')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $cacheControl = (string) $publicResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $this->assertNotNull($verification->fresh()->last_verified_at);

        $registrar = Registrar::create([
            'registrar_id' => 'REG-QR-001',
            'firstname' => 'QR',
            'lastname' => 'Registrar',
            'email' => 'qr-registrar@example.test',
            'password' => 'Strong-Registrar-Password-123!',
            'role' => 'registrar',
        ]);

        $this->actingAs($registrar, 'registrar')
            ->get(route('registrar.qr-scanner'))
            ->assertOk()
            ->assertSee('verificationPathPrefix', false)
            ->assertSee('/^[A-Za-z0-9]{64}$/', false)
            ->assertDontSee("url.pathname.includes('/registrar/clearance/verify/')", false);
    }

    public function test_audit_metadata_redacts_credentials_and_tokens(): void
    {
        AuditLogger::record('security.test', metadata: [
            'action' => 'clearance.approved',
            'password' => 'do-not-store-this',
            'api_token' => 'do-not-store-this-either',
            'nested' => [
                'student_id' => 'STU-001',
                'otp' => '123456',
            ],
        ]);

        $log = SecurityAuditLog::where('event', 'security.test')->sole();

        $this->assertSame('clearance.approved', $log->metadata['action']);
        $this->assertSame('STU-001', $log->metadata['nested']['student_id']);
        $this->assertArrayNotHasKey('password', $log->metadata);
        $this->assertArrayNotHasKey('api_token', $log->metadata);
        $this->assertArrayNotHasKey('otp', $log->metadata['nested']);
    }

    private function createStudent(string $studentId, ?string $email = null): StudentAccount
    {
        return StudentAccount::create([
            'student_id' => $studentId,
            'firstname' => str_contains($studentId, 'QR') ? 'QR' : 'Security',
            'lastname' => str_contains($studentId, 'QR') ? 'Test' : 'Student',
            'email' => $email ?? strtolower($studentId).'@example.test',
            'password' => 'Strong-Student-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'East',
            'student_type' => 'Regular',
        ]);
    }

    /** @return array{Instructor, int} */
    private function createAssignedInstructor(StudentAccount $student, string $instructorId): array
    {
        $instructor = $this->createInstructor($instructorId);
        $subjectId = DB::table('subject_codes')->insertGetId([
            'subject_code' => 'SEC-'.substr(hash('sha256', $instructorId), 0, 8),
            'subject_description' => 'Security Regression Subject',
            'year_level' => $student->year_level,
            'program' => $student->program,
            'semester' => 'First Semester',
        ]);

        DB::table('instructor_assignment')->insert([
            'instructor_id' => $instructor->instructor_id,
            'subject_id' => $subjectId,
            'program' => $student->program,
            'year_level' => $student->year_level,
            'section' => $student->section,
        ]);

        return [$instructor, $subjectId];
    }

    private function createInstructor(string $instructorId): Instructor
    {
        return Instructor::create([
            'instructor_id' => $instructorId,
            'firstname' => 'Security',
            'lastname' => 'Instructor',
            'email' => strtolower($instructorId).'@example.test',
            'password' => 'Strong-Instructor-Password-123!',
            'department' => 'BSIT',
        ]);
    }
}
