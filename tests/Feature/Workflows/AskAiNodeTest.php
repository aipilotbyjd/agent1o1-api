<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\AdHocPromptAgent;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;

it('prompts with the raw provider/model config when no catalog slug is given', function () {
    AdHocPromptAgent::fake(['ok']);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'ask_ai', 'config' => ['prompt' => 'Say hi', 'provider' => 'openai', 'model' => 'gpt-4o']],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow, []);
    $run = $run->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    $nodeRun = $run->nodeRuns->firstWhere('key', 'ask');
    expect($nodeRun->status)->toBe(NodeRunStatus::Completed);
    expect($nodeRun->output['text'])->toBe('ok');

    AdHocPromptAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'openai' && $prompt->model === 'gpt-4o';
    });
});

it('resolves the model catalog chain when a slug is configured', function () {
    AdHocPromptAgent::fake(['ok']);

    $catalog = ModelCatalog::factory()->create(['slug' => 'gpt-4o']);
    ModelRoute::factory()->forCatalog($catalog)->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'ask_ai', 'config' => ['prompt' => 'Say hi', 'model_catalog_slug' => 'gpt-4o']],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    app(StartWorkflowRunAction::class)->execute($workflow, []);

    AdHocPromptAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->provider->name() === 'openai' && $prompt->model === 'gpt-4o';
    });
});
