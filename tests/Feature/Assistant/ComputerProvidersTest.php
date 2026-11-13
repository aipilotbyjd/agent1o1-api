<?php

use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Models\User;
use App\Services\Assistant\Computer\Sandboxes;
use App\Services\Sandbox\SandboxSettings;
use App\Services\Sandbox\SandboxUnavailableException;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = $this->assistant->sessions()->create(['last_activity_at' => now()]);
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['assistant_sandbox' => true]]))
        ->active()
        ->create();

    $this->sandboxes = fn () => app(Sandboxes::class);
    $this->output = fn () => Http::response(['stdout' => "ok\n", 'stderr' => '', 'results' => [], 'error' => null, 'seconds' => 3]);
});

it('starts the computer on the best provider and falls back when it cannot start one', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'first', 'priority' => 10]);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'second', 'priority' => 20, 'price_per_hour_usd' => 3.6]);
    Http::fake([
        'https://93.184.216.35/v1/sandboxes' => Http::response('down', 503),
        'https://93.184.216.37/v1/sandboxes' => Http::response(['id' => 'sbx-2']),
        'https://93.184.216.37/v1/sandboxes/sbx-2/run' => ($this->output)(),
    ]);

    [$result, $fresh] = ($this->sandboxes)()->run($this->session, 'python', 'print("ok")');

    expect($result->stdout)->toBe("ok\n")
        ->and($fresh)->toBeTrue()
        ->and(AssistantSandbox::query()->sole())->provider->toBe('second')->provider_sandbox_id->toBe('sbx-2')
        ->and(SandboxProvider::query()->where('key', 'first')->first()->consecutive_failures)->toBe(1)
        ->and(SandboxRun::query()->sole())->provider->toBe('second')->mode->toBe('persistent')->source->toBe('assistant')->cost_usd->toBe(0.003);
});

it('keeps a running computer on its own provider after the default changes', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->create(['key' => 'old']);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'new', 'is_default' => true]);
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'old', 'provider_sandbox_id' => 'sbx-old', 'last_used_at' => now()]);
    Http::fake([
        'https://93.184.216.35/v1/sandboxes/sbx-old/keepalive' => Http::response([]),
        'https://93.184.216.35/v1/sandboxes/sbx-old/run' => ($this->output)(),
    ]);

    [, $fresh] = ($this->sandboxes)()->run($this->session, 'python', 'print("ok")');

    expect($fresh)->toBeFalse();

    Http::assertNotSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://93.184.216.37'));
});

it('starts a fresh computer elsewhere when its provider was switched off', function () {
    SandboxProvider::factory()->runner('https://93.184.216.35')->disabled()->create(['key' => 'old']);
    SandboxProvider::factory()->runner('https://93.184.216.37')->create(['key' => 'new']);
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'old', 'provider_sandbox_id' => 'sbx-old', 'last_used_at' => now()]);
    Http::fake([
        'https://93.184.216.37/v1/sandboxes' => Http::response(['id' => 'sbx-new']),
        'https://93.184.216.37/v1/sandboxes/sbx-new/run' => ($this->output)(),
    ]);

    [, $fresh] = ($this->sandboxes)()->run($this->session, 'python', 'print("ok")');

    expect($fresh)->toBeTrue()->and(AssistantSandbox::query()->sole()->provider)->toBe('new');
});

it('is unavailable with the kill switch on, however many providers there are', function () {
    SandboxProvider::factory()->runner()->create();

    expect(($this->sandboxes)()->available($this->assistant))->toBeTrue();

    app(SandboxSettings::class)->set('kill_switch', true);

    expect(($this->sandboxes)()->available($this->assistant))->toBeFalse()
        ->and(fn () => ($this->sandboxes)()->run($this->session, 'python', 'x'))->toThrow(SandboxUnavailableException::class, 'switched off');
});

it('offers conversation computers only from providers that can keep one', function () {
    SandboxProvider::factory()->piston()->create();

    expect(($this->sandboxes)()->available($this->assistant))->toBeFalse();
});

it('deletes computers left idle past their timeout, at the provider and here', function () {
    SandboxProvider::factory()->runner()->create(['key' => 'main']);
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'main', 'provider_sandbox_id' => 'stale', 'last_used_at' => now()->subHour()]);
    $freshSession = $this->assistant->sessions()->create(['last_activity_at' => now()]);
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $freshSession->id, 'provider' => 'main', 'provider_sandbox_id' => 'live', 'last_used_at' => now()]);
    Http::fake(['https://93.184.216.35/v1/sandboxes/stale' => Http::response([])]);

    $this->artisan('sandbox:cleanup')->assertSuccessful();

    expect(AssistantSandbox::query()->pluck('provider_sandbox_id')->all())->toBe(['live']);

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/v1/sandboxes/stale'));
});

it('still forgets an idle computer when its provider cannot be reached', function () {
    SandboxProvider::factory()->runner()->create(['key' => 'main']);
    AssistantSandbox::query()->create(['assistant_id' => $this->assistant->id, 'assistant_session_id' => $this->session->id, 'provider' => 'main', 'provider_sandbox_id' => 'stale', 'last_used_at' => now()->subHour()]);
    Http::fake(['*' => Http::response('down', 503)]);

    $this->artisan('sandbox:cleanup')->assertSuccessful();

    expect(AssistantSandbox::query()->count())->toBe(0);
});
