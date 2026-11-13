<?php

use App\Enums\Sandbox\SandboxMode;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSandbox;
use App\Models\Sandbox\SandboxProvider;
use App\Models\Sandbox\SandboxRun;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Sandbox\SandboxRouter;
use App\Services\Sandbox\SandboxSettings;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function runnerProviderPayload(array $overrides = []): array
{
    return [
        'key' => 'fleet-1',
        'name' => 'Sandbox fleet',
        'driver' => 'runner',
        'config' => ['url' => 'https://93.184.216.35'],
        'credentials' => ['token' => 'runner-secret-9876'],
        'is_enabled' => true,
        ...$overrides,
    ];
}

it('adds a provider without ever returning its credentials', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, runnerProviderPayload()))
        ->assertCreated()
        ->assertJsonPath('data.key', 'fleet-1')
        ->assertJsonPath('data.has_credentials', true)
        ->assertJsonPath('data.credential_hint', '…9876')
        ->assertJsonPath('data.is_configured', true)
        ->assertJsonPath('data.modes', ['persistent', 'one_shot'])
        ->assertJsonMissingPath('data.credentials');

    $this->withHeaders($this->headers)->getJson(saUrl('sandbox/providers'))
        ->assertOk()->assertJsonMissing(['runner-secret-9876']);

    expect(SandboxProvider::query()->sole()->credential('token'))->toBe('runner-secret-9876')
        ->and(DB::table('sandbox_providers')->value('credentials'))->not->toContain('runner-secret-9876');
});

it('keeps credentials out of the audit log, recording only that there are some', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, runnerProviderPayload()))->assertCreated();

    $log = SuperAdminAuditLog::query()->where('action', 'sandbox.provider.created')->firstOrFail();

    expect(json_encode($log->toArray()))->not->toContain('runner-secret-9876')
        ->and($log->after)->toMatchArray(['key' => 'fleet-1', 'has_credentials' => true]);
});

it('needs a reason and a fresh authenticator code for every change', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), runnerProviderPayload())
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), [...stepUp($this->operator, runnerProviderPayload()), 'totp_code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    expect(SandboxProvider::query()->count())->toBe(0);
});

it('validates a provider', function (array $input, string $error) {
    SandboxProvider::factory()->create(['key' => 'taken']);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, runnerProviderPayload($input)))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'a key already used' => [['key' => 'taken'], 'key'],
    'capitals in the key' => [['key' => 'My_Fleet'], 'key'],
    'an unknown kind' => [['driver' => 'bash-on-my-server'], 'driver'],
    'no token for a runner' => [['credentials' => []], 'credentials.token'],
    'no address' => [['config' => []], 'config.url'],
    'a private address' => [['config' => ['url' => 'https://10.0.0.5']], 'config.url'],
    'the metadata address' => [['config' => ['url' => 'https://169.254.169.254']], 'config.url'],
    'plain http' => [['config' => ['url' => 'http://93.184.216.35']], 'config.url'],
    'a bad mode' => [['modes' => ['forever']], 'modes.0'],
    'a negative price' => [['price_per_hour_usd' => -1], 'price_per_hour_usd'],
]);

it('never accepts this app\'s own host as a sandbox', function () {
    config(['app.url' => 'https://93.184.216.40']);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, runnerProviderPayload(['config' => ['url' => 'https://93.184.216.40']])))
        ->assertUnprocessable()->assertJsonValidationErrors('config.url');
});

it('needs no address for E2B but does need its key', function () {
    $payload = ['key' => 'e2b', 'name' => 'E2B', 'driver' => 'e2b', 'credentials' => ['api_key' => 'e2b-key-0001']];

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, $payload))->assertCreated();
    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, [...$payload, 'key' => 'e2b-b', 'credentials' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('credentials.api_key');
});

it('adds Daytona with just an API key, using the cloud addresses by default', function () {
    $payload = ['key' => 'daytona', 'name' => 'Daytona', 'driver' => 'daytona', 'credentials' => ['api_key' => 'dtn-key-4321']];

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, $payload))
        ->assertCreated()->assertJsonPath('data.is_configured', true)->assertJsonPath('data.credential_hint', '…4321');

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers'), stepUp($this->operator, [...$payload, 'key' => 'daytona-b', 'credentials' => ['api_key' => 'k'], 'config' => ['toolbox_url' => 'https://10.0.0.5/toolbox']]))
        ->assertUnprocessable()->assertJsonValidationErrors('config.toolbox_url');
});

it('lets only operators and owners change anything, and everyone read', function (SuperAdminRole $role, int $read, int $write) {
    $admin = superAdmin($role);

    $this->withHeaders(superAdminHeaders($admin))->getJson(saUrl('sandbox'))->assertStatus($read);
    $this->withHeaders(superAdminHeaders($admin))->postJson(saUrl('sandbox/providers'), stepUp($admin, runnerProviderPayload()))->assertStatus($write);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 201],
    'operator' => [SuperAdminRole::Operator, 200, 201],
    'finance' => [SuperAdminRole::Finance, 200, 403],
    'support' => [SuperAdminRole::Support, 200, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403],
]);

