<?php

use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Models\User;
use App\Services\Sandbox\CodeRunner;
use App\Services\Sandbox\SandboxConcurrency;
use App\Services\Sandbox\SandboxSettings;
use App\Services\Sandbox\SandboxUnavailableException;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);

    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->runner = fn () => app(CodeRunner::class);
    $this->ok = fn (string $stdout = "{\"a\":1}\n", int $seconds = 4) => Http::response(['stdout' => $stdout, 'stderr' => '', 'error' => null, 'seconds' => $seconds]);
});

it('runs a script once on the best provider and records what it cost', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'main', 'price_per_hour_usd' => 3.6]);
    Http::fake(['https://93.184.216.35/v1/run' => ($this->ok)()]);

    ['result' => $result, 'provider' => $provider] = ($this->runner)()->run($this->workspace, 'python', 'print(1)', ['x' => 1], 30);

    $run = SandboxRun::query()->sole();

    expect($result->stdout)->toBe("{\"a\":1}\n")
        ->and($provider)->toBe('main')
        ->and($run)->provider->toBe('main')->status->toBe('ok')->seconds->toBe(4)->cost_usd->toBe(0.004)
        ->and($run->workspace_id)->toBe($this->workspace->id)
        ->and(app(SandboxConcurrency::class)->inUse(SandboxProvider::query()->first()))->toBe(0);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request['code'], 'print(1)')
        && str_contains($request['code'], base64_encode('{"x":1}'))
        && ! str_contains($request['code'], '{"x":1}'));
});

it('moves on to the next provider when one cannot be reached', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'first', 'priority' => 10]);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'second', 'priority' => 20]);
    Http::fake([
        'https://93.184.216.35/v1/run' => Http::response('down', 503),
        'https://93.184.216.37/v1/run' => ($this->ok)(),
    ]);

    $outcome = ($this->runner)()->run($this->workspace, 'python', 'print(1)', [], 30);

    expect($outcome['provider'])->toBe('second')
        ->and(SandboxProvider::query()->where('key', 'first')->first())->consecutive_failures->toBe(1)
        ->and(SandboxRun::query()->orderBy('provider')->pluck('status', 'provider')->all())->toBe(['first' => 'failed', 'second' => 'ok']);
});

it('stops sending work to a provider that keeps failing, and recovers when it answers again', function () {
    config(['sandbox.circuit.failures' => 2]);
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'flaky', 'priority' => 10]);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'steady', 'priority' => 20]);
    $flaky = new stdClass;
    $flaky->up = false;
    Http::fake([
        'https://93.184.216.35/v1/run' => fn () => $flaky->up ? Http::response(['stdout' => '', 'stderr' => '', 'error' => null, 'seconds' => 1]) : Http::response('down', 503),
        'https://93.184.216.37/v1/run' => ($this->ok)(),
    ]);
    $callsToFlaky = fn (): int => collect(Http::recorded())->filter(fn (array $pair): bool => str_starts_with($pair[0]->url(), 'https://93.184.216.35'))->count();

    ($this->runner)()->run($this->workspace, 'python', 'x', [], 30);
    ($this->runner)()->run($this->workspace, 'python', 'x', [], 30);

    expect(SandboxProvider::query()->where('key', 'flaky')->first()->isCircuitOpen())->toBeTrue()
        ->and($callsToFlaky())->toBe(2);

    ($this->runner)()->run($this->workspace, 'python', 'x', [], 30);

    expect($callsToFlaky())->toBe(2);

    $flaky->up = true;
    SandboxProvider::query()->where('key', 'flaky')->update(['circuit_open_until' => now()->subSecond()]);

    expect(($this->runner)()->run($this->workspace, 'python', 'x', [], 30)['provider'])->toBe('flaky')
        ->and(SandboxProvider::query()->where('key', 'flaky')->first())->consecutive_failures->toBe(0)->circuit_open_until->toBeNull();
});

it('does not run code a second time elsewhere once it has started', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'first', 'priority' => 10]);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'second', 'priority' => 20]);
    Http::fake([
        'https://93.184.216.35/v1/run' => Http::response(['message' => 'crashed mid-run'], 500),
        'https://93.184.216.37/v1/run' => ($this->ok)(),
    ]);

    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class);

    Http::assertNotSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://93.184.216.37'));
});

it('gives a clear answer when every provider is down', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create();
    Http::fake(['*' => Http::response('down', 503)]);

    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class, 'could be reached');
});

it('says so when nothing is set up, or it is switched off', function () {
    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class, 'not set up');

    SandboxProvider::factory()->runner()->create();
    app(SandboxSettings::class)->set('kill_switch', true);

    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class, 'switched off');
});

it('refuses a workspace that used up its time for the day, but not another', function () {
    config(['sandbox.code.workspace_daily_seconds' => 10]);
    SandboxProvider::factory()->runner()->create(['key' => 'main']);
    Http::fake(['*' => ($this->ok)()]);
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    SandboxRun::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'main', 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'ok', 'seconds' => 10, 'cost_usd' => 0]);

    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class, 'for today')
        ->and(($this->runner)()->run($other, 'python', 'x', [], 30)['provider'])->toBe('main');
});

it('gives the slot back even when the run fails', function () {
    $provider = SandboxProvider::factory()->runner()->create(['max_concurrent' => 1]);
    Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

    expect(fn () => ($this->runner)()->run($this->workspace, 'python', 'x', [], 30))->toThrow(SandboxUnavailableException::class)
        ->and(app(SandboxConcurrency::class)->inUse($provider))->toBe(0);
});

it('skips a provider that is full and uses the next', function () {
    $full = SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'full', 'priority' => 10, 'max_concurrent' => 1]);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'spare', 'priority' => 20]);
    Http::fake(['https://93.184.216.37/v1/run' => ($this->ok)()]);
    app(SandboxConcurrency::class)->enter($full);

    expect(($this->runner)()->run($this->workspace, 'python', 'x', [], 30)['provider'])->toBe('spare');
});
