<?php

namespace Tests\Feature;

use App\Models\AdminPersonnel;
use App\Models\Instructor;
use App\Models\Notification;
use App\Models\Registrar;
use App\Models\StudentAccount;
use App\Models\Treasurer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notification only reaches its portal when the identifier written at creation
 * matches the one the reading portal looks up. Those are two different pieces of
 * code per portal, so this walks every one of them end to end.
 */
class PortalNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** Each portal, its guard, and the column its notifications are addressed by. */
    public static function portals(): array
    {
        return [
            'student' => ['student', '2026-0001'],
            'instructor' => ['instructor', 'INS-1'],
            'office' => ['office', 'AP-1'],
            'registrar' => ['registrar', 'REG-1'],
            'treasurer' => ['treasurer', 'TR-1'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_every_portal_receives_the_notifications_addressed_to_it(string $guard, string $identifier): void
    {
        $account = $this->account($guard, $identifier);

        Notification::create([
            'user_id' => $identifier,
            'recipient_role' => $guard,
            'message' => "Hello {$guard}",
            'notif_type' => 'clearance',
        ]);

        $response = $this->actingAs($account, $guard)
            ->getJson(route('notifications.api', ['guard' => $guard]))
            ->assertOk();

        $response->assertJsonPath('unread', 1);
        $this->assertSame("Hello {$guard}", $response->json('notifications.0.message'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_a_portal_never_sees_another_portals_notifications(string $guard, string $identifier): void
    {
        $account = $this->account($guard, $identifier);

        // Same identifier, every other role: none of these belong to this portal.
        foreach (array_keys(self::portals()) as $otherGuard) {
            if ($otherGuard === $guard) {
                continue;
            }
            Notification::create([
                'user_id' => $identifier,
                'recipient_role' => $otherGuard,
                'message' => "For {$otherGuard}",
            ]);
        }

        $this->actingAs($account, $guard)
            ->getJson(route('notifications.api', ['guard' => $guard]))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('notifications', []);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_marking_all_read_clears_only_this_portals_unread_count(string $guard, string $identifier): void
    {
        $account = $this->account($guard, $identifier);

        Notification::create(['user_id' => $identifier, 'recipient_role' => $guard, 'message' => 'Mine']);
        Notification::create(['user_id' => $identifier, 'recipient_role' => 'student', 'message' => 'Someone else']);

        $this->actingAs($account, $guard)
            ->postJson(route('notifications.api.readAll', ['guard' => $guard]))
            ->assertOk();

        $this->actingAs($account, $guard)
            ->getJson(route('notifications.api', ['guard' => $guard]))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        if ($guard !== 'student') {
            $this->assertDatabaseHas('notifications', [
                'recipient_role' => 'student', 'message' => 'Someone else', 'is_read' => 0,
            ]);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portals')]
    public function test_a_portal_cannot_delete_another_portals_notification(string $guard, string $identifier): void
    {
        $account = $this->account($guard, $identifier);

        $foreign = Notification::create([
            'user_id' => $identifier,
            'recipient_role' => $guard === 'student' ? 'instructor' : 'student',
            'message' => 'Not yours',
        ]);

        $this->actingAs($account, $guard)
            ->deleteJson(route('notifications.api.destroy', ['notification' => $foreign->id, 'guard' => $guard]))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);
    }

    public function test_the_signed_in_portal_wins_over_a_guard_named_in_the_query_string(): void
    {
        $student = $this->account('student', '2026-0001');
        $this->account('instructor', 'INS-1');

        Notification::create(['user_id' => '2026-0001', 'recipient_role' => 'student', 'message' => 'Student only']);
        Notification::create(['user_id' => 'INS-1', 'recipient_role' => 'instructor', 'message' => 'Instructor only']);

        // Only the student is signed in, so asking as the instructor must not
        // hand over the instructor's notifications.
        $response = $this->actingAs($student, 'student')
            ->getJson(route('notifications.api', ['guard' => 'instructor']))
            ->assertOk();

        $this->assertNotContains('Instructor only', array_column($response->json('notifications'), 'message'));
    }

    public function test_every_holder_of_an_office_is_notified_not_just_the_first(): void
    {
        $student = $this->account('student', '2026-0001');

        // Two people staff the same office.
        foreach (['AP-LIB-1', 'AP-LIB-2'] as $index => $id) {
            AdminPersonnel::create([
                'personnel_id' => $id, 'firstname' => 'Lib', 'lastname' => "Staff{$index}",
                'email' => "lib{$index}@example.test", 'password' => 'PersonnelPassword1!', 'role' => 'library',
            ]);
        }

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-office'), ['office_role' => 'library'])
            ->assertSessionHasNoErrors();

        foreach (['AP-LIB-1', 'AP-LIB-2'] as $id) {
            // Both staff must be told a request is waiting, not only the first.
            $this->assertDatabaseHas('notifications', ['user_id' => $id, 'recipient_role' => 'office']);
        }
    }

    public function test_every_matching_treasurer_is_notified(): void
    {
        $student = $this->account('student', '2026-0001');

        foreach (['TR-A', 'TR-B'] as $index => $id) {
            Treasurer::create([
                'treasurer_id' => $id, 'firstname' => 'Tre', 'lastname' => "Asurer{$index}",
                'email' => "tre{$index}@example.test", 'password' => 'TreasurerPassword1!',
                'treasurer_type' => 'section', 'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
            ]);
        }

        $this->actingAs($student, 'student')
            ->post(route('student.clearance.submit-office'), ['office_role' => 'section treasurer'])
            ->assertSessionHasNoErrors();

        foreach (['TR-A', 'TR-B'] as $id) {
            $this->assertDatabaseHas('notifications', ['user_id' => $id, 'recipient_role' => 'treasurer']);
        }
    }

    private function account(string $guard, string $identifier)
    {
        return match ($guard) {
            'student' => StudentAccount::create([
                'student_id' => $identifier, 'firstname' => 'Test', 'lastname' => 'Student',
                'email' => 'student@example.test', 'password' => 'StudentPassword1!',
                'program' => 'BSIT', 'year_level' => '1', 'section' => 'A',
            ]),
            'instructor' => Instructor::create([
                'instructor_id' => $identifier, 'firstname' => 'Test', 'lastname' => 'Instructor',
                'email' => 'instructor@example.test', 'password' => 'InstructorPassword1!', 'department' => 'BSIT',
            ]),
            'office' => AdminPersonnel::create([
                'personnel_id' => $identifier, 'firstname' => 'Test', 'lastname' => 'Personnel',
                'email' => 'personnel@example.test', 'password' => 'PersonnelPassword1!', 'role' => 'library',
            ]),
            'registrar' => Registrar::create([
                'registrar_id' => $identifier, 'firstname' => 'Test', 'lastname' => 'Registrar',
                'email' => 'registrar@example.test', 'password' => 'RegistrarPassword1!', 'role' => 'registrar',
            ]),
            'treasurer' => Treasurer::create([
                'treasurer_id' => $identifier, 'firstname' => 'Test', 'lastname' => 'Treasurer',
                'email' => 'treasurer@example.test', 'password' => 'TreasurerPassword1!',
                'treasurer_type' => 'department', 'department' => 'BSIT',
            ]),
        };
    }
}
