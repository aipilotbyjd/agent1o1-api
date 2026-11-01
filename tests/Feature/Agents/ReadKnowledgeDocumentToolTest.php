<?php

use App\Ai\Tools\ReadKnowledgeDocumentTool;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Services\Agents\KnowledgeBase;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

it('reassembles a document\'s chunks in storage order', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'handbook.md', 'chunk_text' => 'First.', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'handbook.md', 'chunk_text' => 'Second.', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'other.md', 'chunk_text' => 'Unrelated.', 'embedding' => [1.0]]);

    $tool = new ReadKnowledgeDocumentTool($workspace, app(KnowledgeBase::class));
    $text = (string) $tool->handle(new Request(['source' => 'handbook.md']));

    expect(unwrapUntrusted($text))->toBe("First.\n\nSecond.");
});

it('reports an error for a source with no chunks', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $tool = new ReadKnowledgeDocumentTool($workspace, app(KnowledgeBase::class));
    $result = json_decode((string) $tool->handle(new Request(['source' => 'missing.md'])), true);

    expect($result['error'])->not->toBeNull();
});

it('scopes to the given collection(s)', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'docs', 'source' => 's', 'chunk_text' => 'in-scope', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'other', 'source' => 's', 'chunk_text' => 'out-of-scope', 'embedding' => [1.0]]);

    $tool = new ReadKnowledgeDocumentTool($workspace, app(KnowledgeBase::class), collection: ['docs']);
    $text = (string) $tool->handle(new Request(['source' => 's']));

    expect(unwrapUntrusted($text))->toBe('in-scope');
});
