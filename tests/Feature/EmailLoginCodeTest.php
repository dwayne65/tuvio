<?php

namespace Tests\Feature;

use App\Domain\Auth\PendingLogin;
use App\Domain\Auth\TwoFactorAuthenticator;
use App\Mail\LoginCodeMail;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailLoginCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    private function memberOfPlan(string $planSlug): User
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $org = Organization::create(['owner_id' => $user->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner', 'accepted_at' => now()]);

        $plan = SubscriptionPlan::where('slug', $planSlug)->firstOrFail();
        $org->subscription()->create([
            'subscription_plan_id' => $plan->id, 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => $plan->isFree() ? null : now()->addMonth(),
            'price_minor' => $plan->price_minor, 'currency' => 'UGX', 'interval' => 'monthly',
        ]);

        return $user;
    }

    private function sentCode(User $user): string
    {
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($user, &$code) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        return $code;
    }

    public function test_professional_member_signs_in_with_password_and_emailed_code(): void
    {
        $user = $this->memberOfPlan('professional');

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('login.email-code'))
            ->assertCookieMissing(PendingLogin::REMEMBERED_EMAIL_COOKIE);
        $this->assertGuest();

        $this->post('/login/email-code', ['code' => $this->sentCode($user)])
            ->assertRedirect(route('dashboard'))
            ->assertCookie(PendingLogin::REMEMBERED_EMAIL_COOKIE, $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_starter_member_signs_in_without_an_emailed_code(): void
    {
        $user = $this->memberOfPlan('starter');

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNotSent(LoginCodeMail::class);
    }

    public function test_admin_needs_the_emailed_code_and_then_the_authenticator_code(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('Password123!')]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!'])->assertRedirect(route('login.email-code'));

        // The authenticator step cannot be used to skip the emailed code.
        $this->get('/two-factor-challenge')->assertRedirect(route('login.email-code'));
        $totp = app(TwoFactorAuthenticator::class)->codeAt($admin->two_factor_secret, intdiv(now()->getTimestamp(), 30));
        $this->post('/two-factor-challenge', ['code' => $totp])->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login/email-code', ['code' => $this->sentCode($admin)])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $totp])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_codes_are_rejected_and_too_many_burn_the_code(): void
    {
        $user = $this->memberOfPlan('enterprise');
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!']);
        $code = $this->sentCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, (int) config('ticketflow.email_login_codes.max_attempts')) as $attempt) {
            $this->post('/login/email-code', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->post('/login/email-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_an_expired_code_does_not_work(): void
    {
        $user = $this->memberOfPlan('professional');
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!']);
        $code = $this->sentCode($user);

        $this->travel((int) config('ticketflow.email_login_codes.ttl_minutes') + 1)->minutes();

        $this->post('/login/email-code', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_admin_without_an_authenticator_app_reaches_the_admin_area_with_the_emailed_code(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('Password123!'), 'is_admin' => true]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123!'])->assertRedirect(route('login.email-code'));
        $this->post('/login/email-code', ['code' => $this->sentCode($admin)])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')->assertOk();
    }

    public function test_admin_session_that_skipped_the_emailed_code_must_sign_in_again(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
