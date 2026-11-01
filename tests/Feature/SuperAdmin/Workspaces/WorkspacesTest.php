<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Exceptions\WorkspaceSuspendedException;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Billing\CreditGate;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Support);
    $this->headers = superAdminHeaders($this->admin);
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('lists, searches and filters workspaces', function () {
    app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
    $this->workspace->forceFill(['suspended_at' => now()])->save();

    $this->withHeaders($this->headers)->getJson(saUrl('workspaces'))->assertOk()->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces?q=glob'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Globex');
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces?status=suspended'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Acme');
    $this->withHeaders($this->headers)->getJson(saUrl('workspaces?status=active'))->assertJsonCount(1, 'data');
});

it('shows a workspace with its owner and size, and records the view', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}"))
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.owner.email', $this->owner->email)
        ->assertJsonPath('data.members_count', 1)
        ->assertJsonPath('data.suspended_at', null);

    expect(SuperAdminAuditLog::query()->where('action', 'workspaces.viewed')->exists())->toBeTrue();
});

it('lists the members of a workspace', function () {
    $this->withHeaders($this->headers)->getJson(saUrl("workspaces/{$this->workspace->id}/members"))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role', 'owner')
        ->assertJsonPath('data.0.user.email', $this->owner->email);
});

it('suspends a workspace so its members and its runs are refused, then restores it', function () {
    Passport::actingAs($this->owner);
    $this->withHeaders($this->headers);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}")->assertOk();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("workspaces/{$this->workspace->id}/suspend"), ['reason' => 'Payment fraud investigation'])
        ->assertOk()
        ->assertJsonPath('data.suspended_reason', 'Payment fraud investigation');

    $this->flushHeaders();
    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'This workspace has been suspended.');

    expect(fn () => app(CreditGate::class)->assertCanStartRun($this->workspace->fresh()))->toThrow(WorkspaceSuspendedException::class);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("workspaces/{$this->workspace->id}/unsuspend"), ['reason' => 'Investigation closed'])
        ->assertOk();

    $this->flushHeaders();
    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}")->assertOk();
});

it('requires a reason and the right role to suspend a workspace', function () {
    $this->withHeaders($this->headers)->postJson(saUrl("workspaces/{$this->workspace->id}/suspend"))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Finance)))
        ->postJson(saUrl("workspaces/{$this->workspace->id}/suspend"), ['reason' => 'Verified abuse report'])
        ->assertForbidden();

    expect($this->workspace->fresh()->isSuspended())->toBeFalse();
});

it('removes a member only with a reason and a fresh code, and never the owner', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
    $row = WorkspaceMember::query()->where('user_id', $member->id)->firstOrFail();
    $ownerRow = WorkspaceMember::query()->where('user_id', $this->owner->id)->firstOrFail();

    $this->withHeaders($this->headers)->deleteJson(saUrl("workspaces/{$this->workspace->id}/members/{$row->id}"))
        ->assertUnprocessable()->assertJsonValidationErrors(['reason', 'totp_code']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("workspaces/{$this->workspace->id}/members/{$ownerRow->id}"), stepUp($this->admin))
        ->assertStatus(409);

    $this->withHeaders($this->headers)->deleteJson(saUrl("workspaces/{$this->workspace->id}/members/{$row->id}"), stepUp($this->admin))
        ->assertNoContent();

    expect(WorkspaceMember::query()->whereKey($row->id)->exists())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->where('action', 'workspaces.member_removed')->exists())->toBeTrue();
});

it('records a super admin by label in the workspace audit trail', function () {
    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => 'member', 'joined_at' => now()]);
    $row = WorkspaceMember::query()->where('user_id', $member->id)->firstOrFail();

    $this->withHeaders($this->headers)->deleteJson(saUrl("workspaces/{$this->workspace->id}/members/{$row->id}"), stepUp($this->admin))->assertNoContent();

    $entry = $this->workspace->auditLogs()->where('action', 'member.removed')->first()
        ?? $this->workspace->auditLogs()->latest('created_at')->first();

    expect($entry->actor_id)->toBeNull()
        ->and($entry->actor_label)->toBe("super_admin:{$this->admin->email}");
});
