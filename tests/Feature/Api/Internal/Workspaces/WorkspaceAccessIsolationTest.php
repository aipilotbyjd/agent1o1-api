<?php

use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Passport\Passport;

/*
 * Who may be inside a workspace at all, and how quickly that changes: the
 * membership half of tenant isolation. The row-level half lives in
 * CrossWorkspaceRouteSweepTest and TenantIsolationTest.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}";

    $this->addMember = function (Role $role): User {
        $user = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

        return $user;
    };
});

it('refuses a user who belongs to a different workspace only', function () {
    $agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $stranger = User::factory()->create();
    app(WorkspaceService::class)->create($stranger, ['name' => 'Globex']);

    Passport::actingAs($stranger);

    $this->getJson("{$this->url}/agents")->assertForbidden();
    $this->getJson("{$this->url}/agents/{$agent->id}")->assertForbidden();
    $this->postJson("{$this->url}/agents", ['name' => 'Sneaky'])->assertForbidden();
    $this->deleteJson("{$this->url}/agents/{$agent->id}")->assertForbidden();

    expect($agent->fresh())->not->toBeNull();
});

it('refuses a user switching into a workspace they are not a member of', function () {
    $stranger = User::factory()->create();

    expect(fn () => app(WorkspaceService::class)->switchTo($stranger, $this->workspace))
        ->toThrow(AuthorizationException::class);

    expect($stranger->fresh()->current_workspace_id)->not->toBe($this->workspace->id);
});

it('stops serving a member the moment they are removed', function () {
    $member = ($this->addMember)(Role::Member);
    Passport::actingAs($member);

    $this->getJson("{$this->url}/agents")->assertOk();

    app(WorkspaceService::class)->removeMember($this->workspace, $member);

    $this->getJson("{$this->url}/agents")->assertForbidden();
});

it('stops serving a member the moment they leave', function () {
    $member = ($this->addMember)(Role::Member);
    Passport::actingAs($member);

    $this->getJson("{$this->url}/agents")->assertOk();

    app(WorkspaceService::class)->leave($this->workspace, $member);

    $this->getJson("{$this->url}/agents")->assertForbidden();
});

it('applies a role downgrade on the very next request', function () {
    $editor = ($this->addMember)(Role::Editor);
    Passport::actingAs($editor);

    expect($this->postJson("{$this->url}/agents", ['name' => 'Before'])->getStatusCode())->not->toBe(403);

    app(WorkspaceService::class)->updateMemberRole($this->workspace, $editor, Role::Member);

    $this->postJson("{$this->url}/agents", ['name' => 'After'])->assertForbidden();
    $this->getJson("{$this->url}/agents")->assertOk();
});

it('locks everyone out of a suspended workspace, owner included', function () {
    $member = ($this->addMember)(Role::Member);
    $this->workspace->forceFill(['suspended_at' => now()])->save();

    foreach ([$this->owner, $member] as $user) {
        Passport::actingAs($user);

        $this->getJson("{$this->url}/agents")->assertForbidden();
    }
});

it('keeps a members access to their other workspace when removed from this one', function () {
    $member = ($this->addMember)(Role::Member);
    $elsewhere = app(WorkspaceService::class)->create($member, ['name' => 'Initech']);

    app(WorkspaceService::class)->removeMember($this->workspace, $member);
    Passport::actingAs($member);

    $this->getJson("{$this->url}/agents")->assertForbidden();
    $this->getJson("/api/v1/workspaces/{$elsewhere->id}/agents")->assertOk();
});
