<?php

namespace Tests\Feature;

use App\Http\Middleware\LimitRequestFieldLengths;
use App\Models\SecurityAuditLog;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\TestCase;

class RequestSecurityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tableAvailable = new ReflectionProperty(AuditLogger::class, 'tableAvailable');
        $tableAvailable->setValue(null, null);
    }

    public function test_general_web_requests_are_limited_per_browser(): void
    {
        config(['security.web_rate_limit' => 3]);
        $browser = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.80']);

        $browser->get(route('landing'))->assertOk();
        $browser->get(route('landing'))->assertOk();
        $browser->get(route('landing'))->assertOk();
        $browser->get(route('landing'))->assertTooManyRequests();
    }

    public function test_recaptcha_v3_is_verified_on_the_server(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'site-key',
            'services.recaptcha.secret_key' => 'secret-key',
            'services.recaptcha.minimum_score' => 0.5,
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'action' => 'login',
                'score' => 0.9,
            ]),
        ]);

        $this->post(route('login.post'), [
            'email' => 'missing@example.test',
            'password' => 'Strong-Wrong-Password-123!',
            'recaptcha_token' => 'browser-token',
        ])->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors('recaptcha');

        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key'
            && $request['response'] === 'browser-token');
    }

    public function test_low_recaptcha_score_blocks_login_and_is_audited(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'site-key',
            'services.recaptcha.secret_key' => 'secret-key',
            'services.recaptcha.minimum_score' => 0.5,
        ]);
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'action' => 'login',
                'score' => 0.2,
            ]),
        ]);

        $this->post(route('login.post'), [
            'email' => 'admin@example.test',
            'password' => 'Strong-Wrong-Password-123!',
            'recaptcha_token' => 'low-score-token',
        ])->assertSessionHasErrors('recaptcha');

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'authentication.recaptcha_failed',
        ]);
    }

    public function test_student_id_is_formatted_before_login_validation_and_location_is_recorded(): void
    {
        $this->post(route('student.login.submit'), [
            'student_id' => '20261234',
            'password' => 'Strong-Wrong-Password-123!',
            'client_latitude' => '11.26654',
            'client_longitude' => '123.73321',
            'client_accuracy' => '35',
        ])->assertSessionHasErrors([
            'student_id' => 'The credentials you entered are incorrect.',
        ]);

        $log = SecurityAuditLog::where('event', 'authentication.failed')->latest('id')->firstOrFail();
        $this->assertSame(11.26654, $log->metadata['location']['latitude']);
        $this->assertSame(123.73321, $log->metadata['location']['longitude']);
        $this->assertEquals(35.0, $log->metadata['location']['accuracy_meters']);
    }

    public function test_login_pages_include_character_limits_location_and_recaptcha_hooks(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'site-key',
        ]);

        foreach (['login', 'student.login', 'instructor.login', 'office.login', 'registrar.login', 'treasurer.login'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('data-client-latitude', false)
                ->assertSee('data-recaptcha-token', false)
                ->assertSee('js/login-security.js', false)
                ->assertSee('js/input-constraints.js', false)
                ->assertSee('recaptcha/api.js?render=site-key', false)
                ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=()');
        }

        $this->get(route('student.login'))
            ->assertSee('maxlength="50"', false)
            ->assertSee('placeholder="Student ID (2000-1234)"', false);
    }

    public function test_oversized_request_fields_are_rejected_before_controller_work(): void
    {
        $request = Request::create('/test', 'POST', [
            'description' => str_repeat('x', 3001),
        ]);

        try {
            (new LimitRequestFieldLengths)->handle($request, fn () => response('ok'));
            $this->fail('The oversized field should have raised a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('description', $exception->errors());
            $this->assertStringContainsString('3,000 characters', $exception->errors()['description'][0]);
        }
    }
}