it('replaces credentials, clears them, or leaves them alone', function () {
    $provider = SandboxProvider::factory()->runner()->create(['key' => 'fleet-1', 'credentials' => ['token' => 'old-token-0001']]);
    $url = saUrl("sandbox/providers/{$provider->id}");

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['name' => 'Renamed']))
        ->assertOk()->assertJsonPath('data.credential_hint', '…0001');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['credentials' => ['token' => 'new-token-0002']]))
        ->assertOk()->assertJsonPath('data.credential_hint', '…0002');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['credentials' => ['token' => '']]))->assertOk();
    expect($provider->fresh()->credential('token'))->toBe('new-token-0002');

    $this->withHeaders($this->headers)->patchJson($url, stepUp($this->operator, ['clear_credentials' => true]))
        ->assertOk()->assertJsonPath('data.has_credentials', false)->assertJsonPath('data.is_configured', false);

    $logs = SuperAdminAuditLog::query()->where('action', 'sandbox.provider.updated')->get();

    expect($logs)->toHaveCount(4)
        ->and(json_encode($logs->toArray()))->not->toContain('old-token-0001')->not->toContain('new-token-0002');
});

it('switches a provider off and the router stops using it at once', function () {
    $provider = SandboxProvider::factory()->runner()->create();

    $this->withHeaders($this->headers)->patchJson(saUrl("sandbox/providers/{$provider->id}"), stepUp($this->operator, ['is_enabled' => false]))
        ->assertOk()->assertJsonPath('data.is_enabled', false);

    expect(app(SandboxRouter::class)->candidates(SandboxMode::OneShot))->toHaveCount(0);
});

it('changes the config of a provider without losing its other keys', function () {
    $provider = SandboxProvider::factory()->create(['config' => ['api_url' => 'https://api.e2b.test', 'domain' => 'e2b.test', 'template' => 'a']]);

    $this->withHeaders($this->headers)->patchJson(saUrl("sandbox/providers/{$provider->id}"), stepUp($this->operator, ['config' => ['template' => 'b']]))->assertOk();

    expect($provider->fresh()->config)->toBe(['api_url' => 'https://api.e2b.test', 'domain' => 'e2b.test', 'template' => 'b']);
});

it('makes one provider the default, and clears it', function () {
    $a = SandboxProvider::factory()->create(['key' => 'a', 'is_default' => true]);
    $b = SandboxProvider::factory()->create(['key' => 'b']);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/default'), stepUp($this->operator, ['provider' => 'b']))->assertOk();

    expect($a->fresh()->is_default)->toBeFalse()->and($b->fresh()->is_default)->toBeTrue();

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/default'), stepUp($this->operator, ['provider' => null]))->assertOk();

    expect(SandboxProvider::query()->where('is_default', true)->count())->toBe(0);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/default'), stepUp($this->operator, ['provider' => 'ghost']))
        ->assertUnprocessable()->assertJsonValidationErrors('provider');
});

it('sets the fallback order', function () {
    SandboxProvider::factory()->create(['key' => 'a', 'priority' => 10]);
    SandboxProvider::factory()->create(['key' => 'b', 'priority' => 20]);
    SandboxProvider::factory()->create(['key' => 'c', 'priority' => 30]);

    $this->withHeaders($this->headers)->putJson(saUrl('sandbox/order'), stepUp($this->operator, ['order' => ['c', 'a']]))->assertOk();

    expect(SandboxProvider::query()->pluck('priority', 'key')->all())->toBe(['a' => 20, 'b' => 30, 'c' => 10]);
});

it('tests a provider and keeps the outcome', function () {
    $provider = SandboxProvider::factory()->piston()->create(['key' => 'piston']);
    Http::fake(['*' => Http::sequence()
        ->push(['run' => ['stdout' => "2\n", 'stderr' => '', 'code' => 0]])
        ->pushFailedConnection('timed out')]);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers/piston/test'))
        ->assertOk()->assertJsonPath('data.ok', true);

    expect($provider->fresh())->last_check_ok->toBeTrue();

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers/piston/test'))
        ->assertOk()->assertJsonPath('data.ok', false);

    expect($provider->fresh()->last_check_ok)->toBeFalse();
});

it('a good test closes an open circuit', function () {
    $provider = SandboxProvider::factory()->piston()->circuitOpen()->create(['key' => 'piston']);
    Http::fake(['*' => Http::response(['run' => ['stdout' => "2\n", 'stderr' => '', 'code' => 0]])]);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers/piston/test'))->assertOk();

    expect($provider->fresh())->consecutive_failures->toBe(0)->circuit_open_until->toBeNull();
});

