<?php

use App\Enums\Billing\CreditGrantSource;
use App\Enums\Onboarding\JobRole;
use App\Enums\RunStatus;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditGrant;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\NodeTester;
use App\Services\Workspaces\WorkspaceInvitationService;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

/**
 * @return array<string, bool>
 */
function gettingStartedMilestones(string $workspaceId): array
{
    return collect(test()->getJson("/api/v1/workspaces/{$workspaceId}/dashboard")->assertOk()->json('data.getting_started.milestones'))
        ->pluck('completed', 'key')
        ->all();
}

it('starts a new workspace with every milestone open', function () {
    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")->assertOk();

    expect($response->json('data.getting_started.dismissed'))->toBeFalse();
    expect($response->json('data.getting_started.completed'))->toBeFalse();
    expect(collect($response->json('data.getting_started.milestones'))->pluck('completed', 'key')->all())->toBe([
        'run_workflow' => false,
        'connect_app' => false,
        'activate_trigger' => false,
        'create_agent' => false,
        'invite_teammate' => false,
    ]);
});

it('ticks each milestone off from what the workspace has done', function () {
    $workspace = $this->workspace;
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Completed]);
    ConnectorCredential::factory()->forWorkspace($workspace)->create();
    Trigger::factory()->forWorkflow($workflow)->create();
    Agent::factory()->forWorkspace($workspace)->create();
    app(WorkspaceInvitationService::class)->invite($workspace, 'teammate@example.com', Role::Member, $this->owner);

    Passport::actingAs($this->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")->assertOk();

    expect($response->json('data.getting_started.completed'))->toBeTrue();
    expect(collect($response->json('data.getting_started.milestones'))->pluck('completed')->unique()->all())->toBe([true]);
});

it('does not count a failed run or a single-node test as a first run', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Failed]);
    Run::factory()->forWorkflow($workflow)->create([
        'status' => RunStatus::Completed,
        'trigger_type' => NodeTester::TRIGGER_TYPE,
    ]);

    Passport::actingAs($this->owner);

    expect(gettingStartedMilestones($this->workspace->id)['run_workflow'])->toBeFalse();
});

it('does not count an inactive trigger as automation', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    Trigger::factory()->forWorkflow($workflow)->inactive()->create();

    Passport::actingAs($this->owner);

    expect(gettingStartedMilestones($this->workspace->id)['activate_trigger'])->toBeFalse();
});

it('counts a second member as an invited teammate', function () {
    $this->workspace->members()->create(['user_id' => User::factory()->create()->id, 'role' => Role::Member, 'joined_at' => now()]);

    Passport::actingAs($this->owner);

    expect(gettingStartedMilestones($this->workspace->id)['invite_teammate'])->toBeTrue();
});

it('ignores what another workspace has done', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $workflow = Workflow::factory()->forWorkspace($other)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Completed]);
    ConnectorCredential::factory()->forWorkspace($other)->create();
    Trigger::factory()->forWorkflow($workflow)->create();
    Agent::factory()->forWorkspace($other)->create();

    Passport::actingAs($this->owner);

    expect(array_filter(gettingStartedMilestones($this->workspace->id)))->toBe([]);
});

it('recommends templates for the viewer\'s role first, capped at three', function () {
    $this->owner->update(['job_role' => JobRole::Sales]);
    WorkflowTemplate::factory()->global()->count(3)->create(['category' => 'Education', 'is_featured' => true]);
    $forSales = WorkflowTemplate::factory()->global()->create(['category' => 'Sales & Outreach']);

    Passport::actingAs($this->owner);

    $recommended = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk()
        ->json('data.getting_started.recommended_templates');

    expect($recommended)->toHaveCount(3);
    expect($recommended[0]['id'])->toBe($forSales->id);
    expect($recommended[0])->toHaveKeys(['id', 'name', 'description', 'category', 'icon', 'color']);
});

