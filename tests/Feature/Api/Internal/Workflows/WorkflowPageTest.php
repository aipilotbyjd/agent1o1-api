<?php

use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Role;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\AuditLog;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Olive Owner']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Note writer']);
    $this->workflow->replaceGraph(pageWorkflowGraph());
    $this->workflow->publishVersion(publisher: $this->owner);

    $this->base = "/api/v1/workspaces/{$this->workspace->id}/pages";

    Passport::actingAs($this->owner);
});

/**
 * Two inputs (one required), a step that writes a note, and two outputs:
 * `note`, which pages show, and `internal`, which they shouldn't leak.
 *
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function pageWorkflowGraph(): array
{
    return [
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'label' => 'Topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'tone', 'type' => 'workflow_input', 'config' => ['name' => 'tone', 'default' => 'calm'], 'position' => ['x' => 0, 'y' => 100]],
            ['key' => 'write', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'text', 'value' => 'A {{ nodes.tone.value }} note about {{ nodes.topic.value }}'],
            ]], 'position' => ['x' => 300, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ nodes.write.text }}'], 'position' => ['x' => 600, 'y' => 0]],
            ['key' => 'internal_out', 'type' => 'workflow_output', 'config' => ['name' => 'internal', 'value' => 'secret-sauce'], 'position' => ['x' => 600, 'y' => 100]],
        ],
        'edges' => [
            ['from' => 'topic', 'to' => 'write'],
            ['from' => 'tone', 'to' => 'write'],
            ['from' => 'write', 'to' => 'note_out'],
            ['from' => 'write', 'to' => 'internal_out'],
        ],
    ];
}

function pageCreate(array $attributes = []): array
{
    return test()->postJson(test()->base, ['workflow_id' => test()->workflow->id, ...$attributes])
        ->assertCreated()
        ->json('data.page');
}

/**
 * The page's content with only the `note` output kept.
 *
 * @param  array<string, mixed>  $page
 * @return array<string, mixed>
 */
function pageWithoutInternal(array $page): array
{
    $content = $page['content'];
    $content['blocks'] = array_values(array_filter(
        $content['blocks'],
        fn (array $block): bool => ! ($block['type'] === 'output' && $block['props']['output_name'] === 'internal'),
    ));

    return $content;
}

function pageMember(Role $role): User
{
    $user = User::factory()->create();
    test()->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

    return $user;
}

it('starts a new page from the workflow it runs', function () {
    $page = pageCreate();

    expect($page['name'])->toBe('Note writer');
    expect($page['is_published'])->toBeFalse();
    expect($page['access'])->toBe('workspace');

    $blocks = collect($page['content']['blocks']);

    expect($blocks->first()['type'])->toBe('heading');
    expect($blocks->where('type', 'field')->pluck('props.field_key')->all())->toBe(['topic', 'tone']);
    expect($blocks->where('type', 'submit'))->toHaveCount(1);
    expect($blocks->where('type', 'output')->pluck('props.output_name')->all())->toBe(['note', 'internal']);
    expect($page['issues'])->toBe([]);
});

it('lists the outputs a workflow returns alongside its interface', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/interface")
        ->assertOk()
        ->assertJsonPath('data.interface.outputs', [['name' => 'note'], ['name' => 'internal']]);
});

it('puts saved content into its canonical shape', function () {
    $page = pageCreate();

    $content = $this->patchJson("{$this->base}/{$page['id']}", ['content' => [
        'blocks' => [
            ['id' => 'pic', 'type' => 'image', 'props' => ['url' => 'javascript:alert(1)', 'height' => 'huge']],
            ['id' => 'pic', 'type' => 'heading', 'width' => 'half', 'props' => ['text' => '  Hello  ', 'level' => '1', 'extra' => 'dropped']],
            ['id' => 'line', 'type' => 'divider', 'props' => []],
        ],
        'theme' => ['accent' => '#ABCDEF', 'font' => 'comic-sans'],
    ]])->assertOk()->json('data.page.content');

    expect($content['blocks'][0]['props'])->toMatchArray(['url' => null, 'height' => 'md']);
    expect($content['blocks'][1]['id'])->not->toBe('pic');
    expect($content['blocks'][1]['width'])->toBe('half');
    expect($content['blocks'][1]['props'])->toBe(['text' => 'Hello', 'level' => 1, 'align' => 'left']);
    expect($content['blocks'][2]['props'])->toBe(['style' => 'solid']);
    expect($content['theme'])->toMatchArray(['accent' => '#abcdef', 'font' => 'sans']);
});

