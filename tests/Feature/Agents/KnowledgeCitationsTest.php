<?php

use App\Ai\Agents\Workspace\WorkspaceAgent;
use App\Ai\Tools\Knowledge\SearchKnowledgeTool;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\Agents\KnowledgeMode;
use App\Http\Resources\Api\Internal\V1\Agents\AgentMessageResource;
use App\Http\Resources\Api\Public\V1\AgentMessageResource as PublicAgentMessageResource;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\KnowledgeBase;
use App\Services\Agents\KnowledgeCitations;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create(['allow_self_clone' => false]);
    $this->session = $this->agent->sessions()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    $this->refunds = DocumentEmbedding::create([
        'workspace_id' => $this->workspace->id,
        'collection' => 'policies',
        'source' => 'Refund policy',
        'chunk_text' => 'Refunds are paid within 5 business days.',
        'embedding' => [1.0, 0.0],
        'metadata' => ['url' => 'https://help.acme.test/refunds'],
    ]);

    $this->searchTool = ToolNameResolver::resolve(new SearchKnowledgeTool($this->workspace, app(KnowledgeBase::class)));
    $this->ref = KnowledgeCitations::chunkRef($this->refunds->id);
});

it('gives every search hit a ref the agent can cite', function () {
    Embeddings::fake([[[1.0, 0.0]]]);

    $results = json_decode((string) (new SearchKnowledgeTool($this->workspace, app(KnowledgeBase::class)))->handle(new Request(['query' => 'refunds'])), true);

    expect($results[0])->toMatchArray(['ref' => $this->ref, 'source' => 'Refund policy', 'url' => 'https://help.acme.test/refunds']);
});

it('resolves the reply\'s citations to the sources it was handed, and drops made-up ones', function () {
    Embeddings::fake([[[1.0, 0.0]]]);
    WorkspaceAgent::fake([
        new ToolCall('call-1', $this->searchTool, ['query' => 'refund timing']),
        "Refunds are paid within 5 business days [{$this->ref}]. Store credit is instant [KABCDEF].",
    ]);

    $reply = app(AgentRunner::class)->run($this->session, 'How long do refunds take?');

    expect($reply->citations)->toHaveCount(1)
        ->and($reply->citations[0])->toMatchArray([
            'number' => 1,
            'ref' => $this->ref,
            'kind' => 'chunk',
            'collection' => 'policies',
            'source' => 'Refund policy',
            'url' => 'https://help.acme.test/refunds',
            'snippet' => 'Refunds are paid within 5 business days.',
        ]);

    // The stored text keeps the markers; the run's output is plain text.
    expect($reply->content)->toContain("[{$this->ref}]");
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->output['text'])
        ->toBe('Refunds are paid within 5 business days [1]. Store credit is instant.');
});

it('resolves a ref the agent cites again in a later turn without searching', function () {
    Embeddings::fake([[[1.0, 0.0]]]);
    WorkspaceAgent::fake([
        new ToolCall('call-1', $this->searchTool, ['query' => 'refund timing']),
        "Within 5 business days [{$this->ref}].",
        "As I said, 5 business days [{$this->ref}].",
    ]);

    app(AgentRunner::class)->run($this->session, 'How long do refunds take?');
    $second = app(AgentRunner::class)->run($this->session, 'Sorry, how long again?');

    expect($second->citations)->toHaveCount(1)
        ->and($second->citations[0]['ref'])->toBe($this->ref);
});

it('resolves a cited always-included document', function () {
    DocumentEmbedding::create(['workspace_id' => $this->workspace->id, 'collection' => 'hours', 'source' => 'Opening hours', 'chunk_text' => 'We are open 9-5 on weekdays.', 'embedding' => [0.0, 1.0]]);
    $this->agent->knowledgeCollections()->create(['collection' => 'hours', 'mode' => KnowledgeMode::Always]);

    $ref = KnowledgeCitations::documentRef('hours', 'Opening hours');
    WorkspaceAgent::fake(["We are open 9-5 on weekdays [{$ref}]."]);

    $reply = app(AgentRunner::class)->run($this->session, 'When are you open?');

    expect($reply->citations[0])->toMatchArray(['number' => 1, 'ref' => $ref, 'kind' => 'document', 'source' => 'Opening hours', 'snippet' => 'We are open 9-5 on weekdays.']);
    WorkspaceAgent::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), "(ref: {$ref})"));
});

it('numbers sources by first appearance, including several in one marker', function () {
    $citations = app(KnowledgeCitations::class);

    $resolved = $citations->resolve('A [K222222, k111111]. B [K111111].', [[
        'id' => 'call-1',
        'name' => 'SearchKnowledgeTool',
        'result' => json_encode([
            ['ref' => 'K111111', 'source' => 'one', 'text' => 'One.'],
            ['ref' => 'K222222', 'source' => 'two', 'text' => 'Two.'],
        ]),
    ]]);

    expect(array_column($resolved, 'ref'))->toBe(['K222222', 'K111111']);
    expect($citations->plain('A [K222222, k111111]. B [K111111].', $resolved))->toBe('A [1][2]. B [2].');
});

it('exposes citations to the chat and plain numbered text to the public API', function () {
    $message = $this->session->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => "Five days [{$this->ref}]."]);
    $message->forceFill(['citations' => [['number' => 1, 'ref' => $this->ref, 'kind' => 'chunk', 'collection' => 'policies', 'source' => 'Refund policy', 'url' => null, 'snippet' => 'Refunds…']]])->save();

    $internal = AgentMessageResource::make($message)->resolve();
    expect($internal['citations'][0]['ref'])->toBe($this->ref)
        ->and($internal['content'])->toBe("Five days [{$this->ref}].");

    $public = PublicAgentMessageResource::make($message)->resolve();
    expect($public['content'])->toBe('Five days [1].')
        ->and($public['citations'][0])->toBe(['number' => 1, 'collection' => 'policies', 'source' => 'Refund policy', 'url' => null, 'snippet' => 'Refunds…']);
});
