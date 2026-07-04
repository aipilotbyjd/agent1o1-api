<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;

test('an email can be verified via a signed link', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute('v1.verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson($verificationUrl)->assertSuccessful();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

test('verification fails with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute('v1.verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('wrong@example.com'),
    ]);

    $this->getJson($verificationUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification fails without a valid signature', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson("/api/v1/verify-email/{$user->id}/".sha1($user->email))
        ->assertForbidden();
});

test('a verification email can be resent', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Passport::actingAs($user);

    $this->postJson('/api/v1/auth/resend-verification-email')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Verification email sent.');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('resending is skipped when the email is already verified', function () {
    Notification::fake();

    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/resend-verification-email')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Email is already verified.');

    Notification::assertNothingSent();
});
