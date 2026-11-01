<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Ai\PlatformKeyUsage;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Agents\AgentMessage;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Ai\Operations\RollUpModelUsage;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'billing.model_prices' => [],
        'ai.providers.openai.key' => 'platform-key',
        'ai.default_for_embeddings' => 'openai',
        'assistant.runtime.catalog' => 'personal-assistant',
    ]);

    $this->headers = superAdminHeaders(superAdmin(SuperAdminRole::Viewer));
});

function insightModel(string $slug, array $attributes = []): ModelCatalog
{
    $model = ModelCatalog::factory()->create(['slug' => $slug, ...$attributes]);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => "{$slug}-id"]);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => "{$slug}-id"]);

    return $model;
}

it('reports the health of the setup, to any role', function (SuperAdminRole $role) {
    insightModel(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG, ['is_internal' => true]);
    insightModel('personal-assistant', ['is_internal' => true]);

    $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('models/health'))
        ->assertOk()
        ->assertJsonPath('data.healthy', true)
        ->assertJsonPath('data.summary', ['errors' => 0, 'warnings' => 0, 'info' => 0]);
})->with(array_map(fn (SuperAdminRole $role) => [$role], SuperAdminRole::cases()));

it('reports what is wrong', function () {
    insightModel('personal-assistant', ['is_internal' => true]);
    $broken = ModelCatalog::factory()->create(['slug' => 'broken']);
    ModelRoute::factory()->forCatalog($broken)->disabled()->create();

    $response = $this->withHeaders($this->headers)->getJson(saUrl('models/health'))->assertOk()->assertJsonPath('data.healthy', false);

    $codes = array_column($response->json('data.findings'), 'code');

    expect($codes)->toContain('no_enabled_route', 'internal_model_missing');
});

it('lists the providers and whether the platform can run each', function () {
    config(['ai.providers.mistral.key' => null]);
    AiEndpoint::factory()->create(['slug' => 'my-vllm', 'name' => 'Our vLLM']);
    insightModel('gpt-a');

    $providers = collect($this->withHeaders($this->headers)->getJson(saUrl('models/providers'))->assertOk()->json('data'))->keyBy('key');

    expect($providers['openai'])->toMatchArray(['source' => 'platform', 'configured' => true, 'byok_capable' => true, 'routes' => 1, 'enabled_routes' => 1])
        ->and($providers['mistral'])->toMatchArray(['configured' => false, 'byok_capable' => true])
        ->and($providers['my-vllm'])->toMatchArray(['source' => 'endpoint', 'label' => 'Our vLLM', 'configured' => true, 'byok_capable' => false]);

    expect(json_encode($providers))->not->toContain('platform-key');
});

it('shows how workspaces use their own keys, without a key', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);

    AiProviderCredential::factory()->create(['workspace_id' => $workspace->id, 'execution_provider' => 'openai', 'validation_status' => AiProviderCredentialStatus::Valid]);
    AiProviderCredential::factory()->create(['workspace_id' => $other->id, 'execution_provider' => 'openai', 'validation_status' => AiProviderCredentialStatus::Invalid, 'validation_message' => 'Key revoked', 'key_hint' => '…9f3a']);
    AiProviderCredential::factory()->create(['workspace_id' => $other->id, 'execution_provider' => 'anthropic', 'validation_status' => AiProviderCredentialStatus::Unvalidated]);

    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $workspace->id, 'platform_usage' => PlatformKeyUsage::Never, 'allow_personal_keys' => false]);
    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $other->id, 'platform_usage' => PlatformKeyUsage::Fallback]);

    $response = $this->withHeaders($this->headers)->getJson(saUrl('models/byok'))->assertOk();
    $openai = collect($response->json('data.providers'))->firstWhere('provider', 'openai');

    expect($openai)->toMatchArray(['label' => 'OpenAI', 'valid' => 1, 'invalid' => 1, 'unvalidated' => 0, 'total' => 2])
        ->and($response->json('data.rejected'))->toHaveCount(1)
        ->and($response->json('data.rejected.0'))->toMatchArray(['workspace' => 'Globex', 'provider' => 'openai', 'key_hint' => '…9f3a', 'message' => 'Key revoked'])
        ->and($response->json('data.policies'))->toBe([
            'platform_usage' => ['fallback' => 1, 'when_no_key' => 0, 'never' => 1],
            'personal_keys_disabled' => 1,
        ]);

    expect($response->getContent())->not->toContain('"data":"')->not->toContain('api_key');
});