it('refuses blocks it does not know', function () {
    $page = pageCreate();

    $this->patchJson("{$this->base}/{$page['id']}", ['content' => ['blocks' => [['id' => 'x', 'type' => 'iframe']]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content.blocks.0.type']);
});

it('reports what stops a page from being published, and refuses to publish it', function () {
    $page = pageCreate();

    $page = $this->patchJson("{$this->base}/{$page['id']}", ['content' => ['blocks' => [
        ['id' => 'ghost', 'type' => 'field', 'props' => ['field_key' => 'nope']],
        ['id' => 'gone', 'type' => 'output', 'props' => ['output_name' => 'missing']],
    ]]])->assertOk()->json('data.page');

    expect(collect($page['issues'])->pluck('code')->all())->toBe([
        'unknown_field', 'missing_required_field', 'no_submit', 'unknown_output',
    ]);
    expect(collect($page['issues'])->firstWhere('code', 'unknown_field')['block_id'])->toBe('ghost');

    $this->postJson("{$this->base}/{$page['id']}/publish")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    expect(WorkflowPage::find($page['id'])->published_content)->toBeNull();
});

it('publishes the draft, and tracks later edits as unpublished changes', function () {
    $page = pageCreate();

    $published = $this->postJson("{$this->base}/{$page['id']}/publish")->assertOk()->json('data.page');

    expect($published['is_published'])->toBeTrue();
    expect($published['has_unpublished_changes'])->toBeFalse();
    expect($published['published_by']['name'])->toBe('Olive Owner');
    expect(AuditLog::query()->where('action', AuditAction::PagePublished)->exists())->toBeTrue();

    $edited = $this->patchJson("{$this->base}/{$page['id']}", ['name' => 'Notes'])->assertOk()->json('data.page');
    expect($edited['has_unpublished_changes'])->toBeFalse();

    $edited = $this->patchJson("{$this->base}/{$page['id']}", ['content' => pageWithoutInternal($page)])->assertOk()->json('data.page');
    expect($edited['has_unpublished_changes'])->toBeTrue();
});

it('runs the workflow for a member and shows only the outputs on the page', function () {
    $page = pageCreate();
    $this->patchJson("{$this->base}/{$page['id']}", ['content' => pageWithoutInternal($page)])->assertOk();
    $this->postJson("{$this->base}/{$page['id']}/publish")->assertOk();

    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['input.topic']);

    $submission = $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain']])
        ->assertStatus(202)
        ->json('data.submission');

    expect($submission['state'])->toBe('completed');
    expect($submission['outputs'])->toBe(['note' => 'A calm note about rain']);
    expect($submission['progress']['total'])->toBe(5);

    $run = Run::find($submission['run_id']);
    expect($run->trigger_type)->toBe('page');
    expect($run->triggered_by)->toBe($this->owner->id);

    $this->getJson("{$this->base}/{$page['id']}/submissions/{$submission['id']}")
        ->assertOk()
        ->assertJsonPath('data.submission.outputs', ['note' => 'A calm note about rain']);

    $this->getJson("{$this->base}/{$page['id']}/submissions")
        ->assertOk()
        ->assertJsonPath('data.submissions.0.user.name', 'Olive Owner')
        ->assertJsonPath('data.submissions.0.run.status', 'completed');
});

it('only runs a published page, except as a test from the builder', function () {
    $page = pageCreate();

    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain']])->assertStatus(409);

    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain'], 'test' => true])
        ->assertStatus(202)
        ->assertJsonPath('data.submission.is_test', true)
        ->assertJsonPath('data.submission.outputs.internal', 'secret-sauce');
});

it('serves the page a member uses with each field described', function () {
    $page = pageCreate();

    $this->getJson("{$this->base}/{$page['id']}/view")->assertNotFound();

    $this->postJson("{$this->base}/{$page['id']}/publish")->assertOk();

    $view = $this->getJson("{$this->base}/{$page['id']}/view")->assertOk()->json('data.view');
    $field = collect($view['blocks'])->firstWhere('type', 'field');

    expect($field['field'])->toMatchArray(['key' => 'topic', 'label' => 'Topic', 'required' => true]);
    expect($view)->not->toHaveKey('workflow_id');
});

it('lets viewers use nothing and change nothing', function () {
    $page = pageCreate();
    $this->postJson("{$this->base}/{$page['id']}/publish")->assertOk();

    Passport::actingAs(pageMember(Role::Viewer));

    $this->getJson($this->base)->assertOk()->assertJsonCount(1, 'data.pages');
    $this->patchJson("{$this->base}/{$page['id']}", ['name' => 'Mine'])->assertForbidden();
    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain']])->assertForbidden();

    Passport::actingAs(pageMember(Role::Member));

    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain']])->assertStatus(202);
    $this->postJson("{$this->base}/{$page['id']}/submissions", ['input' => ['topic' => 'rain'], 'test' => true])->assertForbidden();
});

it('keeps sharing outside the workspace to plans that include it', function () {
    $page = pageCreate();

    $this->patchJson("{$this->base}/{$page['id']}", ['access' => 'link'])->assertForbidden();

    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['public_pages' => true]]))
        ->active()
        ->create();

    $this->patchJson("{$this->base}/{$page['id']}", ['access' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->patchJson("{$this->base}/{$page['id']}", ['access' => 'password', 'password' => 'open-sesame'])
        ->assertOk()
        ->assertJsonPath('data.page.access', 'password')
        ->assertJsonPath('data.page.has_password', true)
        ->assertJsonMissingPath('data.page.password');

    expect(AuditLog::query()->where('action', AuditAction::PageAccessChanged)->exists())->toBeTrue();
});

it('holds the workspace to its plan page limit', function () {
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['limits' => ['pages' => 1], 'credits_monthly' => 1_000_000]))
        ->active()
        ->create();

    pageCreate();

    $this->postJson($this->base, ['workflow_id' => $this->workflow->id])->assertStatus(402);
});

