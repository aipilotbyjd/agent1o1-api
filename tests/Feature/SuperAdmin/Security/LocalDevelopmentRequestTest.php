<?php

use App\Services\Http\LocalDevelopmentRequest;
use App\Services\SuperAdmin\Dashboards\DashboardAccess;
use Illuminate\Http\Request;

function requestTo(string $url): Request
{
    return Request::create($url, 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);
}

it('knows a development host from a public one', function (string $host, bool $expected) {
    expect(app(LocalDevelopmentRequest::class)->isDevelopmentHost($host))->toBe($expected);
})->with([
    ['localhost', true],
    ['127.0.0.1', true],
    ['[::1]', true],
    ['agent-1o1-ai.test', true],
    ['app.localhost', true],
    ['LOCALHOST', true],
    ['example.com', false],
    ['api.agent1o1.com', false],
    ['test.example.com', false],
    ['evil-localhost.com', false],
]);

it('treats a request as local only in the local environment and on a development host', function () {
    $local = app(LocalDevelopmentRequest::class);

    app()->detectEnvironment(fn () => 'local');

    expect($local->matches(requestTo('https://agent-1o1-ai.test/horizon')))->toBeTrue()
        ->and($local->matches(requestTo('https://api.agent1o1.com/horizon')))->toBeFalse();

    app()->detectEnvironment(fn () => 'production');

    expect($local->matches(requestTo('https://agent-1o1-ai.test/horizon')))->toBeFalse();
});

it('keeps Horizon and Pulse closed on a public host even when APP_ENV is left on local', function () {
    config(['super_admin_security.dashboard_ips' => []]);
    app()->detectEnvironment(fn () => 'local');

    $access = app(DashboardAccess::class);

    expect($access->allows(requestTo('https://api.agent1o1.com/horizon')))->toBeFalse()
        ->and($access->allows(requestTo('https://agent-1o1-ai.test/horizon')))->toBeTrue();
});

it('refuses the unsigned Stripe webhook on a public host even when APP_ENV is left on local', function () {
    config(['cashier.webhook.secret' => null]);
    app()->detectEnvironment(fn () => 'local');

    $this->postJson('https://api.agent1o1.com/api/stripe/webhook', ['id' => 'evt_forged'])->assertStatus(503);
});

it('flags a server left on the local environment behind a public address', function () {
    $this->withHeaders(superAdminHeaders(superAdmin()));
    app()->detectEnvironment(fn () => 'local');
    config(['app.url' => 'https://api.agent1o1.com']);

    $check = $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('system/health'))->json('data.checks.environment');

    expect($check['status'])->toBe('down')
        ->and($check['message'])->toContain('APP_ENV=production');
});

it('warns about debug mode behind a public address', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://api.agent1o1.com', 'app.debug' => true]);

    $check = $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('system/health'))->json('data.checks.environment');

    expect($check['status'])->toBe('degraded');
});

it('is healthy when the environment matches where it is served', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://api.agent1o1.com', 'app.debug' => false]);

    $check = $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('system/health'))->json('data.checks.environment');

    expect($check['status'])->toBe('healthy');
});
