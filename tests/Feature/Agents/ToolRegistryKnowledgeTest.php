<?php

use App\Ai\Tools\ForgetTool;
use App\Ai\Tools\ReadKnowledgeDocumentTool;
use App\Ai\Tools\RememberTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;

it('auto-attaches knowledge tools once the workspace has any embedded chunks', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['provider' => 'mistral']);
    $run = Run::factory()->create();

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);
    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(RememberTool::class);
    expect($tools[1])->toBeInstanceOf(ForgetTool::class);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 's', 'chunk_text' => 'x', 'embedding' => [1.0]]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    // No explicit AgentKnowledgeCollection attached: falls back to every
    // collection in the workspace, the pre-attachment zero-config behavior.
    expect($tools)->toHaveCount(4);
    expect($tools[0])->toBeInstanceOf(SearchKnowledgeTool::class);
    expect($tools[1])->toBeInstanceOf(ReadKnowledgeDocumentTool::class);
    expect($tools[2])->toBeInstanceOf(RememberTool::class);
    expect($tools[3])->toBeInstanceOf(ForgetTool::class);
});

it('scopes knowledge tools to only the agent\'s explicitly attached collections', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $otherAgent = Agent::factory()->forWorkspace($workspace)->create();
    $run = Run::factory()->create();

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'docs', 'source' => 's', 'chunk_text' => 'x', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'unrelated', 'source' => 's', 'chunk_text' => 'y', 'embedding' => [1.0]]);

    // Neither agent has attached anything yet: both fall back to the
    // whole-workspace search (every collection).
    $tools = app(ToolRegistry::class)->toolsFor($otherAgent, $run);
    expect($tools[0])->toBeInstanceOf(SearchKnowledgeTool::class);

    $agent->knowledgeCollections()->create(['collection' => 'docs']);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);
    $searchTool = collect($tools)->first(fn ($tool) => $tool instanceof SearchKnowledgeTool);

    expect((new ReflectionProperty($searchTool, 'collection'))->getValue($searchTool))->toBe(['docs']);
});

it("implicitly includes an agent's own artifact-indexed collection alongside its attachments", function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $run = Run::factory()->create();

    DocumentEmbedding::create([
        'workspace_id' => $workspace->id,
        'collection' => $agent->artifactKnowledgeCollection(),
        'source' => 'report.txt', 'chunk_text' => 'x', 'embedding' => [1.0],
    ]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);
    $searchTool = collect($tools)->first(fn ($tool) => $tool instanceof SearchKnowledgeTool);

    expect((new ReflectionProperty($searchTool, 'collection'))->getValue($searchTool))
        ->toBe([$agent->artifactKnowledgeCollection()]);
});
