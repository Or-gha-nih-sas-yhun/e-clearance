<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\StudentAccount;
use App\Models\StudentRegistry;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionProperty;
use Tests\TestCase;

class StudentSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_KEY = 'student_registration';

    protected function setUp(): void
    {
        parent::setUp();

        $tableAvailable = new ReflectionProperty(AuditLogger::class, 'tableAvailable');
        $tableAvailable->setValue(null, null);
    }

    public function test_an_eligible_inactive_entry_can_register_end_to_end(): void
    {
        $entry = $this->registryEntry();

        $this->post(route('student.register.send-code'), ['ms_account' => 'jovan@mcc.edu.ph'])
            ->assertRedirect(route('student.login'))
            ->assertSessionHas(self::SESSION_KEY);

        $state = session(self::SESSION_KEY);
        $this->assertSame('code', $state['stage']);
        $this->assertSame($entry->student_id, $state['student_id']);
        $state['code_hash'] = Hash::make('314159');

        $this->withSession([self::SESSION_KEY => $state])
            ->post(route('student.register.verify-code'), ['verification_code' => '314159'])
            ->assertRedirect(route('student.register'));

        $verified = session(self::SESSION_KEY);
        $this->assertSame('form', $verified['stage']);

        $this->withSession([self::SESSION_KEY => $verified])
            ->get(route('student.register'))
            ->assertOk()
            ->assertSee('2026-0001')
            ->assertSee('jovan@mcc.edu.ph');

        $this->withSession([self::SESSION_KEY => $verified])
            ->post(route('student.register.submit'), $this->formPayload())
            ->assertRedirect(route('student.login'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('student_account', [
            'student_id' => '2026-0001',
            'email' => 'jovan@mcc.edu.ph',
            'firstname' => 'Jovan',
            'program' => 'BSIT',
        ]);
        $this->assertDatabaseHas('student_registry', [
            'id' => $entry->id,
            'status' => 'active',
        ]);
        $this->assertNotNull(StudentRegistry::find($entry->id)->registered_at);
        $this->assertNull(session(self::SESSION_KEY));
    }

    public function test_the_created_account_can_then_sign_in(): void
    {
        $entry = $this->registryEntry();
        $this->completeRegistration($entry);

        $student = StudentAccount::where('student_id', '2026-0001')->firstOrFail();

        $this->loginThroughDeviceCode('student', 'student.login.submit', [
            'student_id' => '2026-0001',
            'password' => 'Strong-Password-123!',
        ], 'student.login.otp.verify')->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_an_ms_account_that_is_not_on_the_roster_is_refused(): void
    {
        $this->post(route('student.register.send-code'), ['ms_account' => 'stranger@mcc.edu.ph'])
            ->assertSessionHasErrors('ms_account');

        $this->assertNull(session(self::SESSION_KEY));
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_an_already_active_entry_cannot_register_again(): void
    {
        $this->registryEntry(['status' => StudentRegistry::STATUS_ACTIVE]);

        $this->post(route('student.register.send-code'), ['ms_account' => 'jovan@mcc.edu.ph'])
            ->assertSessionHasErrors('ms_account');

        $this->assertNull(session(self::SESSION_KEY));
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_a_wrong_code_never_reaches_the_form(): void
    {
        $entry = $this->registryEntry();
        $this->post(route('student.register.send-code'), ['ms_account' => 'jovan@mcc.edu.ph']);

        $state = session(self::SESSION_KEY);
        $state['code_hash'] = Hash::make('314159');

        $this->withSession([self::SESSION_KEY => $state])
            ->post(route('student.register.verify-code'), ['verification_code' => '000000'])
            ->assertSessionHasErrors('verification_code');

        $this->withSession([self::SESSION_KEY => $state])
            ->get(route('student.register'))
            ->assertRedirect(route('student.login'));

        $this->assertDatabaseHas('student_registry', ['id' => $entry->id, 'status' => 'inactive']);
    }

    public function test_the_form_cannot_be_reached_or_submitted_without_a_verified_session(): void
    {
        $this->registryEntry();

        $this->get(route('student.register'))->assertRedirect(route('student.login'));

        $this->post(route('student.register.submit'), $this->formPayload())
            ->assertRedirect(route('student.login'));

        $this->assertDatabaseCount('student_account', 0);
    }

    /**
     * The identity fields are readonly in the browser only. A tampered POST must
     * still create the account the verified session names, never the one it asks for.
     */
    public function test_posted_student_id_and_email_are_ignored_in_favour_of_the_verified_session(): void
    {
        $entry = $this->registryEntry();
        $verified = $this->verifiedSession($entry);

        $this->withSession([self::SESSION_KEY => $verified])
            ->post(route('student.register.submit'), $this->formPayload([
                'student_id' => '2099-9999',
                'email' => 'attacker@evil.test',
                'ms_account' => 'attacker@evil.test',
            ]))->assertRedirect(route('student.login'));

        $this->assertDatabaseHas('student_account', [
            'student_id' => '2026-0001',
            'email' => 'jovan@mcc.edu.ph',
        ]);
        $this->assertDatabaseMissing('student_account', ['student_id' => '2099-9999']);
        $this->assertDatabaseMissing('student_account', ['email' => 'attacker@evil.test']);
    }

    public function test_a_replayed_verified_session_cannot_create_a_second_account(): void
    {
        $entry = $this->registryEntry();
        $verified = $this->verifiedSession($entry);

        $this->withSession([self::SESSION_KEY => $verified])
            ->post(route('student.register.submit'), $this->formPayload())
            ->assertRedirect(route('student.login'));

        // Same verified session posted again after the entry was consumed.
        $this->withSession([self::SESSION_KEY => $verified])
            ->post(route('student.register.submit'), $this->formPayload())
            ->assertRedirect(route('student.login'));

        $this->assertSame(1, StudentAccount::where('student_id', '2026-0001')->count());
    }

    public function test_deleting_the_student_account_reopens_the_registry_entry(): void
    {
        $entry = $this->registryEntry();
        $this->completeRegistration($entry);
        $this->assertDatabaseHas('student_registry', ['id' => $entry->id, 'status' => 'active']);

        $admin = MainAdmin::create([
            'name' => 'Registry Admin',
            'email' => 'registry-admin@example.test',
            'password' => 'Strong-Admin-Password-123!',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('students.destroy', '2026-0001'))
            ->assertRedirect();

        $this->assertDatabaseHas('student_registry', ['id' => $entry->id, 'status' => 'inactive']);
        $this->assertNull(StudentRegistry::find($entry->id)->registered_at);
    }

    public function test_the_login_page_offers_registration(): void
    {
        $this->get(route('student.login'))
            ->assertOk()
            ->assertSee('Register your account')
            ->assertSee(route('student.register.send-code'), false);
    }

    private function registryEntry(array $overrides = []): StudentRegistry
    {
        return StudentRegistry::create($overrides + [
            'student_id' => '2026-0001',
            'ms_account' => 'jovan@mcc.edu.ph',
            'status' => StudentRegistry::STATUS_INACTIVE,
        ]);
    }

    /** @return array<string, mixed> */
    private function verifiedSession(StudentRegistry $entry): array
    {
        return [
            'stage' => 'form',
            'registry_id' => $entry->id,
            'student_id' => $entry->student_id,
            'ms_account' => $entry->ms_account,
            'verified_at' => now()->timestamp,
        ];
    }

    /** @return array<string, mixed> */
    private function formPayload(array $overrides = []): array
    {
        return $overrides + [
            'firstname' => 'Jovan',
            'middlename' => 'Almo',
            'lastname' => 'Mahusay',
            'suffix' => '',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'EAST',
            'student_type' => 'Regular',
            'password' => 'Strong-Password-123!',
            'password_confirmation' => 'Strong-Password-123!',
        ];
    }

    private function completeRegistration(StudentRegistry $entry): void
    {
        $this->withSession([self::SESSION_KEY => $this->verifiedSession($entry)])
            ->post(route('student.register.submit'), $this->formPayload());
    }

    private function sentMessages(): \Illuminate\Support\Collection
    {
        $transport = \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport();

        return $transport instanceof \Illuminate\Mail\Transport\ArrayTransport ? $transport->messages() : collect();
    }
}
