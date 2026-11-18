<?php

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Models\Runs\Run;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\SampleRuns;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    Http::fake();

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->template = WorkflowTemplate::factory()->global()->create([
        'sample_input' => ['name' => 'Ada Lovelace'],
    ]);

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['template_id' => $this->template->id]);
    $this->workflow->replaceGraph([
        'nodes' => [
            ['key' => 'name', 'type' => 'workflow_input', 'config' => ['name' => 'name', 'label' => 'Full name', 'input_type' => 'string', 'required' => true], 'position' => ['x' => 0, 'y' => 0]],
            ['key' => 'approve', 'type' => 'human_approval', 'config' => ['title' => 'Check the message'], 'position' => ['x' => 340, 'y' => 0]],
            ['key' => 'notify', 'type' => 'slack_post_message', 'config' => ['channel' => '#sales', 'text' => 'New lead: {{ nodes.name.value }}'], 'position' => ['x' => 680, 'y' => 0]],
        ],
        'edges' => [
            ['from' => 'name', 'to' => 'approve', 'condition' => null],
            ['from' => 'approve', 'to' => 'notify', 'condition' => null],
        ],
    ]);
});

function sampleRunUrl(Workflow $workflow): string
{
    return "/api/v1/workspaces/{$workflow->workspace_id}/workflows/{$workflow->id}";
}

it('shows the run form with the template\'s example answers and the app steps it will preview', function () {
    Passport::actingAs($this->owner);

    $this->getJson(sampleRunUrl($this->workflow).'/sample-run')
        ->assertOk()
        ->assertJsonPath('data.fields.0.key', 'name')
        ->assertJsonPath('data.fields.0.label', 'Full name')
        ->assertJsonPath('data.fields.0.required', true)
        ->assertJsonPath('data.fields.0.sample', 'Ada Lovelace')
        ->assertJsonPath('data.app_steps.0.key', 'notify')
        ->assertJsonPath('data.app_steps.0.connector', 'slack')
        ->assertJsonPath('data.app_steps.0.connected', false);
});

it('runs the workflow without calling any app or waiting for approval', function () {
    Passport::actingAs($this->owner);

    $runId = $this->postJson(sampleRunUrl($this->workflow).'/sample-runs', ['input' => ['name' => 'Ada Lovelace']])
        ->assertAccepted()
        ->json('data.run.id');

    $run = Run::query()->with('nodeRuns')->findOrFail($runId);
    $notify = $run->nodeRuns->firstWhere('key', 'notify');
    $approve = $run->nodeRuns->firstWhere('key', 'approve');

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->trigger_type)->toBe(SampleRuns::TRIGGER_TYPE)
        ->and($approve->status)->toBe(NodeRunStatus::Completed)
        ->and($approve->output['approved'])->toBeTrue()
        ->and($approve->approval()->exists())->toBeFalse()
        ->and($notify->output['sample'])->toBeTrue()
        ->and($notify->output['would_send'])->toMatchArray(['channel' => '#sales', 'text' => 'New lead: Ada Lovelace']);

    Http::assertNothingSent();
});

it('publishes a workflow that has never been published', function () {
    Passport::actingAs($this->owner);

    expect($this->workflow->current_version_id)->toBeNull();

    $this->postJson(sampleRunUrl($this->workflow).'/sample-runs', ['input' => ['name' => 'Ada']])->assertAccepted();

    expect($this->workflow->fresh()->current_version_id)->not->toBeNull();
});

it('does not let a member who cannot publish run an unpublished workflow', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($member);

    $this->postJson(sampleRunUrl($this->workflow).'/sample-runs', ['input' => ['name' => 'Ada']])->assertForbidden();

    expect($this->workflow->fresh()->current_version_id)->toBeNull();
});

it('treats the child runs of a sample run as sample runs', function () {
    $parent = Run::factory()->forWorkflow($this->workflow)->create(['trigger_type' => SampleRuns::TRIGGER_TYPE]);
    $child = Run::factory()->forWorkflow($this->workflow)->create(['trigger_type' => 'loop', 'parent_run_id' => $parent->id]);
    $unrelated = Run::factory()->forWorkflow($this->workflow)->create(['trigger_type' => 'loop']);

    $sampleRuns = app(SampleRuns::class);

    expect($sampleRuns->isSample($child))->toBeTrue()
        ->and($sampleRuns->isSample($unrelated))->toBeFalse();
});
