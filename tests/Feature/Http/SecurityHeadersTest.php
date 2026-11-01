<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

it('sends hardening headers on api responses, errors included', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
});

it('sends the same headers on authenticated api responses', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('lets web routes be framed only by the same origin and sets no CSP there', function () {
    $response = $this->get('/up');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('does not replace a header a controller already set', function () {
    Route::get('/api/_headers-probe', fn () => response('x', 200, [
        'Content-Security-Policy' => 'sandbox',
        'X-Content-Type-Options' => 'custom',
    ]));

    $response = $this->get('/api/_headers-probe');

    expect($response->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('custom')
        ->and($response->headers->has('X-Frame-Options'))->toBeFalse();
});

it('sends HSTS only in production on an https app url', function (string $env, string $url, bool $expected) {
    app()->detectEnvironment(fn () => $env);
    config(['app.url' => $url]);

    $response = $this->getJson('/api/v1/user');

    expect($response->headers->has('Strict-Transport-Security'))->toBe($expected);

    if ($expected) {
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
})->with([
    'production https' => ['production', 'https://app.example.com', true],
    'production http' => ['production', 'http://app.example.com', false],
    'local https' => ['local', 'https://app.example.com', false],
]);
