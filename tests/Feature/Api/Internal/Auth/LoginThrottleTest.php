<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Auth\AuthEventLog;
use App\Models\User;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;

function attemptLogin(mixed $test, string $email = 'jane@example.com', string $password = 'wrong-password')
{
    return $test->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

beforeEach(function () {
    // The lockout is what is under test here; the per-minute limiter in front of
    // it would otherwise answer first and mask it.
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('locks the account after five failed attempts', function () {
    $this->freezeTime();
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this)->assertStatus(422);
    }

    attemptLogin($this)->assertStatus(429);

    // Even the right password is refused while the lock holds.
    attemptLogin($this, password: 'Password1!')
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too many failed sign-in attempts. Try again in 1 minute.');
});

it('lets the account back in once the lock expires', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this);
    }

    attemptLogin($this, password: 'Password1!')->assertStatus(429);

    $this->travel(61)->seconds();

    attemptLogin($this, password: 'Password1!')->assertOk();
});

it('lengthens the lock each time it is tripped again', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this);
    }

    $this->travel(61)->seconds();

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this);
    }

    // Second lockout is the 5-minute rung, so a minute is no longer enough.
    $this->travel(61)->seconds();
    attemptLogin($this, password: 'Password1!')->assertStatus(429);

    $this->travel(5)->minutes();
    attemptLogin($this, password: 'Password1!')->assertOk();
});

it('clears the counter after a successful sign-in', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 4) as $attempt) {
        attemptLogin($this);
    }

    attemptLogin($this, password: 'Password1!')->assertOk();

    // The slate is clean, so four more failures still do not lock.
    foreach (range(1, 4) as $attempt) {
        attemptLogin($this)->assertStatus(422);
    }

    attemptLogin($this, password: 'Password1!')->assertOk();
});

it('locks unknown addresses too, so a lockout never confirms an account exists', function () {
    foreach (range(1, 5) as $attempt) {
        attemptLogin($this, 'nobody@example.com')->assertStatus(422);
    }

    attemptLogin($this, 'nobody@example.com')->assertStatus(429);
});

it('tells the owner and logs it when their account locks', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this);
    }

    Notification::assertSentTo(
        $user,
        SecurityAlertNotification::class,
        fn (SecurityAlertNotification $n) => $n->event === AuthEvent::AccountLocked,
    );

    expect(AuthEventLog::query()->where('event', AuthEvent::LoginFailed->value)->count())->toBe(5);
    expect(AuthEventLog::query()->where('event', AuthEvent::AccountLocked->value)->count())->toBe(1);
});

it('keeps one account\'s lockout away from another', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);
    User::factory()->create(['email' => 'john@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this);
    }

    attemptLogin($this, password: 'Password1!')->assertStatus(429);
    attemptLogin($this, 'john@example.com', 'Password1!')->assertOk();
});

it('rate-limits attempts on one address even when none of them fail', function () {
    // Isolated from the lockout on purpose: every one of these succeeds, so the
    // failure counter stays at zero and only the per-email limiter (5/min) can
    // be what stops the sixth.
    $this->withMiddleware(ThrottleRequests::class);

    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    foreach (range(1, 5) as $attempt) {
        attemptLogin($this, password: 'Password1!')->assertOk();
    }

    attemptLogin($this, password: 'Password1!')
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too Many Attempts.');
});
