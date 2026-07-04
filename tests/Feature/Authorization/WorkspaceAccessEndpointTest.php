<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

// ── Role identity returned ────────────────────────────────────────────────────

test('owner sees role=owner in access response', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->assertJsonPath('data.role', 'owner');
});

test('admin sees role=admin in access response', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');
});

test('viewer sees role=viewer in access response', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['id' => Str::uuid(), 'role' => Role::Viewer->value, 'joined_at' => now()]);

    $this->actingAs($viewer, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->assertJsonPath('data.role', 'viewer');
});

// ── Effective permission set ──────────────────────────────────────────────────

test('owner receives all permissions in access response', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $permissions = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->json('data.permissions');

    foreach (Permission::cases() as $p) {
        expect(in_array($p->value, $permissions))->toBeTrue("Owner should receive permission: {$p->value}");
    }
});

test('viewer receives only view-tier permissions', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['id' => Str::uuid(), 'role' => Role::Viewer->value, 'joined_at' => now()]);

    $permissions = $this->actingAs($viewer, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->json('data.permissions');

    // Must have all viewOnly permissions
    foreach (Permission::viewOnly() as $p) {
        expect($permissions)->toContain($p->value);
    }

    // Must NOT have member or admin grants
    foreach (Permission::memberGrants() as $p) {
        expect(in_array($p->value, $permissions))->toBeFalse("Viewer should not receive {$p->value}");
    }

    foreach (Permission::adminGrants() as $p) {
        expect(in_array($p->value, $permissions))->toBeFalse("Viewer should not receive {$p->value}");
    }
});

test('admin receives member grants but not owner-only permissions', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);

    $permissions = $this->actingAs($admin, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->json('data.permissions');

    expect($permissions)->toContain(Permission::MemberInvite->value);
    expect($permissions)->toContain(Permission::WorkflowCreate->value);
    expect($permissions)->toContain(Permission::WorkflowDelete->value);

    // Owner-only
    expect($permissions)->not->toContain(Permission::WorkspaceDelete->value);
    expect($permissions)->not->toContain(Permission::SubscriptionManage->value);
    expect($permissions)->not->toContain(Permission::WorkspaceTransferOwnership->value);
    expect($permissions)->not->toContain(Permission::WorkspaceManageBilling->value);
});

// ── Access control ────────────────────────────────────────────────────────────

test('non-member gets 403 on access endpoint', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertForbidden();
});

test('unauthenticated request gets 401', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertUnauthorized();
});

// ── Response structure ────────────────────────────────────────────────────────

test('access response includes plan and usage snapshot', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'role',
                'permissions',
                'plan',
                'usage' => [
                    'members' => ['current', 'max', 'unlimited'],
                    'credits',
                ],
            ],
        ]);
});

test('access response permissions match the Role composition exactly', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id, ['id' => Str::uuid(), 'role' => Role::Member->value, 'joined_at' => now()]);

    $permissions = $this->actingAs($member, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/me/access")
        ->assertOk()
        ->json('data.permissions');

    $expected = array_map(fn ($p) => $p->value, Role::Member->permissions());
    sort($permissions);
    sort($expected);

    expect($permissions)->toBe($expected);
});
