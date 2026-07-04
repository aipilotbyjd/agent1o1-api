<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

// ── Workspace CRUD ──────────────────────────────────────────────────────────

test('authenticated user can create a workspace and becomes owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/v1/workspaces', ['name' => 'My Workspace']);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'My Workspace');

    $workspace = Workspace::where('name', 'My Workspace')->first();
    expect($workspace)->not->toBeNull();
    expect($workspace->owner_id)->toBe($user->id);
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();

    $user->refresh();
    expect($user->current_workspace_id)->toBe($workspace->id);
});

test('workspace slug is generated from name', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/v1/workspaces', ['name' => 'Hello World']);

    expect(Workspace::where('slug', 'hello-world')->exists())->toBeTrue();
});

test('workspace slug appends -1 on duplicate name', function () {
    $owner = User::factory()->create();
    Workspace::factory()->create(['name' => 'Dupe', 'slug' => 'dupe', 'owner_id' => $owner->id]);

    $user = User::factory()->create();
    $this->actingAs($user, 'api')
        ->postJson('/api/v1/workspaces', ['name' => 'Dupe']);

    expect(Workspace::where('slug', 'dupe-1')->exists())->toBeTrue();
});

test('member cannot list workspaces they are not a member of', function () {
    $owner = User::factory()->create();
    Workspace::factory()->create(['owner_id' => $owner->id]);

    $other = User::factory()->create();
    $response = $this->actingAs($other, 'api')->getJson('/api/v1/workspaces');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('non-member gets 403 on workspace show', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $other = User::factory()->create();
    $this->actingAs($other, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}")
        ->assertForbidden();
});

test('viewer cannot update workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id, ['id' => Str::uuid(), 'role' => Role::Viewer->value, 'joined_at' => now()]);

    $this->actingAs($viewer, 'api')
        ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'New Name'])
        ->assertForbidden();
});

test('admin can update workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);

    $this->actingAs($admin, 'api')
        ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

test('only owner can delete workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);

    $this->actingAs($admin, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertForbidden();

    $this->actingAs($owner, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}")
        ->assertOk();

    expect(Workspace::find($workspace->id))->toBeNull();
});

// ── Members ─────────────────────────────────────────────────────────────────

test('member cannot remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $workspace->members()->attach($member1->id, ['id' => Str::uuid(), 'role' => Role::Member->value, 'joined_at' => now()]);
    $workspace->members()->attach($member2->id, ['id' => Str::uuid(), 'role' => Role::Member->value, 'joined_at' => now()]);

    $this->actingAs($member1, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member2->id}")
        ->assertForbidden();
});

test('owner cannot leave workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/leave")
        ->assertUnprocessable();
});

test('member can leave workspace and current_workspace_id is cleared', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $workspace->members()->attach($member->id, ['id' => Str::uuid(), 'role' => Role::Member->value, 'joined_at' => now()]);

    $this->actingAs($member, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/leave")
        ->assertOk();

    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
    expect($member->fresh()->current_workspace_id)->toBeNull();
});

// ── Transfer ownership ───────────────────────────────────────────────────────

test('ownership transfer swaps roles atomically', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    // Attach owner pivot row (normally done by WorkspaceService::create)
    $workspace->members()->attach($owner->id, ['id' => Str::uuid(), 'role' => Role::Owner->value, 'joined_at' => now()]);
    $admin = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);

    $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer-ownership", ['user_id' => $admin->id])
        ->assertOk();

    $workspace->refresh();
    expect($workspace->owner_id)->toBe($admin->id);

    $oldOwnerPivot = $workspace->members()->where('users.id', $owner->id)->first();
    expect($oldOwnerPivot->pivot->role)->toBe(Role::Admin);

    $newOwnerPivot = $workspace->members()->where('users.id', $admin->id)->first();
    expect($newOwnerPivot->pivot->role)->toBe(Role::Owner);
});

test('non-owner cannot transfer ownership', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $workspace->members()->attach($admin->id, ['id' => Str::uuid(), 'role' => Role::Admin->value, 'joined_at' => now()]);
    $workspace->members()->attach($other->id, ['id' => Str::uuid(), 'role' => Role::Member->value, 'joined_at' => now()]);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/transfer-ownership", ['user_id' => $other->id])
        ->assertForbidden();
});

// ── Invitations ──────────────────────────────────────────────────────────────

test('admin can invite a user and token is hashed in DB', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => 'invited@example.com',
            'role' => Role::Member->value,
        ]);

    $response->assertCreated();

    $invitation = Invitation::where('email', 'invited@example.com')->first();
    expect($invitation)->not->toBeNull();
    expect(strlen($invitation->token_hash))->toBe(64);

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('invite resource never leaks token or token_hash', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => 'invited@example.com',
            'role' => Role::Member->value,
        ]);

    $json = $response->json('data');
    expect($json)->not->toHaveKey('token');
    expect($json)->not->toHaveKey('token_hash');
});

test('duplicate pending invite returns 422', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => 'dup@example.com',
            'role' => Role::Member->value,
        ]);

    $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
            'email' => 'dup@example.com',
            'role' => Role::Member->value,
        ])
        ->assertUnprocessable();
});

test('user can accept invitation by raw token', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $rawToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'accept@example.com',
        'role' => Role::Member->value,
        'token_hash' => Invitation::hashToken($rawToken),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $user = User::factory()->create(['email' => 'accept@example.com']);

    $this->actingAs($user, 'api')
        ->postJson("/api/v1/invitations/{$rawToken}/accept")
        ->assertOk();

    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('decline sets declined_at and does not delete row', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $rawToken = Str::random(64);
    Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'decline@example.com',
        'role' => Role::Member->value,
        'token_hash' => Invitation::hashToken($rawToken),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $user = User::factory()->create(['email' => 'decline@example.com']);

    $this->actingAs($user, 'api')
        ->postJson("/api/v1/invitations/{$rawToken}/decline")
        ->assertOk();

    $inv = Invitation::where('email', 'decline@example.com')->first();
    expect($inv)->not->toBeNull();
    expect($inv->declined_at)->not->toBeNull();
});

test('revoke sets revoked_at and does not delete row', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $rawToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'revoke@example.com',
        'role' => Role::Member->value,
        'token_hash' => Invitation::hashToken($rawToken),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/invitations/{$invitation->id}")
        ->assertOk();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});
