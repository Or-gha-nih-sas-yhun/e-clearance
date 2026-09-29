<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_policy_is_publicly_available(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information we collect')
            ->assertSee('How we use information');
    }

    public function test_the_landing_and_login_pages_link_to_the_privacy_policy(): void
    {
        $pages = ['landing', 'login', 'student.login', 'instructor.login', 'office.login', 'registrar.login', 'treasurer.login'];

        foreach ($pages as $page) {
            $this->get(route($page))
                ->assertOk()
                ->assertSee(route('privacy-policy'), false)
                ->assertSee('Privacy Policy');
        }
    }
}
