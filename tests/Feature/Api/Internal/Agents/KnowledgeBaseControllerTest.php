<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Enums\Workspaces\Role;
use App\Jobs\Knowledge\IngestKnowledgeJob;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForKnowledgeBase(): array
{
    $owner = User::factory()->create();

    return [app(WorkspaceService::class)->create($owner, ['name' => 'Acme']), $owner];
}

/**
 * A paragraph long enough that the chunker can't pack it together with
 * another one: the splitter targets ~1,000 characters per chunk.
 */
function longParagraph(string $opening): string
{
    return $opening.' '.str_repeat('Supporting policy detail. ', 30);
}

it('ingests text as embedded chunks', function () {
    Embeddings::fake([[[1.0, 0.0], [0.0, 1.0]]]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $refunds = longParagraph('First paragraph about refunds.');
    $shipping = longParagraph('Second paragraph about shipping.');

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => "{$refunds}\n\n{$shipping}",
        'source' => 'handbook.md',
        'collection' => 'support',
        'metadata' => ['revision' => 3],
    ]);

    $response->assertCreated();
    expect($response->json('data.chunks_count'))->toBe(2);

    $chunks = DocumentEmbedding::orderBy('chunk_index')->get();
    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->chunk_text)->toStartWith('First paragraph about refunds.');
    expect($chunks[1]->chunk_text)->toStartWith('Second paragraph about shipping.');
    expect($chunks[0]->collection)->toBe('support');
    expect($chunks[0]->source)->toBe('handbook.md');
    // Loose comparison: a whole-number float round-trips through JSON as an int.
    expect($chunks[0]->embedding)->toEqual([1.0, 0.0]);
    expect($chunks[1]->embedding)->toEqual([0.0, 1.0]);
    expect($chunks[0]->metadata)->toBe(['revision' => 3]);

    // The vector is stored but never serialized back out, only its size is.
    expect($response->json('data.chunks.0'))->not->toHaveKey('embedding');
    expect($response->json('data.chunks.0.dimensions'))->toBe(2);

    Embeddings::assertGenerated(fn ($prompt) => count($prompt->inputs) === 2);
});

it('packs short paragraphs together and splits an over-long one', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    // A 2,500-character paragraph can't fit one chunk and is split on its
    // sentence boundaries; the two short paragraphs ahead of it are packed
    // together rather than becoming two near-useless one-line chunks.
    $long = str_repeat('This sentence is repeated to overflow a single chunk. ', 48);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => "Short one.\n\nShort two.\n\n{$long}",
    ]);

    $response->assertCreated();

    $chunks = DocumentEmbedding::orderBy('id')->get();

    expect($chunks[0]->chunk_text)->toStartWith("Short one.\n\nShort two.");
    expect($chunks->count())->toBeGreaterThan(2);
    // No chunk overshoots the target by more than a sentence.
    expect($chunks->max(fn (DocumentEmbedding $chunk) => mb_strlen($chunk->chunk_text)))->toBeLessThanOrEqual(1053);
    // Nothing is dropped on the way through the splitter.
    expect($chunks->sum(fn (DocumentEmbedding $chunk) => mb_strlen($chunk->chunk_text)))
        ->toBeGreaterThanOrEqual(mb_strlen(trim($long)));
});

it('searches with the same ranking an agent gets', function () {
    // One vector per call: the ingest of both chunks, then two query
    // embeddings: the API search, then the agent tool's search.
    Embeddings::fake([
        [[1.0, 0.0], [0.0, 1.0]],
        [[1.0, 0.0]],
        [[1.0, 0.0]],
    ]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => longParagraph('Refund window is 30 days.')."\n\n".longParagraph('Shipping takes 5 days.'),
        'source' => 'handbook.md',
    ])->assertCreated();

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/search", [
        'query' => 'refunds',
        'limit' => 1,
    ]);

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(1);
    expect($response->json('data.results.0.text'))->toStartWith('Refund window is 30 days.');
    expect($response->json('data.results.0.source'))->toBe('handbook.md');
    expect($response->json('data.results.0.score'))->toEqual(1.0);

    $toolResult = json_decode(app(SearchKnowledgeTool::class, ['workspace' => $workspace])
        ->handle(new Request(['query' => 'refunds'])), true);

    expect(unwrapUntrusted($toolResult[0]['text']))->toStartWith('Refund window is 30 days.');
});

