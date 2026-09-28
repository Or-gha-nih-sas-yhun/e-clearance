<?php

namespace Tests\Feature;

use App\Models\MainAdmin;
use App\Models\StudentAccount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Main Admin activity page reads security_audit_logs and nothing else. These
 * tests cover the three things that page has to get right: the device is derived
 * from the stored user agent, filters narrow the trail, and a database that never
 * ran the audit migration renders an explanation instead of a 500.
 */
class AdminActivityLogTest extends TestCase
{
    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    private const ANDROID_APP = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36 MCCStudentAndroid/1.4';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['security_audit_logs', 'student_account', 'main_admin'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('main_admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
        });
        Schema::create('student_account', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('program');
            $table->string('year_level');
            $table->string('section');
        });
        $this->createAuditTable();
    }

    public function test_activity_page_reports_the_account_device_and_time_of_each_event(): void
    {
        StudentAccount::create([
            'student_id' => '2023-0001', 'firstname' => 'Ana', 'lastname' => 'Cruz',
            'email' => 'ana@example.com', 'password' => 'secret',
            'program' => 'BSIT', 'year_level' => '3', 'section' => 'A',
        ]);

        $this->log('authentication.login', 'student', '2023-0001', self::ANDROID_APP, '10.0.0.5');
        $this->log('clearance.status_changed', 'registrar', 'RG-0001', self::CHROME, '10.0.0.9');

        $response = $this->actingAs($this->admin(), 'admin')->get(route('activity.index'));

        $response->assertOk();
        $response->assertSee('Ana Cruz');
        $response->assertSee('Signed in');
        $response->assertSee('MCC Student App on Android');
        $response->assertSee('Chrome on Windows');
        $response->assertSee('Changed a clearance status');
        $response->assertSee('10.0.0.5');
        $response->assertSee(Carbon::now()->format('M j, Y'));
    }

    public function test_metrics_count_accounts_devices_and_rejected_sign_ins(): void
    {
        $this->log('authentication.login', 'student', '2023-0001', self::ANDROID_APP);
        $this->log('authentication.login', 'student', '2023-0001', self::CHROME);
        $this->log('authentication.failed', 'office', 'AP-1', self::CHROME);
        $this->log('authentication.locked', 'office', 'AP-1', self::CHROME);

        $metrics = collect($this->actingAs($this->admin(), 'admin')
            ->get(route('activity.index'))->assertOk()->viewData('metrics'))
            ->keyBy('label');

        $this->assertSame('4', $metrics['Recorded activities']['value']);
        $this->assertSame('2', $metrics['Accounts active']['value']);
        $this->assertSame('2', $metrics['Devices used']['value']);
        $this->assertSame('2', $metrics['Sign-ins today']['value']);
        $this->assertSame('2', $metrics['Rejected sign-ins']['value']);
    }

    public function test_portal_category_and_device_filters_narrow_the_trail(): void
    {
        $this->log('authentication.login', 'student', '2023-0001', self::ANDROID_APP);
        $this->log('clearance.status_changed', 'treasurer', 'TR-1', self::CHROME);

        $admin = $this->admin();

        $this->assertSame(
            ['authentication.login'],
            $this->events($admin, ['portal' => 'student']),
        );
        $this->assertSame(
            ['clearance.status_changed'],
            $this->events($admin, ['category' => 'clearance']),
        );
        $this->assertSame(
            ['authentication.login'],
            $this->events($admin, ['device' => 'app']),
        );
        $this->assertSame(
            ['clearance.status_changed'],
            $this->events($admin, ['device' => 'desktop']),
        );
    }

    public function test_date_range_filter_excludes_older_activity(): void
    {
        $this->log('authentication.login', 'student', '2023-0001', self::CHROME, '10.0.0.1', Carbon::now()->subMonths(2));
        $this->log('authentication.logout', 'student', '2023-0001', self::CHROME);

        $this->assertSame(
            ['authentication.logout'],
            $this->events($this->admin(), ['from' => Carbon::now()->subDay()->toDateString()]),
        );
    }

    public function test_search_matches_account_id_and_ip_address(): void
    {
        $this->log('authentication.login', 'student', '2023-0001', self::CHROME, '10.0.0.1');
        $this->log('authentication.login', 'student', '2023-0002', self::CHROME, '192.168.1.4');

        $admin = $this->admin();

        $this->assertSame(1, count($this->events($admin, ['search' => '2023-0002'])));
        $this->assertSame(1, count($this->events($admin, ['search' => '192.168.1.4'])));
    }

    public function test_activity_page_shows_browser_location_when_it_was_shared(): void
    {
        DB::table('security_audit_logs')->insert([
            'event' => 'authentication.login',
            'actor_guard' => 'student',
            'actor_id' => '2023-0001',
            'metadata' => json_encode(['location' => [
                'latitude' => 11.26654,
                'longitude' => 123.73321,
                'accuracy_meters' => 42,
            ]]),
            'user_agent' => self::CHROME,
            'ip_address' => '10.0.0.5',
            'created_at' => Carbon::now()->toDateTimeString(),
        ]);

        $this->actingAs($this->admin(), 'admin')->get(route('activity.index'))
            ->assertOk()
            ->assertSee('11.26654, 123.73321')
            ->assertSee('Within 42 m')
            ->assertSee('https://www.openstreetmap.org/', false);
    }

    public function test_metric_cards_are_styled_by_the_shared_admin_stylesheet(): void
    {
        // These rules used to sit only in dashboard.blade.php's @push('styles'), so any
        // other screen reusing the markup rendered a bare, undersized <i> where the
        // coloured symbol tile belongs. They have to stay shared for the cards to match.
        $css = file_get_contents(public_path('css/main_admin_portal.css'));
        $dashboard = file_get_contents(resource_path('views/mainAdmin/dashboard.blade.php'));

        foreach (['.admin-metrics', '.admin-metric', '.metric-symbol', '.symbol-blue', '.panel-heading'] as $selector) {
            $this->assertStringContainsString('body.main-admin-portal '.$selector, $css);
        }

        // ...and must not be redefined in the dashboard, where they would drift.
        $this->assertStringNotContainsString('.metric-symbol {', $dashboard);
        $this->assertStringNotContainsString('.admin-metric {', $dashboard);
    }

    public function test_activity_cards_use_the_same_markup_as_the_dashboard_cards(): void
    {
        $this->log('authentication.login', 'student', '2023-0001', self::CHROME);

        $html = $this->actingAs($this->admin(), 'admin')->get(route('activity.index'))->assertOk()->getContent();
        $dashboardMarkup = file_get_contents(resource_path('views/mainAdmin/dashboard.blade.php'));

        $this->assertStringContainsString('<div class="admin-metrics">', $html);
        $this->assertStringContainsString('class="metric-card admin-metric"', $html);
        $this->assertStringContainsString('class="metric-symbol symbol-blue', $html);
        // The same class trio the dashboard uses, so one stylesheet covers both.
        $this->assertStringContainsString('class="metric-card admin-metric"', $dashboardMarkup);
    }

    public function test_page_explains_itself_when_the_audit_table_is_missing(): void
    {
        Schema::dropIfExists('security_audit_logs');

        $response = $this->actingAs($this->admin(), 'admin')->get(route('activity.index'));

        $response->assertOk();
        $response->assertSee('security_audit_logs');
        $response->assertSee('Activity tracking is not set up on this database');
    }

    public function test_activity_page_is_closed_to_unauthenticated_visitors(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }

    /** @return array<int, string> */
    private function events(MainAdmin $admin, array $query): array
    {
        return $this->actingAs($admin, 'admin')
            ->get(route('activity.index', $query))
            ->assertOk()
            ->viewData('activities')
            ->pluck('event')
            ->all();
    }

    private function log(
        string $event,
        string $guard,
        string $actorId,
        string $userAgent,
        string $ip = '10.0.0.1',
        ?Carbon $at = null,
    ): void {
        // Inserted through the query builder because created_at is not fillable on
        // the append-only model, and these rows need a controlled timestamp.
        DB::table('security_audit_logs')->insert([
            'event' => $event,
            'actor_guard' => $guard,
            'actor_id' => $actorId,
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'created_at' => ($at ?? Carbon::now())->toDateTimeString(),
        ]);
    }

    private function admin(): MainAdmin
    {
        return MainAdmin::create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => 'secret',
        ]);
    }

    private function createAuditTable(): void
    {
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
}
