<?php

use App\Enums\Auth\AuthEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrlFor(User $user, ?string $email = null): string
{
    return URL::temporarySignedRoute('auth.verify-email', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($email ?? $user->getEmailForVerification()),
    ]);
}

it('sends a verification email on registration', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertCreated();

    Notification::assertSentTo(User::query()->where('email', 'jane@example.com')->first(), VerifyEmail::class);
});

it('verifies an email from a signed link and redirects into the frontend', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrlFor($user))
        ->assertRedirect(config('app.frontend_url').'/verify-email?status=verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->authEvents()->pluck('event')->all())->toContain(AuthEvent::EmailVerified);
});

it('redirects with an invalid status when the hash does not match the address', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrlFor($user, 'someone-else@example.com'))
        ->assertRedirect(config('app.frontend_url').'/verify-email?status=invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects with an expired status when the signature has lapsed', function () {
    $user = User::factory()->unverified()->create();

    $url = verificationUrlFor($user);

    $this->travel(61)->minutes();

    $this->get($url)->assertRedirect(config('app.frontend_url').'/verify-email?status=expired');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('reports an already verified address rather than verifying twice', function () {
    $user = User::factory()->create();

    $this->get(verificationUrlFor($user))
        ->assertRedirect(config('app.frontend_url').'/verify-email?status=already-verified');
});

it('resends a verification email to the signed-in user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/auth/resend-verification')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('will not resend a verification email to a guest', function () {
    $this->postJson('/api/v1/auth/resend-verification')->assertUnauthorized();
});
