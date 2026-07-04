<?php

use App\Enums\Role;
use App\Models\InAppNotification;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

test('a user sees their own in-app notifications', function () {
    InAppNotification::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    InAppNotification::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/notifications")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('unread count reflects unread notifications', function () {
    InAppNotification::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    InAppNotification::factory()->read()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/notifications/unread-count")
        ->assertOk()
        ->assertJsonPath('data.unread', 1);
});

test('a notification can be marked read', function () {
    $notification = InAppNotification::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->fresh()->isRead())->toBeTrue();
});

test('a slack channel can be created and tested', function () {
    Http::fake(['hooks.slack.test/*' => Http::response('ok')]);

    $created = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
            'type' => 'slack',
            'name' => 'Alerts',
            'config' => ['url' => 'https://hooks.slack.test/abc'],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$created}/test")
        ->assertOk();
});

test('a notification preference can be upserted', function () {
    $this->actingAs($this->user, 'api')
        ->putJson("/api/v1/workspaces/{$this->workspace->id}/notification-preferences", [
            'event_key' => 'execution.failed',
            'in_app' => true,
            'email' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.email', true);
});