it('can test and list the E2B provider that comes from the environment key', function () {
    config(['assistant.sandbox.api_key' => 'env-key', 'assistant.sandbox.api_url' => 'https://api.e2b.test', 'assistant.sandbox.domain' => 'e2b.test']);
    Http::fake([
        'https://api.e2b.test/sandboxes' => Http::response(['sandboxID' => 's1']),
        'https://49999-s1.e2b.test/execute' => Http::response(json_encode(['type' => 'stdout', 'text' => "2\n"])),
        'https://api.e2b.test/sandboxes/s1' => Http::response([]),
    ]);

    $this->withHeaders($this->headers)->getJson(saUrl('sandbox/providers'))
        ->assertOk()->assertJsonPath('data.0.key', 'e2b')->assertJsonPath('data.0.from_environment', true)->assertJsonMissing(['env-key']);

    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers/e2b/test'))->assertOk()->assertJsonPath('data.ok', true);
    $this->withHeaders($this->headers)->postJson(saUrl('sandbox/providers/nothing/test'))->assertNotFound();
});

it('has a kill switch and a monthly budget', function () {
    SandboxProvider::factory()->runner()->create();

    $this->withHeaders($this->headers)->patchJson(saUrl('sandbox/settings'), stepUp($this->operator, ['kill_switch' => true, 'monthly_budget_usd' => 50]))
        ->assertOk()->assertJsonPath('data.kill_switch', true)->assertJsonPath('data.monthly_budget_usd', 50.0);

    expect(app(SandboxSettings::class)->killed())->toBeTrue();

    $this->withHeaders($this->headers)->patchJson(saUrl('sandbox/settings'), stepUp($this->operator, ['kill_switch' => false, 'monthly_budget_usd' => null]))->assertOk();

    expect(app(SandboxSettings::class)->killed())->toBeFalse()->and(app(SandboxSettings::class)->monthlyBudgetUsd())->toBeNull();
    expect(SuperAdminAuditLog::query()->where('action', 'sandbox.settings.updated')->count())->toBe(2);
});

it('will not delete a provider that still hosts a conversation computer', function () {
    $provider = SandboxProvider::factory()->create(['key' => 'hosting']);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $assistant = Assistant::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
    $session = $assistant->sessions()->create(['last_activity_at' => now()]);
    AssistantSandbox::query()->create(['assistant_id' => $assistant->id, 'assistant_session_id' => $session->id, 'provider' => 'hosting', 'provider_sandbox_id' => 'x']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("sandbox/providers/{$provider->id}"), stepUp($this->operator))->assertStatus(409);

    AssistantSandbox::query()->delete();

    $this->withHeaders($this->headers)->deleteJson(saUrl("sandbox/providers/{$provider->id}"), stepUp($this->operator))->assertNoContent();
    expect(SandboxProvider::query()->count())->toBe(0);
});

it('shows the overview and what the month has cost', function () {
    SandboxProvider::factory()->runner()->create(['key' => 'main']);
    app(SandboxSettings::class)->set('monthly_budget_usd', 10);
    SandboxRun::query()->create(['provider' => 'main', 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'ok', 'seconds' => 60, 'cost_usd' => 2.5]);

    $this->withHeaders($this->headers)->getJson(saUrl('sandbox'))
        ->assertOk()
        ->assertJsonPath('data.month.cost_usd', 2.5)
        ->assertJsonPath('data.month.budget_used_ratio', 0.25)
        ->assertJsonPath('data.providers.0.key', 'main')
        ->assertJsonPath('data.drivers.0.driver', 'e2b')
        ->assertJsonCount(4, 'data.drivers');
});

it('reports usage by provider, by day and by workspace', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Heavy user']);
    SandboxRun::query()->create(['workspace_id' => $workspace->id, 'provider' => 'main', 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'ok', 'seconds' => 30, 'cost_usd' => 0.1]);
    SandboxRun::query()->create(['workspace_id' => $workspace->id, 'provider' => 'main', 'source' => 'workflow', 'mode' => 'one_shot', 'language' => 'python', 'status' => 'failed', 'seconds' => 0, 'cost_usd' => 0]);

    $this->withHeaders($this->headers)->getJson(saUrl('sandbox/usage?days=7'))
        ->assertOk()
        ->assertJsonPath('data.by_provider.0.provider', 'main')
        ->assertJsonPath('data.by_provider.0.runs', 2)
        ->assertJsonPath('data.by_provider.0.failed', 1)
        ->assertJsonPath('data.by_provider.0.seconds', 30)
        ->assertJsonCount(1, 'data.by_day')
        ->assertJsonPath('data.top_workspaces.0.name', 'Heavy user');
});