it('puts templates that can show a sample run first among the role\'s templates', function () {
    $this->owner->update(['job_role' => JobRole::Sales]);
    WorkflowTemplate::factory()->global()->create(['category' => 'Sales & Outreach', 'is_featured' => true]);
    $runnable = WorkflowTemplate::factory()->global()->create([
        'category' => 'Sales & Outreach',
        'sample_input' => ['email' => 'priya@example.com'],
        'graph' => ['nodes' => [['key' => 'notify', 'type' => 'slack_post_message', 'config' => []]], 'edges' => []],
    ]);

    Passport::actingAs($this->owner);

    $recommended = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk()
        ->json('data.getting_started.recommended_templates');

    expect($recommended[0]['id'])->toBe($runnable->id)
        ->and($recommended[0]['has_sample'])->toBeTrue()
        ->and($recommended[0]['apps'])->toBe(['slack'])
        ->and($recommended[1]['has_sample'])->toBeFalse();
});

it('never recommends another workspace\'s private template', function () {
    WorkflowTemplate::factory()->create();

    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk()
        ->assertJsonPath('data.getting_started.recommended_templates', []);
});

it('stops recommending templates once the workspace has a workflow', function () {
    WorkflowTemplate::factory()->global()->create();
    Workflow::factory()->forWorkspace($this->workspace)->create();

    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertOk()
        ->assertJsonPath('data.getting_started.recommended_templates', []);
});

it('hides the checklist for the member who dismissed it and nobody else', function () {
    $workspace = $this->workspace;
    $teammate = User::factory()->create();
    $workspace->members()->create(['user_id' => $teammate->id, 'role' => Role::Member, 'joined_at' => now()]);

    Passport::actingAs($this->owner);
    $this->postJson("/api/v1/workspaces/{$workspace->id}/dashboard/getting-started/dismiss")->assertNoContent();
    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")
        ->assertJsonPath('data.getting_started.dismissed', true);

    Passport::actingAs($teammate);
    $this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard")
        ->assertJsonPath('data.getting_started.dismissed', false);
});

it('refuses a dismissal from outside the workspace', function () {
    Passport::actingAs(User::factory()->create());

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/getting-started/dismiss")->assertForbidden();

    Passport::actingAs($this->owner);
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/dashboard")
        ->assertJsonPath('data.getting_started.dismissed', false);
});

it('pays a reached milestone\'s reward once', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Completed, 'triggered_by' => $this->owner->id]);
    Passport::actingAs($this->owner);
    $url = "/api/v1/workspaces/{$this->workspace->id}/dashboard/getting-started/run_workflow/claim";

    $this->postJson($url)
        ->assertOk()
        ->assertJsonPath('data.credits', 20)
        ->assertJsonPath('data.getting_started.milestones.0.reward_claimed', true)
        ->assertJsonPath('data.getting_started.milestones.0.reward_claimable', false);

    $this->postJson($url)->assertUnprocessable();

    expect(CreditGrant::query()->where('workspace_id', $this->workspace->id)->where('source', CreditGrantSource::Promo)->sum('amount'))->toEqual(20);
});

it('pays nothing for a milestone that has not been reached', function () {
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/getting-started/create_agent/claim")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Finish this step to claim its reward.');
});

it('pays the teammate reward once someone has joined, not when an invite is sent', function () {
    app(WorkspaceInvitationService::class)->invite($this->workspace, 'teammate@example.com', Role::Member, $this->owner);
    Passport::actingAs($this->owner);
    $url = "/api/v1/workspaces/{$this->workspace->id}/dashboard/getting-started/invite_teammate/claim";

    $this->postJson($url)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The reward arrives once a teammate has joined.');

    $this->workspace->members()->create(['user_id' => User::factory()->create()->id, 'role' => Role::Member, 'joined_at' => now()]);

    $this->postJson($url)->assertOk()->assertJsonPath('data.credits', 50);
});

it('offers no rewards in a second workspace of the same owner', function () {
    $second = app(WorkspaceService::class)->create($this->owner, ['name' => 'Side project']);
    $workflow = Workflow::factory()->forWorkspace($second)->create();
    Run::factory()->forWorkflow($workflow)->create(['status' => RunStatus::Completed]);
    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$second->id}/dashboard")
        ->assertJsonPath('data.getting_started.rewards_available', false)
        ->assertJsonPath('data.getting_started.milestones.0.reward_claimable', false);

    $this->postJson("/api/v1/workspaces/{$second->id}/dashboard/getting-started/run_workflow/claim")->assertUnprocessable();
});

it('rejects an unknown milestone', function () {
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/dashboard/getting-started/not_a_milestone/claim")->assertNotFound();
});
