<?php

namespace Tests\Feature\Foundation;

use App\Models\LoginActivity;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee(__('auth.sign_in_title'));
    }

    public function test_user_can_sign_in_and_activity_is_recorded(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'officer@example.test', 'password' => 'Correct-Horse-9!']);

        $this->post('/login', ['email' => 'officer@example.test', 'password' => 'Correct-Horse-9!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_activities', ['user_id' => $user->id, 'event' => 'login']);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $this->userWithRole('staff', ['email' => 'officer@example.test', 'password' => 'Correct-Horse-9!']);

        $this->from('/login')->post('/login', ['email' => 'officer@example.test', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_activities', ['email' => 'officer@example.test', 'event' => 'failed']);
    }

    public function test_suspended_user_cannot_sign_in(): void
    {
        $this->userWithRole('staff', ['email' => 'gone@example.test', 'password' => 'Correct-Horse-9!', 'status' => 'suspended']);

        $this->post('/login', ['email' => 'gone@example.test', 'password' => 'Correct-Horse-9!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_suspended_during_session_is_signed_out(): void
    {
        $user = $this->userWithRole('staff');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'suspended']);
        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_repeated_failures_lock_out_the_account_temporarily(): void
    {
        $this->userWithRole('staff', ['email' => 'target@example.test', 'password' => 'Correct-Horse-9!']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'target@example.test', 'password' => 'bad-'.$i]);
        }
        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'target@example.test', 'password' => 'Correct-Horse-9!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(LoginActivity::query()->where('event', 'lockout')->exists());
    }

    public function test_logout_requires_post_and_ends_session(): void
    {
        $user = $this->userWithRole('staff');
        $this->actingAs($user)->get('/logout')->assertStatus(405);
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_reset_request_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();
        $user = $this->userWithRole('staff', ['email' => 'known@example.test']);

        $known = $this->post('/forgot-password', ['email' => 'known@example.test']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        $known->assertSessionHas('status', __('auth.reset_link_sent'));
        $unknown->assertSessionHas('status', __('auth.reset_link_sent'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token_and_policy_enforced(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'known@example.test']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'New-Strong-Pass-1!', 'password_confirmation' => 'New-Strong-Pass-1!'])
            ->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'New-Strong-Pass-1!'])->assertRedirect(route('dashboard'));
    }

    public function test_forced_password_change_blocks_other_pages(): void
    {
        $user = $this->userWithRole('staff', ['password' => 'Old-Strong-Pass-1!', 'must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change'));

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'Old-Strong-Pass-1!',
            'password' => 'New-Strong-Pass-2@',
            'password_confirmation' => 'New-Strong-Pass-2@',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_mfa_enabled_user_must_provide_valid_code(): void
    {
        $secret = Totp::generateSecret();
        $user = $this->userWithRole('staff', ['email' => 'mfa@example.test', 'password' => 'Correct-Horse-9!']);
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => now(), 'mfa_recovery_codes' => ['ABCDE-FGHIJ']])->save();

        $this->post('/login', ['email' => 'mfa@example.test', 'password' => 'Correct-Horse-9!'])->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        $this->post('/login/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/login/verify', ['code' => Totp::code($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_mfa_recovery_code_works_once(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'mfa@example.test', 'password' => 'Correct-Horse-9!']);
        $user->forceFill(['mfa_secret' => Totp::generateSecret(), 'mfa_enabled_at' => now(), 'mfa_recovery_codes' => ['ABCDE-FGHIJ']])->save();

        $this->post('/login', ['email' => 'mfa@example.test', 'password' => 'Correct-Horse-9!']);
        $this->post('/login/verify', ['code' => 'abcde-fghij'])->assertRedirect(route('dashboard'));
        $this->assertSame([], $user->fresh()->mfa_recovery_codes);
    }

    public function test_user_can_enable_mfa_from_profile(): void
    {
        $user = $this->userWithRole('staff');
        $this->actingAs($user)->post('/account/mfa/start')->assertRedirect();
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);

        $this->actingAs($user)->post('/account/mfa', ['code' => Totp::code($secret)])->assertSessionHas('recovery_codes');
        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasMfaEnabled());
        // Secret is stored encrypted, never in plain text.
        $raw = \DB::table('users')->where('id', $user->id)->value('mfa_secret');
        $this->assertNotSame($secret, $raw);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
