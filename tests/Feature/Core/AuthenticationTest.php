<?php

namespace Tests\Feature\Core;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_sign_in_and_is_audited(): void
    {
        $user = $this->userWithRole('General Staff', ['email' => 'staff@ndc.test']);

        $this->post('/login', ['email' => 'staff@ndc.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $this->userWithRole('General Staff', ['email' => 'staff@ndc.test']);

        $this->post('/login', ['email' => 'staff@ndc.test', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('event', 'login_failed')->exists());
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->userWithRole('General Staff', ['email' => 'staff@ndc.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'staff@ndc.test', 'password' => 'wrong']);
        }

        // Even the correct password is refused while throttled.
        $this->post('/login', ['email' => 'staff@ndc.test', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_sign_in(): void
    {
        User::factory()->suspended()->create(['email' => 'off@ndc.test']);

        $this->post('/login', ['email' => 'off@ndc.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_with_open_session_is_signed_out(): void
    {
        $user = $this->userWithRole('General Staff');
        $user->update(['status' => 'suspended']);

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_two_factor_challenge_is_required_when_enabled(): void
    {
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey(32);
        $user = $this->userWithRole('General Staff', ['email' => '2fa@ndc.test']);
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['abcde-fghij']])->save();

        $this->post('/login', ['email' => '2fa@ndc.test', 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor', ['code' => $engine->getCurrentOtp($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->userWithRole('General Staff');
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['abcde-fghij']])->save();
        $service = app(TwoFactorService::class);

        $this->assertTrue($service->attempt($user, 'abcde-fghij'));
        $this->assertFalse($service->attempt($user->fresh(), 'abcde-fghij'));
    }

    public function test_user_who_must_change_password_is_sent_to_security_page(): void
    {
        $user = $this->userWithRole('General Staff', ['must_change_password' => true]);

        $this->actingAs($user)->get('/')->assertRedirect(route('profile.security'));
        $this->actingAs($user)->get(route('profile.security'))->assertOk();
    }

    public function test_password_change_requires_current_password_and_strong_password(): void
    {
        $user = $this->userWithRole('General Staff', ['must_change_password' => true]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'N3w-Secure#Pass', 'password_confirmation' => 'N3w-Secure#Pass',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_password_reset_link_response_does_not_reveal_accounts(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@ndc.test'])
            ->assertSessionHas('status');
    }

    public function test_language_can_be_switched(): void
    {
        $user = $this->userWithRole('General Staff');

        $this->actingAs($user)->post(route('locale.switch'), ['locale' => 'so'])->assertRedirect();
        $this->assertSame('so', $user->fresh()->locale);

        $this->actingAs($user->fresh())->get(route('profile.edit'))->assertSee('lang="so"', false);
    }
}
