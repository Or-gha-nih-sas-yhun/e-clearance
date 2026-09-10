<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalOverlayDesignTest extends TestCase
{
    public function test_all_portal_layouts_load_the_shared_overlay_design(): void
    {
        foreach ([
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
            resource_path('views/mainAdmin/layouts/admin.blade.php'),
        ] as $layout) {
            $this->assertStringContainsString(
                "asset('css/portal_overlays.css')",
                file_get_contents($layout),
            );
        }
    }

    /** Main Admin has no notification panel, so only the portals that do are checked. */
    public function test_portals_with_notifications_share_the_same_panel_markup(): void
    {
        foreach ([
            resource_path('views/layouts/portal.blade.php'),
            resource_path('views/instructor/layouts/instructor.blade.php'),
        ] as $layout) {
            $source = file_get_contents($layout);
            $this->assertStringContainsString('panel-close', $source);
            $this->assertStringContainsString('aria-controls="notifPanel"', $source);
        }
    }

    public function test_overlay_styles_are_compact_responsive_and_liquid_glass(): void
    {
        $css = file_get_contents(public_path('css/portal_overlays.css'));

        $this->assertStringContainsString('width: min(390px', $css);
        $this->assertStringContainsString('width:min(540px', $css);
        $this->assertStringContainsString('backdrop-filter: blur(28px)', $css);
        $this->assertStringContainsString('@media(max-width:700px)', $css);
    }

    /**
     * Notifications and chat support were removed from the Main Admin portal.
     * Nothing there ever wrote to either — no notification is addressed to
     * `recipient_role = 'admin'` and nothing inserts into `chat_support` — so
     * both surfaces were permanently empty. This keeps them from creeping back.
     */
    public function test_main_admin_has_no_notification_or_chat_surface(): void
    {
        $source = file_get_contents(resource_path('views/mainAdmin/layouts/admin.blade.php'));

        $this->assertStringNotContainsString('id="notifBtn"', $source);
        $this->assertStringNotContainsString('notifPanel', $source);
        $this->assertStringNotContainsString('loadNotifications', $source);
        $this->assertStringNotContainsString('Chat Support', $source);

        // The sidebar still closes cleanly without the panel it used to close.
        $this->assertStringContainsString('function closeAdminOverlays()', $source);

        foreach (['chat.index', 'notifications.index'] as $removed) {
            $this->assertNull(
                app('router')->getRoutes()->getByName($removed),
                "The [{$removed}] Main Admin route must stay removed.",
            );
        }
    }
}
