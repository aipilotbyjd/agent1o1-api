<?php

use App\Ai\Agents\AdHocPromptAgent;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Nodes\AiAutomation\AskAiNode;
use App\Services\Billing\CreditMeter;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-openai-key', 'ai.providers.anthropic.key' => null, 'ai.providers.openrouter.key' => null]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}";

    $this->gpt = ModelCatalog::factory()->create(['slug' => 'gpt-4o', 'display_name' => 'GPT-4o', 'description' => 'Multimodal', 'strengths' => ['Images'], 'speed' => 'balanced', 'cost_tier' => 2]);
    $this->gpt->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);
    $this->claude = ModelCatalog::factory()->create(['slug' => 'claude', 'display_name' => 'Claude']);
    $this->claude->routes()->create(['execution_provider' => 'anthropic', 'execution_model_id' => 'claude-x', 'priority' => 0]);
    $this->claude->routes()->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'anthropic/claude-x', 'priority' => 1]);
});

function pickerEntries($test): Collection
{
    return collect($test->getJson("/api/v1/model-catalog?workspace_id={$test->workspace->id}")->assertOk()->json('data.model_catalog'))->keyBy('slug');
}

it('describes each model from the member\'s seat: details, cost, and what would make it usable', function () {
    Passport::actingAs($this->owner);

    $entries = pickerEntries($this);

    expect($entries['gpt-4o'])->toMatchArray([
        'description' => 'Multimodal',
        'strengths' => ['Images'],
        'speed' => 'balanced',
        'cost_tier' => 2,
        'is_available' => true,
        'unavailable_reason' => null,
        'own_key' => null,
        'is_allowed' => true,
        'is_default' => false,
    ])->and($entries['gpt-4o']['credits_per_message'])->toBeGreaterThan(1)
        ->and($entries['claude'])->toMatchArray([
            'is_available' => false,
            'unavailable_reason' => 'needs_key',
            'connect_providers' => [['key' => 'anthropic', 'label' => 'Anthropic'], ['key' => 'openrouter', 'label' => 'OpenRouter']],
            'credits_per_message' => null,
        ])
        ->and($entries['claude'])->not->toHaveKey('routes');
});

it('marks a model that runs on the workspace\'s own key and prices its tokens at zero', function () {
    AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('openrouter')->create();
    Passport::actingAs($this->owner);

    $claude = pickerEntries($this)['claude'];

    expect($claude['is_available'])->toBeTrue()
        ->and($claude['own_key'])->toBe(['provider' => 'openrouter', 'label' => 'OpenRouter'])
        ->and($claude['credits_per_message'])->toBe(app(CreditMeter::class)->estimateChatMessage('byok-x', null))
        ->and($claude['credits_per_message'])->toBeLessThan(pickerEntries($this)['gpt-4o']['credits_per_message']);
});

it('says when the policy is what blocks a model', function () {
    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $this->workspace->id, 'platform_usage' => 'never']);
    Passport::actingAs($this->owner);

    expect(pickerEntries($this)['gpt-4o'])->toMatchArray(['is_available' => false, 'unavailable_reason' => 'policy_needs_key']);
});

it('lets an admin set a default and an allow-list, and everyone read them', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);

    Passport::actingAs($member);
    $this->getJson("{$this->base}/model-settings")->assertOk()->assertJsonPath('data.settings.can_manage', false);
    $this->putJson("{$this->base}/model-settings", ['default_model_catalog_id' => $this->gpt->id])->assertForbidden();

    Passport::actingAs($this->owner);
    $this->putJson("{$this->base}/model-settings", ['allowed_model_catalog_ids' => [$this->claude->id], 'default_model_catalog_id' => $this->gpt->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['default_model_catalog_id']);
    $this->putJson("{$this->base}/model-settings", ['allowed_model_catalog_ids' => [$this->gpt->id], 'default_model_catalog_id' => $this->gpt->id])
        ->assertOk()
        ->assertJsonPath('data.settings.default_model_catalog_id', $this->gpt->id);

    $entries = pickerEntries($this);
    expect($entries['gpt-4o']['is_default'])->toBeTrue()
        ->and($entries['claude'])->toMatchArray(['is_allowed' => false, 'is_available' => false, 'unavailable_reason' => 'not_allowed'])
        ->and(AuditLog::query()->where('action', 'model_settings.updated')->exists())->toBeTrue();
});

