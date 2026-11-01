<?php

use App\Ai\Agents\PageDesignerAgent;
use App\Ai\Tools\SubmitPageTool;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Role;
use App\Events\Pages\PageActivity;
use App\Jobs\Pages\GeneratePageJob;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageComment;
use App\Models\Workflows\WorkflowPageGeneration;
use App\Models\Workflows\WorkflowPageVersion;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workflows\Pages\PageVersions;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Olive']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Note writer']);
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'label' => 'Topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ input.topic }}'], 'position' => ['x' => 300, 'y' => 0]],
        ],
        'edges' => [['from' => 'topic', 'to' => 'note_out']],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $this->content = fn (string $title): array => app(PageSchema::class)->normalize(['blocks' => [
        ['id' => 'title', 'type' => 'heading', 'props' => ['text' => $title, 'level' => 1]],
        ['id' => 'topic', 'type' => 'field', 'props' => ['field_key' => 'topic']],
        ['id' => 'go', 'type' => 'submit', 'props' => []],
        ['id' => 'note', 'type' => 'output', 'props' => ['output_name' => 'note']],
    ], 'theme' => ['accent' => '#123456']]);

    $this->page = WorkflowPage::factory()->forWorkspace($this->workspace)->create([
        'workflow_id' => $this->workflow->id,
        'content' => ($this->content)('First'),
    ]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/pages/{$this->page->id}";

    Passport::actingAs($this->owner);
});

function pageMemberAs(Role $role): User
{
    $user = User::factory()->create();
    test()->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    return $user;
}

it('keeps a version for every publish and for each save', function () {
    $this->postJson("{$this->base}/publish")->assertOk();
    $this->patchJson($this->base, ['content' => ($this->content)('Second')])->assertOk();
    $this->postJson("{$this->base}/versions", ['label' => 'Before the redesign'])->assertCreated();

    $versions = $this->getJson("{$this->base}/versions")->assertOk()->json('data.versions');

    expect(array_column($versions, 'number'))->toBe([2, 1]);
    expect(array_column($versions, 'kind'))->toBe(['saved', 'published']);
    expect($versions[0]['label'])->toBe('Before the redesign');
    expect($versions[0])->not->toHaveKey('content');

    $one = $this->getJson("{$this->base}/versions/{$versions[1]['id']}")->assertOk()->json('data.version');
    expect($one['content']['blocks'][0]['props']['text'])->toBe('First');
});

