<?php

use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageEvent;
use App\Models\Workflows\WorkflowPageSubmission;
use App\Services\Workflows\Pages\PageInsights;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['public_pages' => true], 'credits_monthly' => 1_000_000]))
        ->active()
        ->create();

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => 'About {{ nodes.topic.value }}'], 'position' => ['x' => 300, 'y' => 0]],
        ],
        'edges' => [['from' => 'topic', 'to' => 'note_out']],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $this->content = fn (string $title): array => app(PageSchema::class)->normalize(['blocks' => [
        ['id' => 'title', 'type' => 'heading', 'props' => ['text' => $title]],
        ['id' => 'topic', 'type' => 'field', 'props' => ['field_key' => 'topic']],
        ['id' => 'go', 'type' => 'submit', 'props' => []],
        ['id' => 'note', 'type' => 'output', 'props' => ['output_name' => 'note']],
    ]]);

    $this->page = WorkflowPage::query()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'created_by' => $this->owner->id,
        'name' => 'Notes',
        'content' => ($this->content)('Version A'),
        'access' => 'link',
    ]);
    $this->page->forceFill(['published_content' => $this->page->content, 'published_by' => $this->owner->id, 'published_at' => now()])->save();

    $this->public = "/api/v1/p/{$this->page->public_token}";
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/pages/{$this->page->id}";
});

/**
 * A visitor id that `PageInsights::variantFor()` puts in `$variant` for the test page.
 */
function visitorIn(WorkflowPage $page, string $variant): string
{
    $insights = app(PageInsights::class);

    for ($i = 0; $i < 500; $i++) {
        $id = "visitor-{$i}-abc";
        $request = request()->duplicate();
        $request->headers->set(PageInsights::VISITOR_HEADER, $id);

        if ($insights->variantFor($page, $insights->visitor($page, $request)) === $variant) {
            return $id;
        }
    }

    throw new RuntimeException('No visitor lands in that variant.');
}

it('counts a visitor’s views and starts once a day', function () {
    $headers = [PageInsights::VISITOR_HEADER => 'visitor-one-123'];

    $this->getJson($this->public, $headers)->assertOk();
    $this->getJson($this->public, $headers)->assertOk();
    $this->getJson($this->public, [PageInsights::VISITOR_HEADER => 'visitor-two-456'])->assertOk();
    $this->postJson("{$this->public}/events", ['type' => 'start'], $headers)->assertNoContent();
    $this->postJson("{$this->public}/events", ['type' => 'start'], $headers)->assertNoContent();
    $this->postJson("{$this->public}/events", ['type' => 'view'], $headers)->assertUnprocessable();

    expect(WorkflowPageEvent::query()->where('type', 'view')->count())->toBe(2);
    expect(WorkflowPageEvent::query()->where('type', 'start')->count())->toBe(1);
});

it('splits visitors between the published versions and keeps each on theirs', function () {
    $this->page->forceFill(['variant_content' => ($this->content)('Version B'), 'variant_split' => 50])->save();
    $this->page->forceFill(['published_variant_content' => $this->page->variant_content])->save();

    $a = visitorIn($this->page, 'a');
    $b = visitorIn($this->page, 'b');

    foreach ([[$a, 'Version A'], [$b, 'Version B'], [$b, 'Version B']] as [$visitor, $title]) {
        $this->getJson($this->public, [PageInsights::VISITOR_HEADER => $visitor])
            ->assertOk()
            ->assertJsonPath('data.page.blocks.0.props.text', $title);
    }

    $submission = $this->postJson("{$this->public}/submissions", ['input' => ['topic' => 'cats']], [PageInsights::VISITOR_HEADER => $b])
        ->assertStatus(202)
        ->json('data.submission');

    $stored = WorkflowPageSubmission::query()->findOrFail($submission['id']);

    expect($stored->variant)->toBe('b');
    expect($stored->input)->toBe(['topic' => 'cats']);
});

