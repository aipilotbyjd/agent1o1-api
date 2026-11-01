<?php

use App\Actions\Agents\DuplicateAgentAction;
use App\Enums\Billing\PlanLimit;
use App\Enums\Workspaces\Role;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Agents\Agent;
use App\Models\Auth\ApiKey;
use App\Models\Billing\Plan;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\PlanLimitGate;
use App\Services\Workspaces\WorkspaceInvitationService;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * The resource-cap check. `Plan.limits` was seeded and rendered but never
 * read by any decision path, so every tier's workflow/agent/member cap was
 * advertised and unenforced: this is the gate that makes them real.
 *
 * Note what is *not* here: a plan with no `limits` entry, or none at all,
 * must stay unlimited. `PlanFactory` seeds `'limits' => []`, so every test in
 * the rest of the suite relies on that staying true.
 */
function cappedWorkspace(array $limits): Workspace
{
    Plan::factory()->create(['slug' => 'free', 'limits' => $limits]);
    config(['billing.default_plan' => 'free']);

    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

it('allows creating up to the cap', function () {
    $workspace = cappedWorkspace(['workflows' => 2]);
    Workflow::factory()->forWorkspace($workspace)->create();

    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows);
})->throwsNoExceptions();

it('refuses to create once the cap is reached', function () {
    $workspace = cappedWorkspace(['workflows' => 2]);
    Workflow::factory()->count(2)->forWorkspace($workspace)->create();

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows))
        ->toThrow(PlanLimitExceededException::class);
});

it('does not count internal (loop-mode) workflows toward the workflow limit', function () {
    $workspace = cappedWorkspace(['workflows' => 2]);
    Workflow::factory()->forWorkspace($workspace)->create();
    Workflow::factory()->count(5)->forWorkspace($workspace)->create(['is_internal' => true]);

    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows);
})->throwsNoExceptions();

it('treats a negative cap as unlimited', function () {
    $workspace = cappedWorkspace(['workflows' => -1]);
    Workflow::factory()->count(25)->forWorkspace($workspace)->create();

    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows);
})->throwsNoExceptions();

/**
 * The property the other 123 test files depend on: adding a `PlanLimit` case
 * must not retroactively cap every already-seeded plan at zero.
 */
it('treats a missing limit key as unlimited', function () {
    $workspace = cappedWorkspace(['agents' => 1]);
    Workflow::factory()->count(25)->forWorkspace($workspace)->create();

    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows);
})->throwsNoExceptions();

it('never gates a workspace with no resolvable plan', function () {
    Plan::query()->delete();
    config(['billing.default_plan' => 'free']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    Workflow::factory()->count(25)->forWorkspace($workspace)->create();

    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows);
})->throwsNoExceptions();

it('frees a slot when a workflow is soft-deleted', function () {
    $workspace = cappedWorkspace(['workflows' => 1]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows))
        ->toThrow(PlanLimitExceededException::class);

    $workflow->delete();

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows))
        ->not->toThrow(PlanLimitExceededException::class);
});

it('counts agents separately from workflows', function () {
    $workspace = cappedWorkspace(['workflows' => 1, 'agents' => 1]);
    Agent::factory()->forWorkspace($workspace)->create();

    // The agent cap is spent, the workflow cap is not.
    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Agents))
        ->toThrow(PlanLimitExceededException::class);

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows))
        ->not->toThrow(PlanLimitExceededException::class);
});

it('asserts a whole batch up front rather than one at a time', function () {
    $workspace = cappedWorkspace(['workflows' => 3]);
    Workflow::factory()->forWorkspace($workspace)->create();

    // One more fits; three more do not.
    app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows, 2);

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Workflows, 3))
        ->toThrow(PlanLimitExceededException::class);
});

/*
|--------------------------------------------------------------------------
| Seats
|--------------------------------------------------------------------------
*/

it('counts a pending invitation against the seat cap', function () {
    $workspace = cappedWorkspace(['members' => 2]);

    // The owner holds one seat; this invitation holds the second.
    app(WorkspaceInvitationService::class)->invite(
        $workspace, 'new@example.com', Role::Member, $workspace->owner,
    );

    expect(app(PlanLimitGate::class)->usage($workspace, PlanLimit::Members))->toBe(2);

    expect(fn () => app(PlanLimitGate::class)->assertCanCreate($workspace, PlanLimit::Members))
        ->toThrow(PlanLimitExceededException::class);
});

