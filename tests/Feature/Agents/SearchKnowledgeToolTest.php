<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Services\Agents\KnowledgeBase;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

it('ranks the closest-by-construction chunk first and drops unrelated ones', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // Query vector points along the first axis; "close" shares that
    // direction, "nearer" a little less so, "far" is orthogonal (cosine
    // similarity 0) and "opposite" points the other way entirely (-1).
    Embeddings::fake([[[1.0, 0.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'close', 'chunk_text' => 'close chunk', 'embedding' => [0.9, 0.1, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'nearer', 'chunk_text' => 'nearer chunk', 'embedding' => [0.6, 0.4, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'far', 'chunk_text' => 'far chunk', 'embedding' => [0.0, 1.0, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'opposite', 'chunk_text' => 'opposite chunk', 'embedding' => [-1.0, 0.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace, app(KnowledgeBase::class));
    $result = json_decode($tool->handle(new Request(['query' => 'anything'])), true);

    expect(array_column($result, 'source'))->toBe(['close', 'nearer']);
    expect($result[0]['score'])->toBeGreaterThan($result[1]['score']);
});

it('lifts a chunk containing the exact term above a merely similar one', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'similar', 'chunk_text' => 'Our invoices are sent monthly.', 'embedding' => [0.8, 0.6]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'exact', 'chunk_text' => 'Invoice INV-2043 was refunded on May 3.', 'embedding' => [0.5, 0.866]]);

    $result = json_decode((new SearchKnowledgeTool($workspace, app(KnowledgeBase::class)))->handle(new Request(['query' => 'INV-2043'])), true);

    expect($result[0]['source'])->toBe('exact');
});

it('keeps a chunk that shares a word with the query even when its embedding is unrelated', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'sku', 'chunk_text' => 'SKU ZX-9 ships from Leeds.', 'embedding' => [0.0, 1.0]]);

    $result = json_decode((new SearchKnowledgeTool($workspace, app(KnowledgeBase::class)))->handle(new Request(['query' => 'where does zx-9 ship from'])), true);

    expect($result[0]['source'])->toBe('sku');
});

it('tells the model when nothing relevant was found', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 'unrelated', 'chunk_text' => 'Office plants are watered on Fridays.', 'embedding' => [0.0, 1.0]]);

    $result = (new SearchKnowledgeTool($workspace, app(KnowledgeBase::class)))->handle(new Request(['query' => 'refund policy']));

    expect($result)->toContain('Nothing in the knowledge base matches');
});

it('scopes results to the given collection', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'docs', 'source' => 'in-collection', 'chunk_text' => 'a', 'embedding' => [1.0, 0.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'other', 'source' => 'out-of-collection', 'chunk_text' => 'b', 'embedding' => [1.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace, app(KnowledgeBase::class), collection: 'docs');
    $result = json_decode($tool->handle(new Request(['query' => 'anything'])), true);

    expect($result)->toHaveCount(1);
    expect($result[0]['source'])->toBe('in-collection');
});

it('does not leak results from another workspace', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $otherWorkspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

    Embeddings::fake([[[1.0, 0.0]]]);

    DocumentEmbedding::create(['workspace_id' => $otherWorkspace->id, 'source' => 'foreign', 'chunk_text' => 'c', 'embedding' => [1.0, 0.0]]);

    $tool = new SearchKnowledgeTool($workspace, app(KnowledgeBase::class));
    $result = $tool->handle(new Request(['query' => 'anything']));

    expect($result)->toContain('Nothing in the knowledge base matches');
});

it('returns only the top N chunks, best first, across more chunks than N', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    Embeddings::fake([[[1.0, 0.0]]]);

    foreach (range(1, 12) as $i) {
        DocumentEmbedding::create([
            'workspace_id' => $workspace->id,
            'source' => "chunk-{$i}",
            'chunk_text' => "chunk {$i}",
            'embedding' => [(float) $i, 12.0 - $i],
        ]);
    }

    $results = app(KnowledgeBase::class)->search($workspace, 'anything', topN: 3);

    expect($results->pluck('source')->all())->toBe(['chunk-12', 'chunk-11', 'chunk-10']);
});
