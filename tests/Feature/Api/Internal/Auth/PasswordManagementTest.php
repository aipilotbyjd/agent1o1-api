<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\RefreshToken;

it('sends a reset link pointing at the frontend', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If that email exists, a reset link has been sent.');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, config('app.frontend_url').'/reset-password?token=');
    });
});

it('does not reveal whether an address has an account', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If that email exists, a reset link has been sent.');

    Notification::assertNothingSent();
});

it('resets the password with a valid token and revokes every session', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!']);

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'jane@example.com',
        'password' => 'BrandNew1!',
        'password_confirmation' => 'BrandNew1!',
    ])->assertOk()->assertJsonPath('message', 'Password reset successfully.');

    expect(Hash::check('BrandNew1!', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->where('revoked', false)->count())->toBe(0);
    expect(RefreshToken::query()->where('revoked', false)->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'BrandNew1!'])
        ->assertOk();
});

it('rejects a reset with a bad token', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'jane@example.com',
        'password' => 'BrandNew1!',
        'password_confirmation' => 'BrandNew1!',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!'])
        ->assertOk();
});

it('changes a password when the current one is given', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!',
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertNoContent();

    expect(Hash::check('BrandNew1!', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change when the current password is wrong', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'not-my-password',
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertStatus(422);

    expect(Hash::check('Password1!', $user->fresh()->password))->toBeTrue();
});

it('enforces the password policy on a change', function () {
    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422);
});

it('keeps the current session but drops the others when asked', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $other = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $current = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $this->withToken($current)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!',
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
            'revoke_other_sessions' => true,
        ])->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($other)->getJson('/api/v1/user')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->withToken($current)->getJson('/api/v1/user')->assertOk();
});

it('alerts the owner and logs the event when a password changes', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'Password1!']);

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!',
            'password' => 'BrandNew1!',
            'password_confirmation' => 'BrandNew1!',
        ])->assertNoContent();

    Notification::assertSentTo(
        $user,
        SecurityAlertNotification::class,
        fn (SecurityAlertNotification $n) => $n->event === AuthEvent::PasswordChanged,
    );

    expect($user->authEvents()->pluck('event')->all())->toContain(AuthEvent::PasswordChanged);
});
