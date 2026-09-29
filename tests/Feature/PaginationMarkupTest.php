<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The Main Admin tables use one compact pager without a second numbered row. */
class PaginationMarkupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['security_audit_logs', 'main_admin'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('main_admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
        });
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 100);
            $table->string('actor_guard', 30)->nullable();
            $table->string('actor_id', 100)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_paginated_pages_render_only_the_shared_table_controls(): void
    {
        // More than one page of activity so the links actually render.
        foreach (range(1, 30) as $index) {
            DB::table('security_audit_logs')->insert([
                'event' => 'authentication.login',
                'actor_guard' => 'student',
                'actor_id' => '2023-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0 Safari/537.36',
                'ip_address' => '10.0.0.'.$index,
                'created_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        $admin = MainAdmin::create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);

        $html = $this->actingAs($admin, 'admin')->get(route('activity.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="table-pager"', $html);
        $this->assertStringContainsString('Page 1 of 3', $html);
        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('Next', $html);
        $this->assertStringNotContainsString('table-pager-links', $html);
        $this->assertStringNotContainsString('class="pagination', $html);
    }
}
