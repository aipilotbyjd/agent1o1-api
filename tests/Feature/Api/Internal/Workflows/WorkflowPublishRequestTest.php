<?php

use App\Enums\Workflows\PublishRequestStatus;
use App\Enums\Workspaces\AuditAction;
use App\Enums\Workspaces\Role;
use App\Exceptions\PublishApprovalRequiredException;
use App\Models\Auth\ApiKey;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPublishRequest;
use App\Models\Workflows\WorkflowVersion;
use App\Models\Workspaces\AuditLog;
use App\Notifications\Workflows\PublishRequestDecidedNotification;
use App\Notifications\Workflows\PublishReviewRequestedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create(['name' => 'Olive Owner']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['workflow_approvals' => true]]))
        ->active()
        ->create();

    $this->admin = User::factory()->create(['name' => 'Ada Admin']);
    $this->workspace->members()->create(['user_id' => $this->admin->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $this->editor = User::factory()->create(['name' => 'Eddie Editor']);
    $this->workspace->members()->create(['user_id' => $this->editor->id, 'role' => Role::Editor, 'joined_at' => now()]);

    $this->workflow = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Invoice sync']);
    $this->workflow->replaceGraph(publishRequestGraph('a'));

    $this->base = "/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/publish-requests";
    $this->requireApproval = fn () => $this->workspace->forceFill(['require_publish_approval' => true])->save();
});

