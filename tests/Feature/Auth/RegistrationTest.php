<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('a user can register and receives a token pair', function () {
    Notification::fake();
    createPasswordGrantClient();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Secret#123',
        'password_confirmation' => 'Secret#123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.email', 'test@example.com')
        ->assertJsonStructure([
            'success',
            'statusCode',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token' => ['token_type', 'expires_in', 'access_token', 'refresh_token'],
            ],
        ]);

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration fails when the email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'Secret#123',
        'password_confirmation' => 'Secret#123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration fails with an invalid payload', function (array $payload, string $errorField) {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Secret#123',
        'password_confirmation' => 'Secret#123',
        ...$payload,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors($errorField);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'weak password' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'Different#123'], 'password'],
]);
