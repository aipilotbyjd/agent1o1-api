<?php

use App\Enums\Sandbox\SandboxMode;
use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Services\Sandbox\SandboxConcurrency;
use App\Services\Sandbox\SandboxRouter;
use App\Services\Sandbox\SandboxSettings;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);
});

/**
 * @return list<string>
 */
function sandboxCandidates(SandboxMode $mode = SandboxMode::OneShot): array
{
    return app(SandboxRouter::class)->candidates($mode)->pluck('key')->all();
}

it('offers nothing when no provider is set up', function () {
    expect(sandboxCandidates())->toBe([])
        ->and(app(SandboxRouter::class)->available(SandboxMode::Persistent))->toBeFalse();
});

it('keeps the legacy E2B key working until a provider is added', function () {
    config(['assistant.sandbox.api_key' => 'legacy-key']);

    expect(sandboxCandidates(SandboxMode::Persistent))->toBe(['e2b']);

    SandboxProvider::factory()->create(['key' => 'e2b', 'is_enabled' => false]);

    expect(sandboxCandidates(SandboxMode::Persistent))->toBe([]);
});

it('tries the default first, then lower priority numbers', function () {
    SandboxProvider::factory()->create(['key' => 'slow', 'priority' => 30]);
    SandboxProvider::factory()->create(['key' => 'fast', 'priority' => 10]);
    SandboxProvider::factory()->create(['key' => 'chosen', 'priority' => 50, 'is_default' => true]);

    expect(sandboxCandidates())->toBe(['chosen', 'fast', 'slow']);
});

it('leaves out providers that are off, half set up or in an open circuit', function () {
    SandboxProvider::factory()->create(['key' => 'good']);
    SandboxProvider::factory()->disabled()->create(['key' => 'off']);
    SandboxProvider::factory()->create(['key' => 'no-key', 'credentials' => null]);
    SandboxProvider::factory()->runner()->create(['key' => 'no-url', 'config' => []]);
    SandboxProvider::factory()->circuitOpen()->create(['key' => 'broken']);

    expect(sandboxCandidates())->toBe(['good']);

    SandboxProvider::query()->where('key', 'broken')->update(['circuit_open_until' => now()->subSecond()]);

    expect(sandboxCandidates())->toContain('broken');
});

it('never gives a one-shot-only provider a conversation computer', function () {
    SandboxProvider::factory()->piston()->create(['key' => 'piston']);
    SandboxProvider::factory()->create(['key' => 'e2b-main', 'priority' => 200]);

    expect(sandboxCandidates(SandboxMode::OneShot))->toBe(['piston', 'e2b-main'])
        ->and(sandboxCandidates(SandboxMode::Persistent))->toBe(['e2b-main']);
});

it('honours the modes an operator narrowed a provider to', function () {
    SandboxProvider::factory()->create(['key' => 'workflows-only', 'modes' => ['one_shot']]);

    expect(sandboxCandidates(SandboxMode::OneShot))->toBe(['workflows-only'])
        ->and(sandboxCandidates(SandboxMode::Persistent))->toBe([]);
});

it('shares work between providers of equal priority by weight', function () {
    SandboxProvider::factory()->create(['key' => 'heavy', 'priority' => 10, 'weight' => 9]);
    SandboxProvider::factory()->create(['key' => 'light', 'priority' => 10, 'weight' => 1]);

    $heavyFirst = collect(range(1, 400))->filter(fn () => sandboxCandidates()[0] === 'heavy')->count();

    expect($heavyFirst)->toBeGreaterThan(300)->toBeLessThan(400);
});

it('stops everything with the kill switch', function () {
    SandboxProvider::factory()->create(['key' => 'good']);
    app(SandboxSettings::class)->set('kill_switch', true);

    expect(sandboxCandidates())->toBe([])
        ->and(app(SandboxRouter::class)->stopReason())->toContain('switched off');

    app(SandboxSettings::class)->set('kill_switch', false);

    expect(sandboxCandidates())->toBe(['good']);
});

it('stops everything once the month\'s budget is spent', function () {
    $provider = SandboxProvider::factory()->create(['key' => 'good']);
    app(SandboxSettings::class)->set('monthly_budget_usd', 5);
    SandboxRun::query()->create(['provider' => 'good', 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'ok', 'seconds' => 10, 'cost_usd' => 4.99]);

    expect(sandboxCandidates())->toBe(['good']);

    SandboxRun::query()->create(['provider' => $provider->key, 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'ok', 'seconds' => 10, 'cost_usd' => 0.02]);

    expect(sandboxCandidates())->toBe([])
        ->and(app(SandboxRouter::class)->stopReason())->toContain('limit for this month');
});

it('skips a provider that is at its concurrency limit and returns it when a slot frees', function () {
    $provider = SandboxProvider::factory()->create(['key' => 'small', 'max_concurrent' => 1]);
    $concurrency = app(SandboxConcurrency::class);

    expect($concurrency->enter($provider))->toBeTrue()
        ->and($concurrency->enter($provider))->toBeFalse()
        ->and(sandboxCandidates())->toBe([]);

    $concurrency->leave($provider);

    expect(sandboxCandidates())->toBe(['small']);
});
