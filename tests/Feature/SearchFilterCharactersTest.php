<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizeSearchFilters;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class SearchFilterCharactersTest extends TestCase
{
    public function test_search_queries_keep_letters_numbers_spaces_and_hyphens_only(): void
    {
        $request = Request::create('/search', 'GET', [
            'search' => 'Ana-María 2023-0387@#!',
            'program' => 'Arts & Sciences',
        ]);

        (new NormalizeSearchFilters)->handle($request, fn () => response('ok'));

        $this->assertSame('Ana-María 2023-0387', $request->query('search'));
        $this->assertSame('Arts & Sciences', $request->query('program'));
    }

    public function test_text_search_filters_with_other_names_follow_the_same_rule(): void
    {
        $treasurers = Request::create('/mainAdmin/treasurers', 'GET', [
            'department' => 'BSIT@',
            'program' => 'BS-IT#',
            'year_level' => '4!',
            'section' => '4 - EAST_',
            'type' => 'department',
        ]);
        $treasurers->setRouteResolver(fn () => (new Route('GET', '/mainAdmin/treasurers', fn () => null))->name('treasurers.index'));

        (new NormalizeSearchFilters)->handle($treasurers, fn () => response('ok'));

        $this->assertSame('BSIT', $treasurers->query('department'));
        $this->assertSame('BS-IT', $treasurers->query('program'));
        $this->assertSame('4', $treasurers->query('year_level'));
        $this->assertSame('4 - EAST', $treasurers->query('section'));
        $this->assertSame('department', $treasurers->query('type'));

        $assignments = Request::create('/mainAdmin/assignments', 'GET', ['section' => '4-East!']);
        $assignments->setRouteResolver(fn () => (new Route('GET', '/mainAdmin/assignments', fn () => null))->name('assignments.index'));
        (new NormalizeSearchFilters)->handle($assignments, fn () => response('ok'));

        $this->assertSame('4-East', $assignments->query('section'));
    }

    public function test_non_string_search_values_cannot_bypass_the_rule(): void
    {
        $request = Request::create('/search', 'GET', ['search' => ['bad!']]);

        (new NormalizeSearchFilters)->handle($request, fn () => response('ok'));

        $this->assertSame('', $request->query('search'));
    }

    public function test_shared_input_script_covers_named_and_unnamed_search_fields(): void
    {
        $script = (string) file_get_contents(public_path('js/input-constraints.js'));

        $this->assertStringContainsString('input[type="search"], input[name="search"]', $script);
        $this->assertStringContainsString('/[^\\p{L}\\p{N} -]/gu', $script);
        $this->assertStringContainsString("document.addEventListener('input'", $script);
    }
}
