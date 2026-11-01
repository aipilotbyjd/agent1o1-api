<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;

it('records a sign-in with the device that made it', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Desktop/9.9'])
        ->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!'])
        ->assertOk();

    $event = $user->authEvents()->where('event', AuthEvent::LoggedIn->value)->firstOrFail();

    expect($event->ip_address)->toBe('203.0.113.7')
        ->and($event->user_agent)->toBe('Desktop/9.9')
        ->and($event->context['new_device'])->toBeTrue();
});

it('alerts on a sign-in from an unrecognised device, and stays quiet on a familiar one', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $signIn = fn (string $ip, string $agent) => $this
        ->withServerVariables(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $agent])
        ->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!']);

    $signIn('203.0.113.7', 'Desktop/9.9')->assertOk();

    Notification::assertSentToTimes($user, SecurityAlertNotification::class, 1);

    // Same device again: no second alert.
    $signIn('203.0.113.7', 'Desktop/9.9')->assertOk();

    Notification::assertSentToTimes($user, SecurityAlertNotification::class, 1);

    // Somewhere new: alert again.
    $signIn('198.51.100.2', 'Laptop/1.0')->assertOk();

    Notification::assertSentToTimes($user, SecurityAlertNotification::class, 2);
});

it('records failed sign-ins against an address with no account', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])
        ->assertStatus(422);

    $event = AuthEventLog::query()->where('event', AuthEvent::LoginFailed->value)->firstOrFail();

    expect($event->user_id)->toBeNull()
        ->and($event->email)->toBe('nobody@example.com');
});

it('exposes the account\'s own security log, newest first', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $this->withToken($token)->postJson('/api/v1/auth/change-password', [
        'current_password' => 'Password1!',
        'password' => 'BrandNew1!',
        'password_confirmation' => 'BrandNew1!',
    ])->assertNoContent();

    $response = $this->withToken($token)
        ->getJson('/api/v1/auth/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'event', 'label', 'ip_address', 'user_agent', 'created_at']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    $events = collect($response->json('data'))->pluck('event');

    expect($events->first())->toBe(AuthEvent::PasswordChanged->value)
        ->and($events)->toContain(AuthEvent::LoggedIn->value);

    expect($response->json('data.0.label'))->toBe('Password changed');
});

it('never shows one account the security log of another', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $other = User::factory()->create(['email' => 'john@example.com']);

    AuthEventLog::query()->create([
        'user_id' => $other->id,
        'email' => $other->email,
        'event' => AuthEvent::LoggedIn,
        'ip_address' => '203.0.113.1',
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/auth/events')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuses the security log to a guest', function () {
    $this->getJson('/api/v1/auth/events')->assertUnauthorized();
});

it('caps the page size so the log cannot be dumped in one call', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/v1/auth/events?per_page=5000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('records registration', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->authEvents()->pluck('event')->all())->toContain(AuthEvent::Registered);
});