it('scopes search to a collection', function () {
    Embeddings::fake([[[1.0, 0.0]], [[1.0, 0.0]], [[1.0, 0.0]]]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'Support answer.', 'collection' => 'support']);
    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'Sales answer.', 'collection' => 'sales']);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/search", [
        'query' => 'anything',
        'collection' => 'support',
    ]);

    $response->assertOk();
    expect($response->json('data.results'))->toHaveCount(1);
    expect($response->json('data.results.0.text'))->toBe('Support answer.');
});

it('lists collections with their chunk counts', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'a', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'b', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'c', 'embedding' => [1.0]]);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections");

    $response->assertOk();
    expect($response->json('data.collections'))->toBe([
        ['collection' => 'sales', 'private' => false, 'chunks_count' => 1],
        ['collection' => 'support', 'private' => false, 'chunks_count' => 2],
    ]);
});

it('deletes a single chunk and a whole collection', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $chunk = DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'support', 'chunk_text' => 'a', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'b', 'embedding' => [1.0]]);
    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'collection' => 'sales', 'chunk_text' => 'c', 'embedding' => [1.0]]);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/{$chunk->id}")->assertNoContent();

    $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/sales");

    $response->assertOk();
    expect($response->json('data.deleted_count'))->toBe(2);
    expect(DocumentEmbedding::count())->toBe(0);
});

it('does not touch another workspace\'s chunks', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    [$otherWorkspace] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $foreign = DocumentEmbedding::create([
        'workspace_id' => $otherWorkspace->id, 'collection' => 'support', 'chunk_text' => 'secret', 'embedding' => [1.0],
    ]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base")->assertOk()
        ->assertJsonCount(0, 'data');

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/{$foreign->id}")->assertNotFound();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/support")->assertNotFound();

    expect(DocumentEmbedding::count())->toBe(1);
});

it('does not let a viewer ingest', function () {
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'a'])->assertForbidden();
    expect(DocumentEmbedding::count())->toBe(0);
});

it('ingests a plain-text file upload, defaulting source to its filename', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $file = UploadedFile::fake()->createWithContent('handbook.md', "# Handbook\n\nRefund window is 30 days.");

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'file' => $file,
        'collection' => 'support',
    ]);

    $response->assertCreated();
    $chunk = DocumentEmbedding::sole();
    expect($chunk->source)->toBe('handbook.md');
    expect($chunk->chunk_text)->toContain('Refund window is 30 days.');
});

it('rejects a knowledge-base file upload of an unsupported type', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $file = UploadedFile::fake()->create('handbook.pdf', 10, 'application/pdf');

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['file' => $file])
        ->assertJsonValidationErrors('file');
});

it('rejects sending both text and file', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $file = UploadedFile::fake()->createWithContent('a.txt', 'a');

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'a', 'file' => $file])
        ->assertJsonValidationErrors('text');
});

it('reads a document\'s full text back across its chunks', function () {
    Embeddings::fake([[[1.0, 0.0], [0.0, 1.0]]]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
        'text' => longParagraph('First part.')."\n\n".longParagraph('Second part.'),
        'source' => 'handbook.md',
        'collection' => 'support',
    ])->assertCreated();

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?".http_build_query([
        'source' => 'handbook.md',
        'collection' => 'support',
    ]));

    $response->assertOk();
    expect($response->json('data.text'))->toContain('First part.')
        ->toContain('Second part.');
});

it('404s reading a document that does not exist', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?source=nope.md")
        ->assertNotFound();
});

