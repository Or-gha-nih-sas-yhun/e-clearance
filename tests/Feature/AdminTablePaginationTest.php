<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\Registrar;
use App\Support\ListPageSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every Main Admin listing carries the same footer under its table: a rows-per-page
 * selector, the running count, and labelled Previous / Next.
 */
class AdminTablePaginationTest extends TestCase
{
    use RefreshDatabase;

    /** Every Main Admin page that lists records. */
    public static function listingRoutes(): array
    {
        return [
            'students' => ['students.index'],
            'instructors' => ['instructors.index'],
            'personnel' => ['personnel.index'],
            'registrar' => ['registrar.index'],
            'treasurers' => ['treasurers.index'],
            'subject codes' => ['subjects.index'],
            'sections' => ['sections.index'],
            'assignments' => ['assignments.index'],
            'student registry' => ['student-registry.index'],
            'activity log' => ['activity.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listingRoutes')]
    public function test_every_listing_page_offers_the_same_page_sizes_and_page_controls(string $route): void
    {
        $content = $this->actingAs($this->admin(), 'admin')->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('class="table-pager"', $content, "[{$route}] must render the shared table pager.");
        $this->assertStringContainsString('Previous', $content, "[{$route}] must offer a previous page control.");
        $this->assertStringContainsString('Next', $content, "[{$route}] must offer a next page control.");

        foreach (ListPageSize::OPTIONS as $size) {
            $this->assertStringContainsString(">{$size}</option>", $content, "[{$route}] must offer {$size} rows per page.");
        }
    }

    public function test_the_page_size_links_keep_the_filters_and_reset_the_page(): void
    {
        $content = $this->actingAs($this->admin(), 'admin')
            ->get(route('instructors.index', ['department' => 'BSIT', 'page' => 3]))
            ->assertOk()
            ->getContent();

        // Every size keeps the department, and none of them carries `page` over —
        // a different page size renumbers the pages beneath it.
        foreach (ListPageSize::OPTIONS as $size) {
            $this->assertStringContainsString('department=BSIT&amp;limit='.$size.'"', $content);
        }
        $this->assertStringNotContainsString('limit=10&amp;page=3', $content);
    }

    public function test_a_chosen_page_size_is_honoured_and_a_bogus_one_falls_back(): void
    {
        foreach (range(1, 25) as $index) {
            Registrar::create([
                'registrar_id' => 'REG-'.$index,
                'firstname' => 'Reg',
                'lastname' => 'Number'.$index,
                'email' => "registrar{$index}@example.test",
                'password' => 'RegistrarPassword1!',
                'role' => 'registrar',
            ]);
        }

        $this->actingAs($this->admin(), 'admin')
            ->get(route('registrar.index', ['limit' => 20]))
            ->assertOk()
            ->assertSee('Showing 1–20 of 25 registrars');

        // A URL asking for everything at once is refused, not obeyed.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('registrar.index', ['limit' => 9999]))
            ->assertOk()
            ->assertSee('Showing 1–'.ListPageSize::DEFAULT_SIZE.' of 25 registrars');
    }

    public function test_next_and_previous_move_between_pages(): void
    {
        foreach (range(1, 15) as $index) {
            Registrar::create([
                'registrar_id' => 'REG-'.$index,
                'firstname' => 'Reg',
                'lastname' => 'Number'.$index,
                'email' => "registrar{$index}@example.test",
                'password' => 'RegistrarPassword1!',
                'role' => 'registrar',
            ]);
        }

        $this->actingAs($this->admin(), 'admin')
            ->get(route('registrar.index'))
            ->assertOk()
            ->assertSee('Page 1 of 2')
            ->assertSee(route('registrar.index', ['page' => 2]), false);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('registrar.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('Showing 11–15 of 15 registrars');
    }

    private function admin(): MainAdmin
    {
        return MainAdmin::firstOrCreate(
            ['email' => 'admin-pagination@example.test'],
            ['password' => 'AdminPassword1!'],
        );
    }
}
