<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class MainAdminRedesignTest extends TestCase
{
    public function test_protected_layout_uses_the_main_admin_theme_and_admin_identity(): void
    {
        $layoutPath = resource_path('views/mainAdmin/layouts/admin.blade.php');
        $stylesheetPath = public_path('css/main_admin_portal.css');

        $this->assertFileExists($layoutPath);
        $this->assertFileExists($stylesheetPath);

        $source = (string) file_get_contents($layoutPath);

        $this->assertStringContainsString("asset('css/main_admin_portal.css')", $source);
        $this->assertMatchesRegularExpression(
            "/auth\(\s*['\"]admin['\"]\s*\)->user\(\)/",
            $source,
            'The protected Main Admin layout must read its identity from the admin guard.',
        );
        $this->assertMatchesRegularExpression(
            "/route\(\s*['\"]profile\.edit['\"]\s*\)/",
            $source,
            'The protected Main Admin layout must expose the Account Settings route.',
        );

        $stylesheet = (string) file_get_contents($stylesheetPath);
        $this->assertMatchesRegularExpression(
            '/\.main\.sidebar-open\s*\{[^}]*margin-left:\s*0\s*!important;/s',
            $stylesheet,
            'Opening the Main Admin sidebar must not resize or shift the page content.',
        );
    }

    public function test_every_routed_protected_main_admin_view_uses_the_shared_page_header(): void
    {
        $componentPath = resource_path('views/components/main-admin/page-header.blade.php');
        $this->assertFileExists($componentPath);

        $views = $this->protectedMainAdminViews();
        $this->assertNotEmpty($views, 'No protected Main Admin views were discovered from the route table.');

        foreach ($views as $viewName => $viewPath) {
            $this->assertFileExists($viewPath, "The routed [{$viewName}] view does not exist.");

            $source = (string) file_get_contents($viewPath);
            $this->assertMatchesRegularExpression(
                '/<x-main-admin\.page-header(?=[\s>\/])/',
                $source,
                "The routed [{$viewName}] view must use <x-main-admin.page-header>.",
            );
        }
    }

    public function test_every_routed_protected_main_admin_view_compiles(): void
    {
        foreach ($this->protectedMainAdminViews() as $viewName => $viewPath) {
            $compiled = Blade::compileString((string) file_get_contents($viewPath));

            $this->assertNotSame('', trim($compiled), "The routed [{$viewName}] view did not compile.");
        }
    }

    /**
     * Discover controller-backed HTML views instead of maintaining a list that
     * can silently become stale when another protected admin page is routed.
     *
     * @return array<string, string>
     */
    private function protectedMainAdminViews(): array
    {
        $views = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isProtectedMainAdminGetRoute($route)) {
                continue;
            }

            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            $reflection = new ReflectionMethod($controller, $method);
            $lines = file($reflection->getFileName());
            $methodSource = implode('', array_slice(
                $lines ?: [],
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ));

            preg_match_all(
                "/\bview\s*\(\s*['\"](mainAdmin\.[^'\"]+)['\"]/",
                $methodSource,
                $matches,
            );

            foreach ($matches[1] as $viewName) {
                $views[$viewName] = resource_path(
                    'views/'.str_replace('.', '/', $viewName).'.blade.php',
                );
            }
        }

        ksort($views);

        return $views;
    }

    private function isProtectedMainAdminGetRoute(RoutingRoute $route): bool
    {
        return str_starts_with($route->uri(), 'mainAdmin/')
            && in_array('GET', $route->methods(), true)
            && in_array('admin.auth', $route->gatherMiddleware(), true);
    }
}
