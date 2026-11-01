<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use App\Notifications\Auth\ConfirmEmailChangeNotification;
use App\Notifications\Auth\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function emailChangeUrlFor(User $user, string $newEmail): string
{
    return URL::temporarySignedRoute('auth.confirm-email-change', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($newEmail),
    ]);
}

it('stages an email change rather than applying it', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'jane.new@example.com'])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.pending_email', 'jane.new@example.com');

    expect($user->fresh()->email)->toBe('jane@example.com');

    Notification::assertSentOnDemand(ConfirmEmailChangeNotification::class);
});

it('warns the old address as soon as a change is requested', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    Notification::assertSentTo(
        $user,
        SecurityAlertNotification::class,
        fn (SecurityAlertNotification $n) => $n->event === AuthEvent::EmailChangeRequested,
    );
});

it('applies the change once the new address confirms, and signs every device out', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->json('data.tokens.access_token');

    $this->withToken($token)->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    $this->get(emailChangeUrlFor($user, 'jane.new@example.com'))
        ->assertRedirect(config('app.frontend_url').'/settings/account?email_change=confirmed');

    $user->refresh();

    expect($user->email)->toBe('jane.new@example.com')
        ->and($user->pending_email)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->tokens()->where('revoked', false)->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', ['email' => 'jane.new@example.com', 'password' => 'Password1!'])
        ->assertOk();
});

it('tells the old address after the change lands', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    $this->get(emailChangeUrlFor($user, 'jane.new@example.com'));

    Notification::assertSentOnDemand(
        SecurityAlertNotification::class,
        fn (SecurityAlertNotification $n, array $channels, object $notifiable) => $n->event === AuthEvent::EmailChanged
            && $notifiable->routes['mail'] === 'jane@example.com',
    );
});

it('refuses a confirmation link whose address no longer matches the request', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    // A link minted for an address the user never asked for.
    $this->get(emailChangeUrlFor($user, 'attacker@example.com'))
        ->assertRedirect(config('app.frontend_url').'/settings/account?email_change=invalid');

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('refuses a confirmation once the signature has expired', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    $url = emailChangeUrlFor($user, 'jane.new@example.com');

    $this->travel(61)->minutes();

    $this->get($url)->assertRedirect(config('app.frontend_url').'/settings/account?email_change=expired');

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('refuses an address that another account has taken in the meantime', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    User::factory()->create(['email' => 'jane.new@example.com']);

    $this->get(emailChangeUrlFor($user, 'jane.new@example.com'))
        ->assertRedirect(config('app.frontend_url').'/settings/account?email_change=invalid');

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('rejects an address already registered to someone else', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'taken@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'That email address is already in use.');
});

it('rejects an address another account is already trying to move to', function () {
    $other = User::factory()->create(['email' => 'other@example.com']);
    $this->actingAs($other, 'api')->patchJson('/api/v1/user', ['email' => 'contested@example.com']);

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'contested@example.com'])
        ->assertStatus(422);
});

it('cancels a pending change', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')->patchJson('/api/v1/user', ['email' => 'jane.new@example.com']);

    expect($user->fresh()->hasPendingEmailChange())->toBeTrue();

    $this->actingAs($user, 'api')
        ->deleteJson('/api/v1/user/pending-email')
        ->assertOk()
        ->assertJsonPath('data.user.pending_email', null);

    expect($user->fresh()->hasPendingEmailChange())->toBeFalse();
});

it('updates a name without staging anything', function () {
    Notification::fake();

    $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['name' => 'Jane Doe'])
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Jane Doe')
        ->assertJsonPath('message', 'User updated successfully.');

    expect($user->fresh()->hasPendingEmailChange())->toBeFalse();
    Notification::assertNothingSent();
});

it('treats re-submitting the current address as a no-op', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user, 'api')
        ->patchJson('/api/v1/user', ['email' => 'jane@example.com'])
        ->assertOk();

    expect($user->fresh()->hasPendingEmailChange())->toBeFalse();
    Notification::assertNothingSent();
});
