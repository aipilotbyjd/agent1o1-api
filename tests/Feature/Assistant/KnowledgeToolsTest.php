<?php

use App\Ai\Assistant\Tools\ReadKnowledgeEntryTool;
use App\Ai\Assistant\Tools\SaveToKnowledgeTool;
use App\Ai\Assistant\Tools\SearchKnowledgeBaseTool;
use App\Enums\Workspaces\Role;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Services\Agents\Knowledge\KnowledgeSources;
use App\Services\Agents\KnowledgeBase;
use App\Services\Assistant\Tools\ToolCatalog;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    Embeddings::fake();

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->knowledge = app(KnowledgeBase::class);
});

it('offers knowledge tools, without saving in incognito', function () {
    $tools = fn (bool $incognito) => collect(app(ToolCatalog::class)->available($this->assistant))->map->name();
    $session = $this->assistant->sessions()->create(['last_activity_at' => now(), 'incognito' => true]);
    $turn = $session->turns()->create(['status' => 'running']);

    expect($tools(false))->toContain(SearchKnowledgeBaseTool::NAME, ReadKnowledgeEntryTool::NAME, SaveToKnowledgeTool::NAME);

    $incognito = collect(app(ToolCatalog::class)->forTurn($turn, 'openai'))->map(fn ($tool) => method_exists($tool, 'name') ? $tool->name() : null);
    expect($incognito)->toContain(SearchKnowledgeBaseTool::NAME)->not->toContain(SaveToKnowledgeTool::NAME);
});

it('searches the owner\'s private knowledge and shared knowledge, never other members\' private knowledge', function () {
    $other = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $other->id, 'role' => Role::Member, 'joined_at' => now()]);

    $this->knowledge->ingest($this->workspace, 'Globex renews in March.', 'Globex notes', 'personal', ownerId: $this->owner->id);
    $this->knowledge->ingest($this->workspace, 'Globex escalations go to Lee.', 'Support handbook');
    $this->knowledge->ingest($this->workspace, 'Globex is my side project.', 'Secret', 'personal', ownerId: $other->id);

    $results = collect(json_decode((string) (new SearchKnowledgeBaseTool($this->assistant, $this->knowledge))->handle(new Request(['query' => 'Globex'])), true));

    expect($results->pluck('source')->sort()->values()->all())->toBe(['Globex notes', 'Support handbook'])
        ->and($results->firstWhere('source', 'Globex notes')['private'])->toBeTrue();

    $read = (string) (new ReadKnowledgeEntryTool($this->assistant, $this->knowledge))->handle(new Request(['source' => 'Secret']));
    expect($read)->toContain('No document has that source name');
});

it('saves a note privately and replaces it when saved again', function () {
    $tool = new SaveToKnowledgeTool($this->assistant, $this->knowledge, app(KnowledgeSources::class));

    $tool->handle(new Request(['title' => 'Standup', 'text' => 'Ship the billing fix Friday.']));
    $tool->handle(new Request(['title' => 'Standup', 'text' => 'Ship the billing fix Monday.']));

    expect(DocumentEmbedding::query()->sole())
        ->owner_id->toBe($this->owner->id)
        ->source->toBe('Standup')
        ->chunk_text->toBe('Ship the billing fix Monday.');
});