it('restores a version into the draft and keeps what it replaced', function () {
    $this->postJson("{$this->base}/versions")->assertCreated();
    $this->patchJson($this->base, ['content' => ($this->content)('Changed')])->assertOk();
    $first = $this->page->versions()->firstOrFail();

    $this->postJson("{$this->base}/versions/{$first->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.page.content.blocks.0.props.text', 'First');

    $backup = $this->page->versions()->where('kind', WorkflowPageVersion::BeforeRestore)->firstOrFail();
    expect($backup->content['blocks'][0]['props']['text'])->toBe('Changed');
    expect($backup->label)->toBe('Before restoring version 1');
});

it('keeps only the newest versions', function () {
    $versions = app(PageVersions::class);

    foreach (range(1, PageVersions::KEEP + 3) as $i) {
        $versions->snapshot($this->page, WorkflowPageVersion::Saved, $this->owner);
    }

    expect($this->page->versions()->count())->toBe(PageVersions::KEEP);
    expect($this->page->versions()->min('number'))->toBe(4);
});

it('keeps version history to people who build pages', function () {
    Passport::actingAs(pageMemberAs(Role::Viewer));

    $this->getJson("{$this->base}/versions")->assertForbidden();
});

it('threads comments on blocks, and tells the builders', function () {
    Event::fake([PageActivity::class]);

    $thread = $this->postJson("{$this->base}/comments", ['body' => 'Shorter title?', 'block_id' => 'title'])->assertCreated()->json('data.comment');
    $this->postJson("{$this->base}/comments", ['body' => 'Agreed', 'parent_id' => $thread['id'], 'block_id' => 'ignored'])->assertCreated();

    $comments = $this->getJson("{$this->base}/comments")->assertOk()->json('data.comments');

    expect($comments)->toHaveCount(1);
    expect($comments[0])->toMatchArray(['block_id' => 'title', 'body' => 'Shorter title?', 'is_mine' => true]);
    expect($comments[0]['author']['name'])->toBe('Olive');
    expect($comments[0]['replies'][0])->toMatchArray(['body' => 'Agreed', 'block_id' => 'title']);

    Event::assertDispatched(PageActivity::class, fn (PageActivity $event): bool => $event->type === 'comments' && $event->payload['thread_id'] === $thread['id']);
});

it('lets the author edit and anyone who builds pages resolve', function () {
    $viewer = pageMemberAs(Role::Viewer);
    Passport::actingAs($viewer);
    $thread = $this->postJson("{$this->base}/comments", ['body' => 'Typo here'])->assertCreated()->json('data.comment');

    $this->patchJson("{$this->base}/comments/{$thread['id']}", ['body' => 'Typo in the title'])->assertOk();

    Passport::actingAs($this->owner);
    $this->patchJson("{$this->base}/comments/{$thread['id']}", ['body' => 'Not mine'])->assertForbidden();
    $this->patchJson("{$this->base}/comments/{$thread['id']}", ['resolved' => true])
        ->assertOk()
        ->assertJsonPath('data.comment.resolved_by.name', 'Olive');

    $other = pageMemberAs(Role::Viewer);
    Passport::actingAs($other);
    $this->patchJson("{$this->base}/comments/{$thread['id']}", ['resolved' => false])->assertForbidden();
    $this->deleteJson("{$this->base}/comments/{$thread['id']}")->assertForbidden();

    Passport::actingAs($viewer);
    $this->deleteJson("{$this->base}/comments/{$thread['id']}")->assertNoContent();
    expect(WorkflowPageComment::query()->count())->toBe(0);
});

it('designs a page with AI, bound to the workflow, and charges for it', function () {
    Event::fake([PageActivity::class]);

    PageDesignerAgent::fake([new ToolCall('call_1', SubmitPageTool::NAME, [
        'theme' => ['accent' => '#ff0000', 'appearance' => 'dark', 'background' => 'plain', 'radius' => 'lg', 'font' => 'serif', 'width' => 'wide'],
        'blocks' => [
            ['type' => 'heading', 'props' => ['text' => 'Write a better note', 'level' => 1]],
            ['type' => 'section', 'props' => ['background' => 'subtle'], 'children' => [
                ['type' => 'field', 'props' => ['field_key' => 'made_up_input', 'label' => 'Invented']],
            ]],
            ['type' => 'output', 'props' => ['output_name' => 'note']],
        ],
    ])]);

    $generation = $this->postJson("{$this->base}/generations", ['prompt' => 'A bold page for note writing', 'keep_theme' => false])
        ->assertStatus(202)
        ->json('data.generation');

    $done = $this->getJson("{$this->base}/generations/{$generation['id']}")->assertOk()->json('data.generation');

    expect($done['status'])->toBe('completed');
    expect($done['content']['theme']['accent'])->toBe('#ff0000');
    expect($done['content']['blocks'][0]['props']['text'])->toBe('Write a better note');

    $field = $done['content']['blocks'][1]['children'][0];
    expect($field['props'])->toMatchArray(['field_key' => 'topic', 'label' => null]);
    expect(collect(PageSchema::flatten($done['content']['blocks']))->pluck('type'))->toContain('submit');
    expect(app(PageSchema::class)->issues($done['content'], $this->workflow))->toBe([]);

    expect($this->page->fresh()->content)->toBe(($this->content)('First'));
    expect(CreditTransaction::query()->where('source_type', CreditTransactionType::PageGeneration)->where('source_id', $generation['id'])->exists())->toBeTrue();
    Event::assertDispatched(PageActivity::class, fn (PageActivity $event): bool => $event->type === 'generation' && $event->payload['status'] === 'completed');
});

it('keeps the page’s theme unless asked not to', function () {
    PageDesignerAgent::fake([new ToolCall('call_1', SubmitPageTool::NAME, [
        'theme' => ['accent' => '#ff0000'],
        'blocks' => [['type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 1]]],
    ])]);

    $id = $this->postJson("{$this->base}/generations", ['prompt' => 'Simpler please'])->assertStatus(202)->json('data.generation.id');

    expect(WorkflowPageGeneration::query()->findOrFail($id)->content['theme']['accent'])->toBe('#123456');
});

it('records a failed generation instead of losing it', function () {
    PageDesignerAgent::fake(['I would rather chat.']);

    $id = $this->postJson("{$this->base}/generations", ['prompt' => 'Anything'])->assertStatus(202)->json('data.generation.id');
    $generation = WorkflowPageGeneration::query()->findOrFail($id);

    expect($generation->status)->toBe('failed');
    expect($generation->error)->toBe(GeneratePageJob::FAILURE_MESSAGE);
});

it('only generates version B when the page has one', function () {
    $this->postJson("{$this->base}/generations", ['prompt' => 'Anything', 'variant' => 'b'])->assertConflict();
});

it('refuses a draft saved over changes made since it was loaded', function () {
    $revision = $this->getJson($this->base)->assertOk()->json('data.page.draft_revision');

    $this->patchJson($this->base, ['content' => ($this->content)('Mine'), 'base_revision' => $revision])
        ->assertOk()
        ->assertJsonPath('data.page.draft_revision', $revision + 1);

    $this->patchJson($this->base, ['content' => ($this->content)('Stale tab'), 'base_revision' => $revision])->assertConflict();
    expect($this->page->fresh()->content['blocks'][0]['props']['text'])->toBe('Mine');

    $this->patchJson($this->base, ['content' => ($this->content)('Kept anyway')])->assertOk();
    $this->patchJson($this->base, ['name' => 'Renamed', 'base_revision' => 0])->assertOk();
});
