<?php

use App\Exceptions\InsufficientCreditsException;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Services\Billing\CreditGate;
use App\Services\Workflows\Pages\PageSchema;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['public_pages' => true], 'credits_monthly' => 1_000_000]))
        ->active()
        ->create();

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Note writer']);
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'topic', 'type' => 'workflow_input', 'config' => ['name' => 'topic', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'write', 'type' => 'run_code', 'config' => ['operations' => [
                ['op' => 'set', 'output' => 'text', 'value' => 'About {{ nodes.topic.value }}'],
            ]], 'position' => ['x' => 300, 'y' => 0]],
            ['key' => 'note_out', 'type' => 'workflow_output', 'config' => ['name' => 'note', 'value' => '{{ nodes.write.text }}'], 'position' => ['x' => 600, 'y' => 0]],
            ['key' => 'internal_out', 'type' => 'workflow_output', 'config' => ['name' => 'internal', 'value' => 'secret-sauce'], 'position' => ['x' => 600, 'y' => 100]],
        ],
        'edges' => [
            ['from' => 'topic', 'to' => 'write'],
            ['from' => 'write', 'to' => 'note_out'],
            ['from' => 'write', 'to' => 'internal_out'],
        ],
    ]);
    $this->workflow->publishVersion(publisher: $this->owner);

    $schema = app(PageSchema::class);
    $content = $schema->normalize(['blocks' => [
        ['id' => 'title', 'type' => 'heading', 'props' => ['text' => 'Write a note']],
        ['id' => 'topic', 'type' => 'field', 'props' => ['field_key' => 'topic']],
        ['id' => 'go', 'type' => 'submit', 'props' => []],
        ['id' => 'note', 'type' => 'output', 'props' => ['output_name' => 'note']],
    ]]);

    $this->page = WorkflowPage::query()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'created_by' => $this->owner->id,
        'name' => 'Notes',
        'content' => $content,
        'access' => 'link',
    ]);
    $this->page->forceFill(['published_content' => $content, 'published_by' => $this->owner->id, 'published_at' => now()])->save();

    $this->url = "/api/v1/p/{$this->page->public_token}";
});

it('serves a shared page without the workflow behind it', function () {
    $page = $this->getJson($this->url)->assertOk()->json('data.page');

    expect($page['name'])->toBe('Notes');
    expect($page['locked'])->toBeFalse();
    expect(collect($page['blocks'])->pluck('type')->all())->toBe(['heading', 'field', 'submit', 'output']);
    expect($page['blocks'][1]['field'])->toMatchArray(['key' => 'topic', 'required' => true]);
    expect(json_encode($page))->not->toContain($this->workflow->id)->not->toContain('run_code');
});

it('runs the workflow for a visitor and returns only what the page shows', function () {
    $this->postJson("{$this->url}/submissions", ['input' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['input.topic']);

    $response = $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'rain']])->assertStatus(202);
    $submission = $response->json('data.submission');
    $secret = $response->json('data.secret');

    expect($submission['state'])->toBe('completed');
    expect($submission['outputs'])->toBe(['note' => 'About rain']);
    expect($submission)->not->toHaveKey('run_id');

    $this->getJson("{$this->url}/submissions/{$submission['id']}")->assertNotFound();
    $this->getJson("{$this->url}/submissions/{$submission['id']}", ['X-Submission-Secret' => 'guess'])->assertNotFound();
    $this->getJson("{$this->url}/submissions/{$submission['id']}", ['X-Submission-Secret' => $secret])
        ->assertOk()
        ->assertJsonPath('data.submission.outputs', ['note' => 'About rain']);

    $stored = $this->page->submissions()->sole();
    expect($stored->user_id)->toBeNull();
    expect($stored->run->triggered_by)->toBe($this->owner->id);
});

it('does not answer for a page that is private, unpublished, turned off or relinked', function () {
    $this->page->update(['access' => 'workspace']);
    $this->getJson($this->url)->assertNotFound();

    $this->page->update(['access' => 'link', 'is_enabled' => false]);
    $this->getJson($this->url)->assertNotFound();

    $this->page->update(['is_enabled' => true]);
    $this->page->forceFill(['published_content' => null])->save();
    $this->getJson($this->url)->assertNotFound();
    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'x']])->assertNotFound();

    $this->page->forceFill(['published_content' => $this->page->content])->save();
    $this->getJson($this->url)->assertOk();

    $this->page->forceFill(['public_token' => WorkflowPage::newToken()])->save();
    $this->getJson($this->url)->assertNotFound();
});

