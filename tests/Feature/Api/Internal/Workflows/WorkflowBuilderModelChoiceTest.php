<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Agents\WorkflowBuilderTitleAgent;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->session = WorkflowBuilderSession::factory()->forWorkspace($this->workspace, $this->owner)->create();
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/workflow-builder-sessions/{$this->session->id}/messages";
    $this->model = ModelCatalog::factory()->create(['slug' => 'claude-fast', 'display_name' => 'Claude Fast']);

    Passport::actingAs($this->owner);
});

it('stores the model the member picked for the turn and returns it', function () {
    Queue::fake();

    $response = $this->postJson($this->url, ['message' => 'Add a Slack step.', 'model_catalog_id' => $this->model->id])
        ->assertAccepted();

    $userMessage = WorkflowBuilderMessage::query()->where('role', 'user')->firstOrFail();

    expect($userMessage->model_catalog_id)->toBe($this->model->id);

    $this->getJson($this->url)->assertOk()
        ->assertJsonPath('data.messages.0.model_catalog_id', $this->model->id);

    Queue::assertPushed(ProcessWorkflowBuilderMessageJob::class);
    expect($response->json('data.message.role'))->toBe('assistant');
});

it('runs the turn on the picked model', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $resolver = Mockery::mock(ModelCatalogResolver::class)->makePartial();
    $resolver->shouldReceive('providerChain')->with('claude-fast')->atLeast()->once()->andReturn(['openai' => 'gpt-4o']);
    app()->instance(ModelCatalogResolver::class, $resolver);

    $this->postJson($this->url, ['message' => 'Add a Slack step.', 'model_catalog_id' => $this->model->id])->assertAccepted();
});

it('runs on the assistant\'s own model when none is picked', function () {
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $resolver = Mockery::mock(ModelCatalogResolver::class)->makePartial();
    $resolver->shouldReceive('providerChain')->with(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG)->atLeast()->once()->andReturn(['openai' => 'gpt-4o']);
    app()->instance(ModelCatalogResolver::class, $resolver);

    $this->postJson($this->url, ['message' => 'Add a Slack step.'])->assertAccepted();

    expect(WorkflowBuilderMessage::query()->where('role', 'user')->value('model_catalog_id'))->toBeNull();
});

it('refuses a model the builder may not use', function (string $state) {
    Queue::fake();

    $model = match ($state) {
        'internal' => ModelCatalog::factory()->create(['is_internal' => true]),
        'inactive' => ModelCatalog::factory()->create(['is_active' => false]),
        'unknown' => null,
    };

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $model?->id ?? (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model_catalog_id');

    expect(WorkflowBuilderMessage::query()->count())->toBe(0);
})->with(['internal', 'inactive', 'unknown']);

it('refuses a model the workspace has not allowed', function () {
    Queue::fake();

    $allowed = ModelCatalog::factory()->create();
    WorkspaceModelSettings::query()->updateOrCreate(['workspace_id' => $this->workspace->id], ['allowed_model_catalog_ids' => [$allowed->id]]);

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $this->model->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model_catalog_id');

    $this->postJson($this->url, ['message' => 'Hi', 'model_catalog_id' => $allowed->id])->assertAccepted();
});

/**
 * Sends a message with the given resolver behaviour and returns the assistant's reply as the API shows it.
 *
 * @param  array<string, array<string, string>|null>  $chains  catalog slug => provider chain, or null for "no enabled route"
 * @return array<string, mixed>
 */
function builderReplyWith(array $chains, ?string $pickedModelId): array
{
    WorkflowBuilderAgent::fake(['Done.']);
    WorkflowBuilderTitleAgent::fake([]);

    $resolver = Mockery::mock(ModelCatalogResolver::class)->makePartial();
    $resolver->shouldReceive('providerChain')->andReturnUsing(fn (string $slug): array => ($chains[$slug] ?? null) ?? throw new RuntimeException("Model catalog entry [{$slug}] has no enabled execution route."));
    app()->instance(ModelCatalogResolver::class, $resolver);

    test()->postJson(test()->url, ['message' => 'Add a Slack step.', 'model_catalog_id' => $pickedModelId])->assertAccepted();

    return collect(test()->getJson(test()->url)->assertOk()->json('data.messages'))->firstWhere('role', 'assistant');
}

function builderDefaultModel(): ModelCatalog
{
    return ModelCatalog::factory()->create(['slug' => ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG, 'display_name' => 'Workflow Builder Assistant', 'brand' => 'internal', 'is_internal' => true]);
}

it('shows the model that answered when it is the one the member picked', function () {
    $reply = builderReplyWith(['claude-fast' => ['openai' => 'gpt-4o']], $this->model->id);

    expect($reply['model_catalog_id'])->toBe($this->model->id)
        ->and($reply['model'])->toMatchArray(['id' => $this->model->id, 'slug' => 'claude-fast', 'display_name' => 'Claude Fast', 'is_default' => false]);
});

it('shows the builder default, not the model picked, when the pick had no working route and the default answered', function () {
    $default = builderDefaultModel();

    $reply = builderReplyWith([ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG => ['openai' => 'gpt-4o']], $this->model->id);

    expect($reply['model_catalog_id'])->toBe($default->id)
        ->and($reply['model'])->toMatchArray(['display_name' => 'Builder default', 'is_default' => true, 'brand' => null])
        ->and(WorkflowBuilderMessage::query()->where('role', 'user')->value('model_catalog_id'))->toBe($this->model->id);
});

it('shows the builder default when no model was picked, without naming the internal model', function () {
    builderDefaultModel();

    $reply = builderReplyWith([ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG => ['openai' => 'gpt-4o']], null);

    expect($reply['model'])->toMatchArray(['display_name' => 'Builder default', 'is_default' => true])
        ->and(json_encode($reply['model']))->not->toContain('Workflow Builder Assistant');
});

it('shows no model when the default provider answered with no catalog entry behind it', function () {
    $reply = builderReplyWith([], null);

    expect($reply['model_catalog_id'])->toBeNull()
        ->and($reply['model'])->toBeNull();
});

it('still shows which model was picked on the member\'s own message', function () {
    builderReplyWith(['claude-fast' => ['openai' => 'gpt-4o']], $this->model->id);

    $messages = collect($this->getJson($this->url)->assertOk()->json('data.messages'));

    expect($messages->firstWhere('role', 'user')['model']['display_name'])->toBe('Claude Fast');
});

it('loads every message\'s model in one query, not one per message', function () {
    builderReplyWith(['claude-fast' => ['openai' => 'gpt-4o']], $this->model->id);
    builderReplyWith(['claude-fast' => ['openai' => 'gpt-4o']], $this->model->id);

    $queries = 0;
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries += str_contains($query->sql, 'model_catalog') ? 1 : 0;
    });

    $this->getJson($this->url)->assertOk();

    expect($queries)->toBeLessThanOrEqual(1);
});

it('shows the model that answered when the editor reads the whole session', function () {
    builderReplyWith(['claude-fast' => ['openai' => 'gpt-4o']], $this->model->id);

    $messages = collect($this->getJson("/api/v1/workspaces/{$this->workspace->id}/workflow-builder-sessions/{$this->session->id}")->assertOk()->json('data.session.messages'));

    expect($messages->firstWhere('role', 'assistant')['model'])->toMatchArray(['display_name' => 'Claude Fast', 'is_default' => false]);
});