it('does not count expired or accepted invitations against the seat cap', function () {
    $workspace = cappedWorkspace(['members' => 5]);

    $workspace->invitations()->create([
        'email' => 'expired@example.com',
        'role' => Role::Member,
        'token' => 'expired-token',
        'invited_by' => $workspace->owner->id,
        'expires_at' => now()->subDay(),
    ]);

    $workspace->invitations()->create([
        'email' => 'accepted@example.com',
        'role' => Role::Member,
        'token' => 'accepted-token',
        'invited_by' => $workspace->owner->id,
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    // Only the owner's seat counts.
    expect(app(PlanLimitGate::class)->usage($workspace, PlanLimit::Members))->toBe(1);
});

/**
 * Accepting converts a seat the invitation already held, so it must not be
 * double-counted, otherwise the last invitation a workspace is allowed to
 * send could never be accepted.
 */
it('lets a pending invitation be accepted when it fills the last seat', function () {
    $workspace = cappedWorkspace(['members' => 2]);

    $invitation = app(WorkspaceInvitationService::class)->invite(
        $workspace, 'new@example.com', Role::Member, $workspace->owner,
    );

    $invitee = User::factory()->create(['email' => 'new@example.com']);
    $member = app(WorkspaceInvitationService::class)->accept($invitation, $invitee);

    expect($member->user_id)->toBe($invitee->id)
        ->and(app(PlanLimitGate::class)->usage($workspace->fresh(), PlanLimit::Members))->toBe(2);
});

/**
 * The case the send-side check can't catch: the invitation was legitimate
 * when sent, and the plan shrank underneath it.
 */
it('refuses to accept an invitation sent before a downgrade', function () {
    $workspace = cappedWorkspace(['members' => 2]);

    $invitation = app(WorkspaceInvitationService::class)->invite(
        $workspace, 'new@example.com', Role::Member, $workspace->owner,
    );

    Plan::where('slug', 'free')->first()->update(['limits' => ['members' => 1]]);

    $invitee = User::factory()->create(['email' => 'new@example.com']);

    expect(fn () => app(WorkspaceInvitationService::class)->accept($invitation, $invitee))
        ->toThrow(PlanLimitExceededException::class);
});

/*
|--------------------------------------------------------------------------
| Downgrade
|--------------------------------------------------------------------------
*/

it('leaves existing resources usable when a downgrade puts a workspace over its cap', function () {
    $workspace = cappedWorkspace(['workflows' => 5]);
    $workflows = Workflow::factory()->count(4)->forWorkspace($workspace)->create();

    Plan::where('slug', 'free')->first()->update(['limits' => ['workflows' => 2]]);

    Passport::actingAs($workspace->owner);

    // Over cap, but the existing rows still read and write.
    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflows->first()->id}")
        ->assertOk();

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflows->first()->id}", ['name' => 'Renamed'])
        ->assertOk();

    // Only creating more is refused.
    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows", ['name' => 'One more'])
        ->assertStatus(402);
});

/*
|--------------------------------------------------------------------------
| API surfaces
|--------------------------------------------------------------------------
*/

it('maps a reached cap to a 402 on the internal api', function () {
    $workspace = cappedWorkspace(['workflows' => 1]);
    Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows", ['name' => 'Nightly sync'])
        ->assertStatus(402);

    expect($workspace->workflows()->count())->toBe(1);
});

/**
 * The surface that bypasses the frontend entirely: a client-side counter is
 * not enforcement, so this is the one that matters most.
 */
it('maps a reached cap to a 402 on the public api', function () {
    $workspace = cappedWorkspace(['workflows' => 1]);
    Workflow::factory()->forWorkspace($workspace)->create();

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Integration key',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read', 'workflows:write'],
    ]);

    $this->withToken($plainTextKey)
        ->postJson('/api/public/v1/workflows', ['name' => 'Nightly sync'])
        ->assertStatus(402);

    expect($workspace->workflows()->count())->toBe(1);
});

it('refuses to duplicate a workflow at the cap', function () {
    $workspace = cappedWorkspace(['workflows' => 1]);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/duplicate")
        ->assertStatus(402);
});

it('refuses to duplicate an agent at the cap', function () {
    $workspace = cappedWorkspace(['agents' => 1]);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    expect(fn () => app(DuplicateAgentAction::class)->execute($agent, $workspace->owner))
        ->toThrow(PlanLimitExceededException::class);

    expect($workspace->agents()->count())->toBe(1);
});

it('refuses to invite past the seat cap over the api', function () {
    $workspace = cappedWorkspace(['members' => 1]);

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
        'email' => 'new@example.com',
        'role' => 'member',
    ])->assertStatus(402);

    expect($workspace->invitations()->count())->toBe(0);
});

/**
 * Instantiating a pack isn't transactional, so a cap hit mid-loop would leave
 * half the collection created. The whole batch is asserted up front instead.
 */
it('creates nothing when a template collection would exceed the cap', function () {
    $workspace = cappedWorkspace(['workflows' => 2]);

    $collection = TemplateCollection::factory()->forWorkspace($workspace)->create();

    foreach (range(1, 3) as $position) {
        $collection->items()->create([
            'templatable_type' => (new WorkflowTemplate)->getMorphClass(),
            'templatable_id' => WorkflowTemplate::factory()->forWorkspace($workspace)->create()->id,
            'position' => $position,
        ]);
    }

    Passport::actingAs($workspace->owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/template-collections/{$collection->id}/use")
        ->assertStatus(402);

    expect($workspace->workflows()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Billing overview
|--------------------------------------------------------------------------
*/

it('reports usage against every limit on the billing overview', function () {
    $workspace = cappedWorkspace(['workflows' => 3, 'agents' => -1]);
    Workflow::factory()->count(2)->forWorkspace($workspace)->create();

    Passport::actingAs($workspace->owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/billing")
        ->assertOk()
        ->assertJsonPath('data.limits.workflows.used', 2)
        ->assertJsonPath('data.limits.workflows.max', 3)
        ->assertJsonPath('data.limits.agents.used', 0)
        // -1 is reported as unlimited, not as a literal -1.
        ->assertJsonPath('data.limits.agents.max', null)
        ->assertJsonPath('data.limits.members.used', 1);
});