it('asks for the password before showing or running a protected page', function () {
    $this->page->password = 'open-sesame';
    $this->page->access = 'password';
    $this->page->save();

    $locked = $this->getJson($this->url)->assertOk()->json('data.page');
    expect($locked['locked'])->toBeTrue();
    expect($locked['requires_password'])->toBeTrue();
    expect($locked['blocks'])->toBe([]);

    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'rain']])->assertForbidden();
    $this->postJson("{$this->url}/unlock", ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $token = $this->postJson("{$this->url}/unlock", ['password' => 'open-sesame'])->assertOk()->json('data.access_token');

    $this->getJson($this->url, ['X-Page-Access' => $token])->assertOk()->assertJsonPath('data.page.locked', false);
    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'rain']], ['X-Page-Access' => $token])->assertStatus(202);

    $this->page->password = 'new-password';
    $this->page->save();

    $this->getJson($this->url, ['X-Page-Access' => $token])->assertOk()->assertJsonPath('data.page.locked', true);
});

it('stops a page at its daily limit', function () {
    $this->page->update(['max_runs_per_day' => 1]);

    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'one']])->assertStatus(202);
    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'two']])
        ->assertStatus(429)
        ->assertJsonPath('message', 'This page has reached its limit for today. Try again tomorrow.');
});

it('tells a visitor only that the page cannot run when the workspace is out of credits', function () {
    $this->mock(CreditGate::class, fn ($mock) => $mock->shouldReceive('assertCanStartWorkflowRun')->andThrow(new InsufficientCreditsException(1, 0)));

    $this->postJson("{$this->url}/submissions", ['input' => ['topic' => 'rain']])
        ->assertStatus(503)
        ->assertJsonPath('message', "This page can't run right now. Please try again later.");

    expect($this->page->submissions()->count())->toBe(0);
});

it('describes the link card from the published page', function () {
    $content = app(PageSchema::class)->normalize([
        'blocks' => [
            ['id' => 'title', 'type' => 'heading', 'props' => ['text' => 'Write a note']],
            ['id' => 'intro', 'type' => 'text', 'props' => ['markdown' => "## Hello\n\nGet a **short** note about [any topic](https://example.com) in seconds."]],
            ['id' => 'topic', 'type' => 'field', 'props' => ['field_key' => 'topic']],
            ['id' => 'go', 'type' => 'submit', 'props' => []],
        ],
        'theme' => ['accent' => '#16a34a', 'cover_url' => 'https://images.example.com/cover.jpg'],
    ]);
    $this->page->forceFill(['published_content' => $content])->save();

    $this->getJson("{$this->url}/meta")
        ->assertOk()
        ->assertJsonPath('data.meta', [
            'title' => 'Notes',
            'description' => 'Get a short note about any topic in seconds.',
            'image_url' => 'https://images.example.com/cover.jpg',
            'theme_color' => '#16a34a',
            'site_name' => 'Acme',
        ]);
});

it('uses the link card the builder chose over the page', function () {
    $this->page->update(['share_title' => 'Free note writer', 'share_description' => 'Try it now.', 'share_image_url' => 'https://images.example.com/og.png']);

    $this->getJson("{$this->url}/meta")
        ->assertOk()
        ->assertJsonPath('data.meta.title', 'Free note writer')
        ->assertJsonPath('data.meta.description', 'Try it now.')
        ->assertJsonPath('data.meta.image_url', 'https://images.example.com/og.png');
});

it('describes a password page’s card but hides a private page’s', function () {
    $this->page->forceFill(['access' => 'password', 'password' => 'secret-pass'])->save();
    $this->getJson("{$this->url}/meta")->assertOk()->assertJsonPath('data.meta.title', 'Notes');

    $this->page->forceFill(['access' => 'workspace', 'password' => null])->save();
    $this->getJson("{$this->url}/meta")->assertNotFound();
});
