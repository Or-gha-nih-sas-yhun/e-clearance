<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PortalEnhancementsTest extends TestCase
{
    public function test_every_authenticated_portal_loads_the_shared_enhancements(): void
    {
        $layouts = [
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/mainAdmin/layouts/admin.blade.php'),
            resource_path('views/mainAdmin/layouts/app.blade.php'),
        ];

        foreach ($layouts as $layout) {
            $source = (string) file_get_contents($layout);

            $this->assertStringContainsString("asset('css/portal-enhancements.css')", $source);
            $this->assertStringContainsString("asset('js/portal-enhancements.js')", $source);
            $this->assertStringContainsString('data-inactivity-timeout="60000"', $source);
        }
    }

    public function test_portal_enhancements_provide_larger_text_keyboard_search_and_idle_logout(): void
    {
        $css = (string) file_get_contents(public_path('css/portal-enhancements.css'));
        $javascript = (string) file_get_contents(public_path('js/portal-enhancements.js'));

        $this->assertStringContainsString('--portal-base-font-size: 17px', $css);
        $this->assertStringContainsString('input[type="search"]', $javascript);
        $this->assertStringContainsString("event.key.toLowerCase() === 'k'", $javascript);
        $this->assertStringContainsString("event.key === '/'", $javascript);
        $this->assertStringContainsString("event.key.toLowerCase() === 'f'", $javascript);
        $this->assertStringContainsString('inactivityLimit - (Date.now() - lastActivity)', $javascript);
        $this->assertStringContainsString("You've been inactive for at least 1 minute", $javascript);
        $this->assertStringContainsString("'Accept': 'application/json'", $javascript);
        $this->assertStringContainsString('portal-inactivity-modal-overlay', $css);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(logoutForm)', $javascript);
    }

    public function test_success_feedback_exposes_sweet_alert_on_portals_and_login_pages(): void
    {
        session()->flash('status', 'password-updated');

        $feedback = view('partials.action-feedback-modal')->render();
        $loginLayout = (string) file_get_contents(resource_path('views/auth/portal-login.blade.php'));

        $this->assertStringContainsString('data-sweet-alert', $feedback);
        $this->assertStringContainsString('window.Swal', $feedback);
        $this->assertStringContainsString('Password updated successfully.', $feedback);
        $this->assertStringContainsString("@include('partials.action-feedback-modal')", $loginLayout);
        $this->assertNotSame('', trim(Blade::compileString($loginLayout)));
    }
}