it('says when the workflow behind a page is in the trash', function () {
    $page = pageCreate();

    $this->workflow->delete();

    $this->getJson("{$this->base}/{$page['id']}")
        ->assertOk()
        ->assertJsonPath('data.page.issues.0.code', 'workflow_missing')
        ->assertJsonPath('data.page.workflow.trashed', true);
});

it('will not build a page on another workspace’s workflow', function () {
    $stranger = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $theirs = Workflow::factory()->forWorkspace($stranger)->create();

    $this->postJson($this->base, ['workflow_id' => $theirs->id])->assertNotFound();
});

it('saves the link card and shows the preview it makes', function () {
    $page = pageCreate();

    $this->patchJson("{$this->base}/{$page['id']}", ['share_title' => '  Note writer  ', 'share_description' => '', 'share_image_url' => 'https://images.example.com/og.png'])
        ->assertOk()
        ->assertJsonPath('data.page.share', ['title' => 'Note writer', 'description' => null, 'image_url' => 'https://images.example.com/og.png'])
        ->assertJsonPath('data.page.share_preview.title', 'Note writer')
        ->assertJsonPath('data.page.share_preview.image_url', 'https://images.example.com/og.png');

    $this->patchJson("{$this->base}/{$page['id']}", ['share_image_url' => 'javascript:alert(1)'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('share_image_url');
});