it('refuses a disallowed model on an agent and starts new agents on the default', function () {
    WorkspaceModelSettings::query()->create(['workspace_id' => $this->workspace->id, 'allowed_model_catalog_ids' => [$this->gpt->id], 'default_model_catalog_id' => $this->gpt->id]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/agents", ['name' => 'Blocked', 'model_catalog_id' => $this->claude->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['model_catalog_id']);

    $created = $this->postJson("{$this->base}/agents", ['name' => 'Defaulted'])->assertCreated();
    expect(Agent::find($created->json('data.agent.id'))->model_catalog_id)->toBe($this->gpt->id);
});

it('runs an Ask AI step with no model on the workspace default', function () {
    WorkspaceModelSettings::query()->create(['workspace_id' => $this->workspace->id, 'default_model_catalog_id' => $this->gpt->id]);
    AdHocPromptAgent::fake(['Hi', 'Hi again']);
    $run = Run::factory()->forWorkspace($this->workspace)->create();

    app(AskAiNode::class)->execute($run, ['prompt' => 'Hello'], []);
    app(AskAiNode::class)->execute($run, ['prompt' => 'Hello', 'model_catalog_slug' => ''], []);

    AdHocPromptAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai');
});

it('tries a model with a sample prompt, charges the turn, and reports how it went', function () {
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_1', 'model' => 'gpt-4o', 'status' => 'completed',
        'output' => [['type' => 'message', 'id' => 'm', 'role' => 'assistant', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => 'Paris.', 'annotations' => []]]]],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 3, 'total_tokens' => 23],
    ])]);
    Passport::actingAs($this->owner);

    $trial = $this->postJson("{$this->base}/model-catalog/gpt-4o/try", ['prompt' => 'Capital of France?'])->assertOk()->json('data.trial');

    expect($trial)->toMatchArray(['text' => 'Paris.', 'prompt_tokens' => 20, 'completion_tokens' => 3, 'served_by_own_key' => false])
        ->and($trial['credits'])->toBeGreaterThan(0)
        ->and(CreditTransaction::query()->where('source_type', 'model_trial')->sole()->credits)->toBe($trial['credits']);
});

it('refuses to try a model the workspace doesn\'t allow or can\'t run', function () {
    WorkspaceModelSettings::query()->create(['workspace_id' => $this->workspace->id, 'allowed_model_catalog_ids' => [$this->claude->id]]);
    WorkspaceAiKeyPolicy::query()->create(['workspace_id' => $this->workspace->id, 'platform_usage' => 'never']);
    Http::fake();
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/model-catalog/gpt-4o/try", ['prompt' => 'Hi'])->assertUnprocessable();
    $this->postJson("{$this->base}/model-catalog/claude/try", ['prompt' => 'Hi'])->assertUnprocessable()
        ->assertJsonFragment(['message' => 'This workspace only runs AI on its own provider keys, and none of them covers this model. Add a key for Anthropic or OpenRouter in Settings → AI Providers, or pick another model.']);

    Http::assertNothingSent();
});

it('marks a workspace key rejected the moment a trial is refused for it, and says so plainly', function () {
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'API key expired.']], 401),
        'api.openai.com/v1/models' => Http::response(['error' => 'expired'], 401),
    ]);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/model-catalog/gpt-4o/try", ['prompt' => 'Hi'])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'OpenAI rejected your key: it may have expired or been revoked. Replace it in Settings → AI Providers.']);

    expect($credential->fresh()->validation_status->value)->toBe('invalid')
        ->and(CreditTransaction::query()->where('source_type', 'model_trial')->exists())->toBeFalse();
});