it('records each chunk\'s position and reads a document in that order', function () {
    [$workspace] = ownerWorkspaceForKnowledgeBase();

    foreach ([2 => 'third', 0 => 'first', 1 => 'second'] as $index => $text) {
        DocumentEmbedding::factory()->forWorkspace($workspace)->create([
            'source' => 'ordered.md',
            'chunk_index' => $index,
            'chunk_text' => $text,
        ]);
    }

    expect(app(KnowledgeBase::class)->readDocument($workspace, 'ordered.md'))->toBe("first\n\nsecond\n\nthird");
});

it('replaces a re-ingested source instead of duplicating it', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    foreach (['Old revision.', 'New revision.'] as $text) {
        $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
            'text' => $text,
            'source' => 'policy.md',
            'collection' => 'support',
        ])->assertCreated();
    }

    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'policy.md', 'collection' => 'other']);

    expect(DocumentEmbedding::where('collection', 'support')->sole()->chunk_text)->toBe('New revision.');
    expect(DocumentEmbedding::where('collection', 'other')->count())->toBe(1);
});

it('keeps the previous document when a re-ingest fails', function () {
    Embeddings::fake(fn () => throw new RuntimeException('provider down'));
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'policy.md', 'chunk_text' => 'Old revision.']);

    expect(fn () => app(KnowledgeBase::class)->ingest($workspace, 'New revision.', 'policy.md', replaceSource: true))
        ->toThrow(RuntimeException::class);
    expect(DocumentEmbedding::sole()->chunk_text)->toBe('Old revision.');
});

it('deletes a document by source', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    DocumentEmbedding::factory()->forWorkspace($workspace)->count(2)->sequence(['chunk_index' => 0], ['chunk_index' => 1])->create(['source' => 'a.md']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'b.md']);
    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?source=a.md")
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 2);
    expect(DocumentEmbedding::pluck('source')->all())->toBe(['b.md']);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?source=a.md")->assertNotFound();
});

it('queues a large ingest instead of embedding it inline', function () {
    Bus::fake();
    config(['knowledge_base.sync_ingest_max_characters' => 100]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => str_repeat('word ', 50), 'source' => 'big.md'])
        ->assertAccepted()
        ->assertJsonPath('data.queued', true);

    Bus::assertDispatched(IngestKnowledgeJob::class, fn (IngestKnowledgeJob $job) => $job->source === 'big.md' && $job->collection === 'default');
    expect(DocumentEmbedding::count())->toBe(0);
});

it('rejects a non-file value for file without erroring', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['file' => 'not-a-file'])
        ->assertJsonValidationErrors('file');
});

it('rejects an upload that is not valid UTF-8', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $file = UploadedFile::fake()->createWithContent('latin1.txt', "caf\xE9 au lait");

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['file' => $file])
        ->assertUnprocessable();
    expect(DocumentEmbedding::count())->toBe(0);
});

it('rejects collection names that cannot be addressed in a URL or that are reserved', function (string $collection) {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => 'a', 'collection' => $collection])
        ->assertJsonValidationErrors('collection');
})->with(['a/b', 'a?b', 'artifacts:123', '-lead']);

it('caps per_page when listing chunks', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base?per_page=100000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('detaches agents when their collection is deleted', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support']);
    $agent->knowledgeCollections()->create(['collection' => 'support']);
    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/support")->assertOk();

    expect($agent->knowledgeCollections()->count())->toBe(0);
});

it('hides agents\' artifact collections from the collection list', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => $agent->artifactKnowledgeCollection()]);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support']);
    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections")
        ->assertJsonCount(1, 'data.collections');
});

it('reads only one collection\'s copy of a source shared by several, and caps length', function () {
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'a', 'source' => 'notes.txt', 'chunk_text' => 'from a']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'b', 'source' => 'notes.txt', 'chunk_text' => 'from b']);

    expect(app(KnowledgeBase::class)->readDocument($workspace, 'notes.txt'))->toBe('from a');

    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'huge.txt', 'chunk_index' => 0, 'chunk_text' => str_repeat('x', KnowledgeBase::MAX_DOCUMENT_CHARACTERS)]);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'huge.txt', 'chunk_index' => 1, 'chunk_text' => 'tail']);

    expect(app(KnowledgeBase::class)->readDocument($workspace, 'huge.txt'))
        ->toContain('[Document truncated')
        ->not->toContain('tail');
});

