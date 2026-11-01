<?php

use App\Models\User;

/**
 * @return array{access_token: string, refresh_cookie: string}
 */
function signIn(mixed $test, string $email = 'jane@example.com', string $password = 'Password1!'): array
{
    $response = $test->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);

    return [
        'access_token' => $response->json('data.tokens.access_token'),
        'refresh_cookie' => $response->getCookie('refresh_token', false)->getValue(),
    ];
}

it('never returns the refresh token in the body, only in an httpOnly cookie', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'Password1!',
    ])->assertOk();

    $response->assertJsonMissingPath('data.tokens.refresh_token');

    $cookie = $response->getCookie('refresh_token', false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('none');
});

/*
 * `withCredentials()` on every refresh below is not incidental: Laravel's JSON
 * test requests send no cookies without it, mirroring the browser, where a
 * `fetch` only sends this cookie with `credentials: 'include'`. The frontend has
 * to do the same or refreshing silently 401s.
 */
it('exchanges the refresh cookie for a fresh access token and rotates the cookie', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    ['access_token' => $original, 'refresh_cookie' => $refreshCookie] = signIn($this);

    $refreshed = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['data' => ['tokens' => ['access_token', 'expires_in', 'token_type']]])
        ->assertJsonMissingPath('data.tokens.refresh_token');

    expect($refreshed->json('data.tokens.access_token'))->not->toBe($original);
    expect($refreshed->getCookie('refresh_token', false)->getValue())->not->toBe($refreshCookie);

    $this->withToken($refreshed->json('data.tokens.access_token'))
        ->getJson('/api/v1/user')
        ->assertOk();
});

it('rejects a refresh with no cookie at all', function () {
    $this->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Missing refresh token.');
});

it('rejects a refresh cookie that has already been used', function () {
    User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    ['refresh_cookie' => $refreshCookie] = signIn($this);

    $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie)
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(422);
});

it('rejects a refresh cookie that is not a token at all', function () {
    $this->withCredentials()->withUnencryptedCookie('refresh_token', 'not-a-refresh-token')
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(422);
});

it('stamps the refreshed session with the requesting device', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);

    ['refresh_cookie' => $refreshCookie] = signIn($this);

    $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'PestBrowser/2.0'])
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    $session = $user->tokens()->where('revoked', false)->latest()->first();

    expect($session->user_agent)->toBe('PestBrowser/2.0');
});
