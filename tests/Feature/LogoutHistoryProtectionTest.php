<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\MainAdmin;
use App\Models\Registrar;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutHistoryProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_portal_login_shows_success_and_logout_returns_to_its_login_page(): void
    {
        $accounts = [
            ['admin', 'login.post', [
                'email' => 'logout-admin@example.test',
                'password' => 'Strong-Password-123!',
            ], 'logout', 'dashboard', 'login', MainAdmin::create([
                'name' => 'Main Admin',
                'email' => 'logout-admin@example.test',
                'password' => 'Strong-Password-123!',
            ])],
            ['student', 'student.login.submit', [
                'student_id' => 'LOGOUT-STUDENT-001',
                'password' => 'Strong-Password-123!',
            ], 'student.logout', 'student.dashboard', 'student.login', StudentAccount::create([
                'student_id' => 'LOGOUT-STUDENT-001',
                'firstname' => 'Logout',
                'lastname' => 'Student',
                'email' => 'logout-student@example.test',
                'password' => 'Strong-Password-123!',
                'program' => 'BSIT',
                'year_level' => '4',
                'section' => 'A',
                'student_type' => 'Regular',
            ])],
            ['instructor', 'instructor.login.submit', [
                'email' => 'logout-instructor@example.test',
                'password' => 'Strong-Password-123!',
            ], 'instructor.logout', 'instructor.dashboard', 'instructor.login', Instructor::create([
                'instructor_id' => 'LOGOUT-INSTRUCTOR-001',
                'firstname' => 'Logout',
                'lastname' => 'Instructor',
                'email' => 'logout-instructor@example.test',
                'password' => 'Strong-Password-123!',
                'department' => 'BSIT',
            ])],
            ['office', 'office.login.submit', [
                'login' => 'logout-office@example.test',
                'password' => 'Strong-Password-123!',
                'role' => 'library',
            ], 'office.logout', 'office.dashboard', 'office.login', AdminPersonnel::create([
                'personnel_id' => 'LOGOUT-OFFICE-001',
                'firstname' => 'Logout',
                'lastname' => 'Officer',
                'email' => 'logout-office@example.test',
                'password' => 'Strong-Password-123!',
                'office' => 'Library',
                'role' => 'library',
            ])],
            ['registrar', 'registrar.login.submit', [
                'login' => 'logout-registrar@example.test',
                'password' => 'Strong-Password-123!',
            ], 'registrar.logout', 'registrar.dashboard', 'registrar.login', Registrar::create([
                'registrar_id' => 'LOGOUT-REGISTRAR-001',
                'firstname' => 'Logout',
                'lastname' => 'Registrar',
                'email' => 'logout-registrar@example.test',
                'password' => 'Strong-Password-123!',
                'role' => 'registrar',
            ])],
            ['treasurer', 'treasurer.login.submit', [
                'login' => 'logout-treasurer@example.test',
                'password' => 'Strong-Password-123!',
                'treasurer_type' => 'department',
            ], 'treasurer.logout', 'treasurer.dashboard', 'treasurer.login', Treasurer::create([
                'treasurer_id' => 'LOGOUT-TREASURER-001',
                'firstname' => 'Logout',
                'lastname' => 'Treasurer',
                'email' => 'logout-treasurer@example.test',
                'password' => 'Strong-Password-123!',
                'treasurer_type' => 'department',
                'department' => 'BSIT',
            ])],
        ];

        foreach ($accounts as [$guard, $loginRoute, $credentials, $logoutRoute, $protectedRoute, $expectedLoginRoute, $account]) {
            // Each account is signing in for the first time, so every portal
            // mails a code before it opens the session.
            $loginResponse = $this->loginThroughDeviceCode(
                $guard,
                $loginRoute,
                $credentials,
                $guard === 'admin' ? 'login.otp.verify' : "{$guard}.login.otp.verify",
            );
            $loginResponse->assertRedirect(route($protectedRoute))->assertSessionHas('login_success');
            $this->assertAuthenticatedAs($account, $guard);

            $logoutResponse = $this->post(route($logoutRoute));

            $logoutResponse
                ->assertRedirect(route($expectedLoginRoute))
                ->assertSessionHas('status', 'You have been logged out successfully.')
                ->assertHeader('Clear-Site-Data', '"cache"');

            $this->assertStringContainsString(
                'no-store',
                (string) $logoutResponse->headers->get('Cache-Control'),
                "The {$guard} logout response must not be cached.",
            );
            $this->assertGuest($guard);

            // This represents the network request made when a restored history
            // page is reloaded by protected-page-history.js.
            $this->get(route($protectedRoute))->assertRedirect(route('landing'));
        }
    }

    public function test_normal_guest_navigation_still_redirects_to_the_portal_login(): void
    {
        $redirects = [
            ['dashboard', 'login'],
            ['student.dashboard', 'student.login'],
            ['instructor.dashboard', 'instructor.login'],
            ['office.dashboard', 'office.login'],
            ['registrar.dashboard', 'registrar.login'],
            ['treasurer.dashboard', 'treasurer.login'],
        ];

        foreach ($redirects as [$protectedRoute, $loginRoute]) {
            $this->get(route($protectedRoute))->assertRedirect(route($loginRoute));
        }
    }

    public function test_logging_out_one_portal_keeps_another_portal_signed_in(): void
    {
        $student = StudentAccount::create([
            'student_id' => 'MULTI-STUDENT-001',
            'firstname' => 'Multi',
            'lastname' => 'Student',
            'email' => 'multi-student@example.test',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);
        $instructor = Instructor::create([
            'instructor_id' => 'MULTI-INSTRUCTOR-001',
            'firstname' => 'Multi',
            'lastname' => 'Instructor',
            'email' => 'multi-instructor@example.test',
            'password' => 'Strong-Password-123!',
            'department' => 'BSIT',
        ]);

        $this->actingAs($student, 'student')->actingAs($instructor, 'instructor');

        $this->post(route('student.logout'))
            ->assertRedirect(route('student.login'));

        $this->assertGuest('student');
        $this->assertAuthenticatedAs($instructor, 'instructor');

        // Visiting the still-active portal must not remove the Student portal's
        // history marker or its own authenticated session.
        $this->get(route('instructor.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($instructor, 'instructor');
        $this->get(route('student.dashboard'))->assertRedirect(route('landing'));
    }

    public function test_admin_logout_removes_only_admin_session_data(): void
    {
        $admin = MainAdmin::create([
            'name' => 'Multi Admin',
            'email' => 'multi-admin@example.test',
            'password' => 'Strong-Password-123!',
        ]);
        $student = StudentAccount::create([
            'student_id' => 'MULTI-STUDENT-002',
            'firstname' => 'Still',
            'lastname' => 'Signed In',
            'email' => 'multi-student-two@example.test',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'B',
            'student_type' => 'Regular',
        ]);

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->actingAs($admin, 'admin')->actingAs($student, 'student');

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('admin_id')
            ->assertSessionMissing('admin_name')
            ->assertSessionMissing('admin_email');

        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_authenticated_pages_are_not_cached_and_reload_if_restored_from_history(): void
    {
        $student = StudentAccount::create([
            'student_id' => 'CACHE-STUDENT-001',
            'firstname' => 'Cache',
            'lastname' => 'Student',
            'email' => 'cache-student@example.test',
            'password' => 'Strong-Password-123!',
            'program' => 'BSIT',
            'year_level' => '4',
            'section' => 'A',
            'student_type' => 'Regular',
        ]);

        $response = $this->withSession([
            'login_success' => 'Login successful. Welcome to the Student panel.',
        ])->actingAs($student, 'student')->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSee('js/protected-page-history.js', false)
            ->assertSee('feedbackModalOverlay', false)
            ->assertSee('Login Successful', false)
            ->assertSee('js/auth-feedback-modals.js', false);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $historyScript = file_get_contents(public_path('js/protected-page-history.js'));
        $this->assertIsString($historyScript);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $historyScript);
        $this->assertStringContainsString('event.persisted', $historyScript);
        $this->assertStringContainsString('window.location.reload()', $historyScript);

        foreach ([
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/mainAdmin/layouts/admin.blade.php'),
        ] as $panelLayout) {
            $layout = file_get_contents($panelLayout);
            $this->assertIsString($layout);
            $this->assertStringContainsString("@include('partials.action-feedback-modal')", $layout);
            $this->assertStringContainsString('js/auth-feedback-modals.js', $layout);
        }

        $feedbackModal = file_get_contents(resource_path('views/partials/action-feedback-modal.blade.php'));
        $this->assertIsString($feedbackModal);
        $this->assertStringContainsString('backdrop-filter: blur(32px) saturate(170%)', $feedbackModal);
        $this->assertStringContainsString("session('login_success')", $feedbackModal);
    }
}