it('reports usage per model and bounds the window', function () {
    ModelPrice::factory()->priced(10, 0)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    AgentMessage::factory()->assistant()->create(['usage' => ['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]]);
    app(RollUpModelUsage::class)->run();

    $this->withHeaders($this->headers)->getJson(saUrl('models/usage?days=7'))
        ->assertOk()
        ->assertJsonPath('data.days', 7)
        ->assertJsonPath('data.models.0.model', 'gpt-x')
        ->assertJsonPath('data.models.0.calls', 1)
        ->assertJsonPath('data.totals.estimated_cost_usd', 10);

    $this->withHeaders($this->headers)->getJson(saUrl('models/usage?days=500'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('models/usage'))->assertOk()->assertJsonPath('data.days', 7);
});

it('reports margins, with the lifetime assumption', function () {
    Plan::factory()->create(['slug' => 'pro', 'price_monthly' => 2900, 'credits_monthly' => 5000, 'enabled_intervals' => ['monthly']]);
    insightModel('gpt-a');

    $this->withHeaders($this->headers)->getJson(saUrl('models/margins'))
        ->assertOk()
        ->assertJsonPath('data.lifetime_months', 36)
        ->assertJsonPath('data.plans.0.plan', 'pro')
        ->assertJsonPath('data.models.0.slug', 'gpt-a');

    $this->withHeaders($this->headers)->getJson(saUrl('models/margins?lifetime_months=12'))->assertOk()->assertJsonPath('data.lifetime_months', 12);
    $this->withHeaders($this->headers)->getJson(saUrl('models/margins?lifetime_months=1'))->assertUnprocessable();
});

it('refuses anyone who is not a signed-in super admin', function (string $path) {
    $this->flushHeaders()->getJson(saUrl($path))->assertUnauthorized();
})->with(['models/health', 'models/providers', 'models/byok', 'models/usage', 'models/margins', 'models/prices']);

it('ranks the workspaces by cost and draws a daily trend', function () {
    ModelPrice::factory()->priced(10, 0)->create(['provider' => 'openai', 'model_id' => 'gpt-x']);
    AgentMessage::factory()->assistant()->create(['usage' => ['provider' => 'openai', 'model' => 'gpt-x', 'prompt_tokens' => 1_000_000, 'completion_tokens' => 0]]);
    app(RollUpModelUsage::class)->run();

    $this->withHeaders($this->headers)->getJson(saUrl('models/usage/workspaces?days=7'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.estimated_cost_usd', 10);

    $daily = $this->withHeaders($this->headers)->getJson(saUrl('models/usage/daily?days=3'))->assertOk()->assertJsonCount(3, 'data')->json('data');

    expect($daily[2])->toMatchArray(['date' => today()->toDateString(), 'calls' => 1]);

    $this->withHeaders($this->headers)->getJson(saUrl('models/usage/daily?days=99'))->assertUnprocessable();
});

it('narrows usage to one workspace', function () {
    $this->withHeaders($this->headers)->getJson(saUrl('models/usage?workspace_id=not-a-uuid'))->assertUnprocessable();
    $this->withHeaders($this->headers)->getJson(saUrl('models/usage?workspace_id='.Str::uuid()))->assertOk()->assertJsonPath('data.totals.calls', 0);
});