it('sums up views, runs and rates by day and by version', function () {
    $this->page->forceFill(['variant_content' => ($this->content)('Version B'), 'published_variant_content' => ($this->content)('Version B')])->save();
    $insights = app(PageInsights::class);

    foreach (['v1', 'v2', 'v3', 'v4'] as $visitor) {
        $insights->record($this->page, WorkflowPageEvent::View, $visitor === 'v4' ? 'b' : 'a', $visitor);
    }
    $insights->record($this->page, WorkflowPageEvent::Start, 'a', 'v1');

    Passport::actingAs($this->owner);
    $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'one']])->assertStatus(202);
    $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'test']], [])->assertStatus(202);
    WorkflowPageSubmission::query()->latest()->first()->update(['is_test' => true]);

    $data = $this->getJson("{$this->base}/insights?days=7")->assertOk()->json('data.insights');

    expect($data['totals'])->toMatchArray(['views' => 4, 'starts' => 1, 'submissions' => 1, 'start_rate' => 0.25, 'conversion_rate' => 0.25]);
    expect($data['daily'])->toHaveCount(7);
    expect(end($data['daily'])['views'])->toBe(4);
    expect(collect($data['variants'])->pluck('views', 'variant')->all())->toBe(['a' => 3, 'b' => 1]);

    $withTests = $this->getJson("{$this->base}/insights?days=7&include_tests=1")->json('data.insights.totals');
    expect($withTests['submissions'])->toBe(2);
});

it('filters submissions, pages through them and exports them', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'first']])->assertStatus(202);
    $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'second']])->assertStatus(202);
    $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'draft'], 'test' => true])->assertStatus(202);

    $all = $this->getJson("{$this->base}/submissions?per_page=2")->assertOk();
    expect($all->json('data.meta'))->toMatchArray(['total' => 3, 'per_page' => 2, 'last_page' => 2]);
    expect($all->json('data.submissions.0'))->toHaveKeys(['variant', 'input', 'outputs', 'duration_seconds']);

    $real = $this->getJson("{$this->base}/submissions?include_tests=0")->json('data.submissions');
    expect(collect($real)->pluck('input.topic')->all())->toBe(['second', 'first']);

    $this->getJson("{$this->base}/submissions?status=nope")->assertUnprocessable();

    $csv = $this->get("{$this->base}/submissions/export?include_tests=0")->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

    expect($rows[0])->toBe(['Submitted at', 'Status', 'Person', 'Version', 'Test run', 'Run time (s)', 'topic', 'note']);
    expect($rows)->toHaveCount(3);
    expect(array_column(array_slice($rows, 1), 6))->toBe(['second', 'first']);
});

it('creates, publishes, promotes and removes version B', function () {
    Passport::actingAs($this->owner);

    $page = $this->postJson("{$this->base}/variant")->assertOk()->json('data.page');
    expect($page['variant'])->toMatchArray(['exists' => true, 'live' => false, 'split' => 50]);
    $this->postJson("{$this->base}/variant")->assertConflict();

    $b = ($this->content)('Version B');
    $this->patchJson($this->base, ['variant_content' => $b, 'variant_split' => 30])
        ->assertOk()
        ->assertJsonPath('data.page.variant.content.blocks.0.props.text', 'Version B')
        ->assertJsonPath('data.page.variant.split', 30);

    $this->postJson("{$this->base}/publish")->assertOk()->assertJsonPath('data.page.variant.live', true);

    $promoted = $this->postJson("{$this->base}/variant/promote")->assertOk()->json('data.page');
    expect($promoted['content']['blocks'][0]['props']['text'])->toBe('Version B');
    expect($promoted['variant'])->toMatchArray(['exists' => false, 'live' => false]);
    expect($promoted['has_unpublished_changes'])->toBeTrue();

    $this->postJson("{$this->base}/variant")->assertOk();
    $this->deleteJson("{$this->base}/variant")->assertOk()->assertJsonPath('data.page.variant.exists', false);
});

it('blocks publishing when version B has errors', function () {
    Passport::actingAs($this->owner);
    $this->postJson("{$this->base}/variant")->assertOk();
    $this->patchJson($this->base, ['variant_content' => ['blocks' => [['id' => 'h', 'type' => 'heading', 'props' => ['text' => 'B']]]]])->assertOk();

    $this->postJson("{$this->base}/publish")
        ->assertUnprocessable()
        ->assertJsonFragment(['Version B: Add a Run button so people can start the workflow.']);
});

it('runs a builder test against version B’s draft', function () {
    Passport::actingAs($this->owner);
    $this->postJson("{$this->base}/variant")->assertOk();

    $id = $this->postJson("{$this->base}/submissions", ['input' => ['topic' => 'x'], 'test' => true, 'variant' => 'b'])
        ->assertStatus(202)
        ->json('data.submission.id');

    expect(WorkflowPageSubmission::query()->findOrFail($id)->variant)->toBe('b');
});
