<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_and_register(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_user_can_register_and_is_authenticated(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_new_account_is_not_sent_to_an_admin_page_it_visited_as_a_guest(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');
    }

    public function test_sign_in_still_returns_to_the_page_the_guest_wanted(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $this->get('/my-account/tickets')->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect('/my-account/tickets');
    }

    public function test_user_can_login_and_login_fails_with_bad_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_become_organizer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/organizations', ['name' => 'Kampala Events Co.'])
            ->assertRedirect('/dashboard');

        $org = Organization::first();
        $this->assertNotNull($org);
        $this->assertSame($user->id, $org->owner_id);
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertTrue($user->fresh()->isOrganizer());
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_rbac_roles_resolve_correctly(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/organizations', ['name' => 'Acme']);
        $org = Organization::first();

        $this->assertSame(OrganizationRole::Owner, $owner->fresh()->roleIn($org));
        $this->assertTrue($owner->fresh()->canManageOrganization($org));

        $stranger = User::factory()->create();
        $this->assertNull($stranger->roleIn($org));
        $this->assertFalse($stranger->canManageOrganization($org));
    }

    public function test_five_wrong_passwords_lock_the_account_on_that_device(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'], ['REMOTE_ADDR' => '10.0.0.1']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'], ['REMOTE_ADDR' => '10.0.0.1'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login_throttled', 'ip_address' => '10.0.0.1']);
    }

    public function test_guessing_from_many_ips_locks_the_account_for_fifteen_minutes(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        foreach (range(1, 20) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'], ['REMOTE_ADDR' => "10.0.1.{$attempt}"]);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'], ['REMOTE_ADDR' => '10.0.2.1'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 900 seconds.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.account_locked']);
    }

    public function test_successful_login_resets_the_failed_attempt_count(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $failFromManyIps = function (string $subnet) use ($user): void {
            foreach (range(1, 19) as $attempt) {
                $this->post('/login', ['email' => $user->email, 'password' => 'wrong'], ['REMOTE_ADDR' => "{$subnet}.{$attempt}"]);
            }
        };

        $failFromManyIps('10.0.3');
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'], ['REMOTE_ADDR' => '10.0.9.1']);
        $this->post('/logout');
        $failFromManyIps('10.0.4');

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'], ['REMOTE_ADDR' => '10.0.9.2'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $this->post('/register', [
            'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
