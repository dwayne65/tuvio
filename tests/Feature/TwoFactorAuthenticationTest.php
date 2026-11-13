<?php

namespace Tests\Feature;

use App\Domain\Auth\TwoFactorAuthenticator;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** These tests cover the authenticator step alone; the emailed code before it is covered in EmailLoginCodeTest. */
    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.email_login_codes.enabled' => false]);
    }

    private function currentCode(User $user): string
    {
        return app(TwoFactorAuthenticator::class)->codeAt($user->two_factor_secret, intdiv(now()->getTimestamp(), 30));
    }

    private function adminWithPassword(): User
    {
        return User::factory()->admin()->create(['password' => bcrypt('Password123!')]);
    }

    public function test_correct_password_for_two_factor_account_asks_for_code_without_signing_in(): void
    {
        $admin = $this->adminWithPassword();

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_valid_authenticator_code_completes_sign_in(): void
    {
        $this->freezeTime();
        $admin = $this->adminWithPassword();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);

        $this->post('/two-factor-challenge', ['code' => $this->currentCode($admin)])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $admin = $this->adminWithPassword();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'That code is invalid. Please try again.']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'auth.two_factor_failed']);
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $this->freezeTime();
        $admin = $this->adminWithPassword();
        $code = $this->currentCode($admin);
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->post('/logout');

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_repeated_wrong_codes_lock_out_and_discard_the_pending_sign_in(): void
    {
        $this->freezeTime();
        $admin = $this->adminWithPassword();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }
        $this->post('/two-factor-challenge', ['code' => $this->currentCode($admin)])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_recovery_code_signs_in_once_and_is_then_spent(): void
    {
        $admin = $this->adminWithPassword();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);

        $this->post('/two-factor-challenge', ['recovery_code' => 'abcde-12345'])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(['fghij-67890'], json_decode($admin->fresh()->two_factor_recovery_codes, true));
    }

    public function test_pending_sign_in_expires_after_five_minutes(): void
    {
        $this->freezeTime();
        $admin = $this->adminWithPassword();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!']);
        $this->travel(6)->minutes();

        $this->post('/two-factor-challenge', ['code' => $this->currentCode($admin)])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_challenge_without_a_password_step_redirects_to_login(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_admin_without_any_second_step_is_sent_to_settings_when_email_codes_are_off(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error', 'Turn on two-factor authentication to access the admin area.');
    }

    public function test_admin_with_two_factor_can_open_admin_area(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_user_can_enrol_by_confirming_a_code_from_the_app(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $this->actingAs($user)->post('/settings/two-factor', ['password' => 'Password123!'])->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->post('/settings/two-factor/confirm', ['code' => $this->currentCode($user->fresh())])
            ->assertSessionHas('twoFactorRecoveryCodes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_enrolment_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $this->actingAs($user)->post('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_wrong_confirmation_code_leaves_two_factor_off(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $this->actingAs($user)->post('/settings/two-factor', ['password' => 'Password123!']);

        $this->post('/settings/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_turning_off_requires_the_current_password(): void
    {
        $admin = $this->adminWithPassword();

        $this->actingAs($admin)->delete('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->delete('/settings/two-factor', ['password' => 'Password123!']);
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_is_admin_cannot_be_set_through_profile_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', ['name' => 'Me', 'email' => $user->email, 'is_admin' => true]);

        $this->assertFalse($user->fresh()->is_admin);
    }
}
