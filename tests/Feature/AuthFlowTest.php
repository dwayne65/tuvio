<?php

namespace Tests\Feature;

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

        $this->assertSame('owner', $owner->fresh()->roleIn($org));
        $this->assertTrue($owner->fresh()->canManageOrganization($org));

        $stranger = User::factory()->create();
        $this->assertNull($stranger->roleIn($org));
        $this->assertFalse($stranger->canManageOrganization($org));
    }
}