it('includes the collection in search results', function () {
    Embeddings::fake();
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support', 'chunk_text' => 'refund window is thirty days']);

    $results = app(KnowledgeBase::class)->search($workspace, 'refund window');

    expect($results->first()['collection'])->toBe('support');
});

it('re-ingests a private document without replacing the shared one of the same source', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'policy.md', 'collection' => 'notes', 'chunk_text' => 'Shared.']);
    Passport::actingAs($owner);

    foreach (['Mine v1.', 'Mine v2.'] as $text) {
        $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", [
            'text' => $text,
            'source' => 'policy.md',
            'collection' => 'notes',
            'private' => true,
        ])->assertCreated();
    }

    expect(DocumentEmbedding::whereNull('owner_id')->sole()->chunk_text)->toBe('Shared.')
        ->and(DocumentEmbedding::where('owner_id', $owner->id)->sole()->chunk_text)->toBe('Mine v2.');
});

it('deletes a private document by source without touching the shared copy, and lets a viewer do it', function () {
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'a.md']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['source' => 'a.md', 'owner_id' => $viewer->id]);
    Passport::actingAs($viewer);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?source=a.md")->assertForbidden();
    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/document?source=a.md&private=1")
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 1);

    expect(DocumentEmbedding::sole()->owner_id)->toBeNull();
});

it('keeps the owner on a queued private ingest', function () {
    Bus::fake();
    config(['knowledge_base.sync_ingest_max_characters' => 100]);
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/knowledge-base", ['text' => str_repeat('word ', 50), 'source' => 'big.md', 'private' => true])
        ->assertAccepted();

    Bus::assertDispatched(IngestKnowledgeJob::class, fn (IngestKnowledgeJob $job) => $job->ownerId === $owner->id && $job->collection === 'personal');
});

it('leaves agents attached when only a private collection is deleted', function () {
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support']);
    DocumentEmbedding::factory()->forWorkspace($workspace)->create(['collection' => 'support', 'owner_id' => $owner->id]);
    $agent->knowledgeCollections()->create(['collection' => 'support']);
    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/knowledge-base/collections/support?private=1")
        ->assertOk()
        ->assertJsonPath('data.deleted_count', 1);

    expect($agent->knowledgeCollections()->count())->toBe(1)
        ->and(DocumentEmbedding::sole()->owner_id)->toBeNull();
});

it('keeps agents\' artifact collections out of listing, search and document reads', function () {
    Embeddings::fake();
    [$workspace, $owner] = ownerWorkspaceForKnowledgeBase();
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    DocumentEmbedding::factory()->forWorkspace($workspace)->create([
        'collection' => $agent->artifactKnowledgeCollection(),
        'source' => 'secret.md',
        'chunk_text' => 'restricted artifact text about payroll',
    ]);
    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/knowledge-base";

    $this->getJson($base)->assertJsonCount(0, 'data');
    $this->postJson("{$base}/search", ['query' => 'payroll'])->assertJsonCount(0, 'data.results');
    $this->getJson("{$base}/document?".http_build_query(['source' => 'secret.md', 'collection' => $agent->artifactKnowledgeCollection()]))->assertNotFound();
});

it('drops an ingest that carries an older revision than the stored document', function () {
    Embeddings::fake();
    [$workspace] = ownerWorkspaceForKnowledgeBase();
    $knowledgeBase = app(KnowledgeBase::class);

    $knowledgeBase->ingest($workspace, 'Newer text.', 'policy.md', 'support', replaceSource: true, revision: 200);
    $stale = $knowledgeBase->ingest($workspace, 'Older text.', 'policy.md', 'support', replaceSource: true, revision: 100);

    expect($stale)->toBeEmpty()
        ->and(DocumentEmbedding::where('collection', 'support')->sole()->chunk_text)->toBe('Newer text.');
});
