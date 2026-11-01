<?php

use App\Enums\Auth\AuthEvent;
use App\Models\Credentials\OAuthConnection;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeSocialiteUser(string $id, string $email, string $name = 'Jane Doe'): SocialiteUser
{
    $socialUser = new SocialiteUser;
    $socialUser->id = $id;
    $socialUser->email = $email;
    $socialUser->name = $name;
    $socialUser->avatar = 'https://example.com/avatar.png';

    return $socialUser;
}

function fakeSocialiteDriver(SocialiteUser $socialUser): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->andReturn($provider);
}

it('hands back the provider authorization url', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?x=1'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->getJson('/api/v1/auth/social/google/redirect')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://accounts.google.com/o/oauth2/auth?x=1');
});

it('rejects a provider the app does not support', function () {
    $this->getJson('/api/v1/auth/social/myspace/redirect')->assertNotFound();
});

it('creates an account without a workspace on a first social sign-in, then exchanges the code for tokens', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $redirect = $this->get('/api/v1/auth/social/google/callback');

    $redirect->assertRedirectContains(config('app.frontend_url').'/oauth/callback?code=');

    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $response = $this->postJson('/api/v1/auth/social/exchange', ['code' => $query['code']])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'email'],
                'tokens' => ['access_token', 'expires_in', 'token_type'],
            ],
        ]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->workspaces()->count())->toBe(0)
        ->and(OAuthConnection::query()->where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();

    $this->withToken($response->json('data.tokens.access_token'))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.email', 'jane@example.com');
});

it('issues a refresh cookie for a social sign-in, same as a password sign-in', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $redirect = $this->get('/api/v1/auth/social/google/callback');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $response = $this->postJson('/api/v1/auth/social/exchange', ['code' => $query['code']])->assertOk();

    $refreshCookie = $response->getCookie('refresh_token', false);

    expect($refreshCookie)->not->toBeNull();

    $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshCookie->getValue())
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();
});

it('leaves an existing password untouched when that user signs in socially', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'Password1!']);
    $originalHash = $user->password;

    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $redirect = $this->get('/api/v1/auth/social/google/callback');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->postJson('/api/v1/auth/social/exchange', ['code' => $query['code']])->assertOk();

    expect($user->fresh()->password)->toBe($originalHash);
    expect(Hash::check('Password1!', $user->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'Password1!'])
        ->assertOk();
});

it('reuses the account on a second social sign-in rather than duplicating it', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $this->get('/api/v1/auth/social/google/callback');
    $this->get('/api/v1/auth/social/google/callback');

    expect(User::query()->where('email', 'jane@example.com')->count())->toBe(1);
    expect(OAuthConnection::query()->where('provider_id', 'google-1')->count())->toBe(1);
});

it('accepts the callback over POST for popup flows', function () {
    fakeSocialiteDriver(fakeSocialiteUser('github-1', 'jane@example.com'));

    $this->post('/api/v1/auth/social/github/callback')
        ->assertRedirectContains(config('app.frontend_url').'/oauth/callback?code=');
});

it('sends the user back with an error when the provider handshake fails', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new RuntimeException('invalid state'));

    Socialite::shouldReceive('driver')->andReturn($provider);

    $this->get('/api/v1/auth/social/google/callback')
        ->assertRedirectContains(config('app.frontend_url').'/oauth/callback?error=');

    expect(User::query()->count())->toBe(0);
});

it('refuses an exchange code that was already spent', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $redirect = $this->get('/api/v1/auth/social/google/callback');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->postJson('/api/v1/auth/social/exchange', ['code' => $query['code']])->assertOk();

    $this->postJson('/api/v1/auth/social/exchange', ['code' => $query['code']])->assertStatus(422);
});

it('refuses an exchange code that does not exist', function () {
    $this->postJson('/api/v1/auth/social/exchange', ['code' => 'made-up-code'])->assertStatus(422);
});

it('mints a code that carries no tokens of its own', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $redirect = $this->get('/api/v1/auth/social/google/callback');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $payload = Cache::get(AuthService::socialExchangeKey($query['code']));

    expect($payload)->toHaveKey('user_id')
        ->and($payload)->not->toHaveKey('tokens');
});

it('logs the link when a social account is first connected', function () {
    fakeSocialiteDriver(fakeSocialiteUser('google-1', 'jane@example.com'));

    $this->get('/api/v1/auth/social/google/callback');

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->authEvents()->pluck('event')->all())
        ->toContain(AuthEvent::SocialAccountLinked);
});