/**
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
function publishRequestGraph(string ...$keys): array
{
    $nodes = [];

    foreach ($keys as $index => $key) {
        $nodes[] = ['key' => $key, 'type' => 'transform', 'config' => ['mapping' => []], 'position' => ['x' => $index * 300, 'y' => 0]];
    }

    $edges = [];

    for ($index = 1; $index < count($keys); $index++) {
        $edges[] = ['from' => $keys[$index - 1], 'to' => $keys[$index], 'condition' => null];
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

describe('policy', function () {
    it('lets an admin require review when the plan includes approvals', function () {
        Passport::actingAs($this->owner);

        $this->putJson("/api/v1/workspaces/{$this->workspace->id}/publish-approval-policy", ['require_publish_approval' => true])
            ->assertOk()
            ->assertJsonPath('data.policy.requires_approval', true)
            ->assertJsonPath('data.policy.can_approve', true)
            ->assertJsonPath('data.policy.approver_count', 2);

        expect($this->workspace->fresh()->require_publish_approval)->toBeTrue();
        expect(AuditLog::query()->where('action', AuditAction::PublishApprovalPolicyUpdated)->exists())->toBeTrue();
    });

    it('refuses to switch review on without a plan that includes it', function () {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Free']);
        Passport::actingAs($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/publish-approval-policy", ['require_publish_approval' => true])
            ->assertForbidden();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/publish-approval-policy")
            ->assertOk()
            ->assertJsonPath('data.policy.plan_includes_approvals', false)
            ->assertJsonPath('data.policy.requires_approval', false);
    });

    it('lets editors read the policy but not change it', function () {
        Passport::actingAs($this->editor);

        $this->getJson("/api/v1/workspaces/{$this->workspace->id}/publish-approval-policy")
            ->assertOk()
            ->assertJsonPath('data.policy.can_manage', false)
            ->assertJsonPath('data.policy.can_approve', false);

        $this->putJson("/api/v1/workspaces/{$this->workspace->id}/publish-approval-policy", ['require_publish_approval' => true])
            ->assertForbidden();
    });

    it('stops requiring review when the plan no longer includes it', function () {
        ($this->requireApproval)();
        PlanGrant::query()->where('workspace_id', $this->workspace->id)->delete();

        $version = $this->workflow->fresh()->publishVersion(null, $this->editor);

        expect($version->version)->toBe(1);
    });
});

describe('publishing', function () {
    it('refuses to publish directly once review is required, even for owners', function () {
        ($this->requireApproval)();
        Passport::actingAs($this->owner);

        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$this->workflow->id}/versions")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This workspace reviews changes before they go live. Request a review instead of publishing.');

        expect(WorkflowVersion::count())->toBe(0);
    });

    it('refuses to publish over the public api once review is required', function () {
        ($this->requireApproval)();
        $key = ApiKey::generatePlainTextKey();
        $this->workspace->apiKeys()->create(['name' => 'CI', 'hashed_key' => ApiKey::hash($key), 'abilities' => ['workflows:write']]);

        $this->withToken($key)->postJson("/api/public/v1/workflows/{$this->workflow->id}/versions")
            ->assertStatus(409);
    });

    it('still publishes hidden child workflows, which are never reviewed on their own', function () {
        ($this->requireApproval)();
        $child = Workflow::factory()->forWorkspace($this->workspace)->create(['is_internal' => true]);
        $child->replaceGraph(publishRequestGraph('only'));

        expect($child->publishVersion()->version)->toBe(1);
        expect(fn () => $this->workflow->publishVersion())->toThrow(PublishApprovalRequiredException::class);
    });
});

describe('requesting a review', function () {
    it('saves the draft for review and tells the other approvers', function () {
        ($this->requireApproval)();
        Passport::actingAs($this->editor);

        $response = $this->postJson($this->base, ['notes' => 'Adds the first step'])
            ->assertCreated()
            ->assertJsonPath('data.request.status', 'pending')
            ->assertJsonPath('data.request.notes', 'Adds the first step')
            ->assertJsonPath('data.request.requester.name', 'Eddie Editor')
            ->assertJsonPath('data.request.draft_changed_since', false)
            ->assertJsonPath('data.request.can_review', false)
            ->assertJsonPath('data.request.can_withdraw', true);

        expect($response->json('data.request.editor_graph.nodes.0.key'))->toBe('a');
        expect(WorkflowVersion::count())->toBe(0);

        Notification::assertSentTo([$this->owner, $this->admin], PublishReviewRequestedNotification::class);
        Notification::assertNotSentTo($this->editor, PublishReviewRequestedNotification::class);
    });

    it('does not tell the requester about their own request', function () {
        ($this->requireApproval)();
        Passport::actingAs($this->admin);

        $this->postJson($this->base)->assertCreated();

        Notification::assertSentTo($this->owner, PublishReviewRequestedNotification::class);
        Notification::assertNotSentTo($this->admin, PublishReviewRequestedNotification::class);
    });

    it('replaces the open request when someone asks again', function () {
        ($this->requireApproval)();
        Passport::actingAs($this->editor);

        $first = $this->postJson($this->base)->json('data.request.id');
        $this->workflow->replaceGraph(publishRequestGraph('a', 'b'));
        $second = $this->postJson($this->base)->json('data.request.id');

        expect(WorkflowPublishRequest::find($first)->status)->toBe(PublishRequestStatus::Superseded);

        $this->getJson($this->base)
            ->assertOk()
            ->assertJsonPath('data.pending.id', $second)
            ->assertJsonCount(2, 'data.requests');
    });

    it('refuses a draft that could not be published', function () {
        ($this->requireApproval)();
        $this->workflow->replaceGraph(['nodes' => [], 'edges' => []]);
        Passport::actingAs($this->editor);

        $this->postJson($this->base)->assertUnprocessable();

        expect(WorkflowPublishRequest::count())->toBe(0);
    });

    it('refuses when the draft is already live', function () {
        $this->workflow->publishVersion();
        ($this->requireApproval)();
        Passport::actingAs($this->editor);

        $this->postJson($this->base)->assertStatus(409);
    });

    it('refuses when the workspace publishes directly', function () {
        Passport::actingAs($this->editor);

        $this->postJson($this->base)->assertStatus(409);
    });

    it('does not let a member without publish rights ask', function () {
        ($this->requireApproval)();
        $member = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
        Passport::actingAs($member);

        $this->postJson($this->base)->assertForbidden();
    });
});

describe('reviewing', function () {
    beforeEach(function () {
        ($this->requireApproval)();
        Passport::actingAs($this->editor);
        $this->requestId = $this->postJson($this->base, ['notes' => 'First step'])->json('data.request.id');
    });

    it('publishes exactly what was reviewed, not later edits', function () {
        $this->workflow->replaceGraph(publishRequestGraph('a', 'later'));
        Passport::actingAs($this->admin);

        $this->getJson("{$this->base}/{$this->requestId}")
            ->assertJsonPath('data.request.draft_changed_since', true)
            ->assertJsonPath('data.request.can_review', true);

        $this->postJson("{$this->base}/{$this->requestId}/approve", ['notes' => 'Looks good'])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'approved')
            ->assertJsonPath('data.request.reviewer.name', 'Ada Admin')
            ->assertJsonPath('data.request.review_notes', 'Looks good')
            ->assertJsonPath('data.request.published_version.version', 1)
            ->assertJsonPath('data.workflow.has_unpublished_changes', true);

        $version = WorkflowVersion::sole();
        expect(array_column($version->graph['nodes'], 'key'))->toBe(['a']);
        expect($version->notes)->toBe('First step');
        expect($version->published_by)->toBe($this->editor->id);
        expect($this->workflow->fresh()->current_version_id)->toBe($version->id);
        expect(array_column($this->workflow->fresh()->draftGraph()['nodes'], 'key'))->toBe(['a', 'later']);

        Notification::assertSentTo($this->editor, PublishRequestDecidedNotification::class);
        expect(AuditLog::query()->where('action', AuditAction::PublishRequestApproved)->exists())->toBeTrue();
    });

    it('leaves nothing unpublished when the draft did not move', function () {
        Passport::actingAs($this->owner);

        $this->postJson("{$this->base}/{$this->requestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.workflow.has_unpublished_changes', false);
    });

    it('does not let anyone approve their own request', function () {
        Passport::actingAs($this->admin);
        $this->workflow->replaceGraph(publishRequestGraph('a', 'b'));
        $own = $this->postJson($this->base)->json('data.request.id');

        $this->postJson("{$this->base}/{$own}/approve")
            ->assertForbidden()
            ->assertJsonPath('message', "You can't review your own request. Ask another admin, or withdraw it.");

        expect(WorkflowVersion::count())->toBe(0);
    });

    it('does not let an editor approve', function () {
        $otherEditor = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $otherEditor->id, 'role' => Role::Editor, 'joined_at' => now()]);
        Passport::actingAs($otherEditor);

        $this->postJson("{$this->base}/{$this->requestId}/approve")->assertForbidden();
    });

    it('rejects with a reason and tells the requester', function () {
        Passport::actingAs($this->admin);

        $this->postJson("{$this->base}/{$this->requestId}/reject", ['notes' => 'Missing the error branch'])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'rejected')
            ->assertJsonPath('data.request.review_notes', 'Missing the error branch');

        expect(WorkflowVersion::count())->toBe(0);
        Notification::assertSentTo($this->editor, PublishRequestDecidedNotification::class);
    });

    it('cannot decide a request twice', function () {
        Passport::actingAs($this->admin);
        $this->postJson("{$this->base}/{$this->requestId}/reject")->assertOk();

        $this->postJson("{$this->base}/{$this->requestId}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This request was already rejected.');
    });

    it('lets only the requester withdraw', function () {
        Passport::actingAs($this->admin);
        $this->postJson("{$this->base}/{$this->requestId}/withdraw")->assertForbidden();

        Passport::actingAs($this->editor);
        $this->postJson("{$this->base}/{$this->requestId}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.request.status', 'withdrawn');

        $this->getJson($this->base)->assertJsonPath('data.pending', null);
    });

    it('keeps requests to their own workflow', function () {
        $other = Workflow::factory()->forWorkspace($this->workspace)->create();
        Passport::actingAs($this->admin);

        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$other->id}/publish-requests/{$this->requestId}/approve")
            ->assertNotFound();
    });

    it('cannot be reached from another workspace', function () {
        $stranger = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($stranger, ['name' => 'Elsewhere']);
        Passport::actingAs($stranger);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/workflows/{$this->workflow->id}/publish-requests/{$this->requestId}/approve")
            ->assertNotFound();
    });
});

it('only lets workspace members open the policy', function () {
    $stranger = User::factory()->create();
    Passport::actingAs($stranger);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/publish-approval-policy")->assertForbidden();
});

it('lists every change waiting for review in the workspace, oldest first', function () {
    ($this->requireApproval)();
    $second = Workflow::factory()->forWorkspace($this->workspace)->create(['name' => 'Lead router']);
    $second->replaceGraph(publishRequestGraph('x'));
    Passport::actingAs($this->editor);
    $this->postJson($this->base)->assertCreated();
    $this->travel(1)->minute();
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/workflows/{$second->id}/publish-requests")->assertCreated();

    Passport::actingAs($this->admin);
    $requests = $this->getJson("/api/v1/workspaces/{$this->workspace->id}/publish-requests")
        ->assertOk()
        ->json('data.requests');

    expect(array_column(array_column($requests, 'workflow'), 'name'))->toBe(['Invoice sync', 'Lead router']);
    expect($requests[0]['can_review'])->toBeTrue();
    expect($requests[0])->not->toHaveKeys(['editor_graph', 'draft_changed_since']);
});
