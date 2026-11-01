<?php

use App\Enums\Notifications\NotificationEvent;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Notifications\Workspace\MemberInvitedNotification;
use App\Notifications\Workspace\MemberRoleChangedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

it('lists the notification event catalogue for an authenticated user', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/notifications/events')
        ->assertOk()
        ->assertJsonPath('data.0.key', NotificationEvent::MemberInvited->value)
        ->assertJsonCount(count(NotificationEvent::cases()), 'data');
});

it('lists, marks read, and deletes only the authenticated users notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'test',
        'data' => ['title' => 'Needs attention'],
    ]);
    $otherNotification = $otherUser->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'test',
        'data' => ['title' => 'Private'],
    ]);

    Passport::actingAs($user);

    $this->getJson('/api/v1/notifications?unread=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $notification->id);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($readAt): bool => $readAt !== null);

    $this->deleteJson("/api/v1/notifications/{$otherNotification->id}")->assertNotFound();
    $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertOk();
});

it('stores workspace-scoped notification preferences', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    Passport::actingAs($user);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/notification-preferences", [
        'event_key' => NotificationEvent::RunApprovalRequested->value,
        'in_app' => false,
        'email' => true,
    ])->assertOk()
        ->assertJsonPath('data.preference.event_key', NotificationEvent::RunApprovalRequested->value)
        ->assertJsonPath('data.preference.in_app', false)
        ->assertJsonPath('data.preference.email', true);
});

it('notifies workspace administrators when a member is invited', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $admin = User::factory()->create();
    $workspace->members()->create(['user_id' => $admin->id, 'role' => Role::Admin, 'joined_at' => now()]);
    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/invitations", [
        'email' => 'new.member@example.com',
        'role' => Role::Member->value,
    ])->assertCreated();

    Notification::assertSentTo($admin, MemberInvitedNotification::class);
    Notification::assertNotSentTo($owner, MemberInvitedNotification::class);
});

it('does not notify administrators who have been removed from a workspace', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $removedAdmin = User::factory()->create();
    $workspace->members()->create(['user_id' => $removedAdmin->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $workspace->members()->where('user_id', $removedAdmin->id)->delete();
    $invitation = $workspace->invitations()->create([
        'email' => 'new.member@example.com',
        'role' => Role::Member,
        'token' => str()->random(40),
        'invited_by' => $owner->id,
        'expires_at' => now()->addWeek(),
    ]);

    app(NotificationDispatcher::class)->dispatch(
        app(NotificationDispatcher::class)->ownersAndAdmins($workspace),
        new MemberInvitedNotification($workspace, $invitation, $owner),
    );

    Notification::assertNotSentTo($removedAdmin, MemberInvitedNotification::class);
    Notification::assertSentTo($owner, MemberInvitedNotification::class);
});

it('notifies other workspace administrators when a members role changes', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $admin->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
        'role' => Role::Editor->value,
    ])->assertNoContent();

    Notification::assertSentTo($admin, MemberRoleChangedNotification::class, function (MemberRoleChangedNotification $notification): bool {
        return $notification->data['previous_role'] === Role::Member->value
            && $notification->data['role'] === Role::Editor->value;
    });
});

it('changes only the preference fields a partial update names', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    Passport::actingAs($user);
    $url = "/api/v1/workspaces/{$workspace->id}/notification-preferences";
    $event = NotificationEvent::RunApprovalRequested->value;

    $this->putJson($url, ['event_key' => $event, 'in_app' => false, 'email' => true])->assertOk();
    $this->putJson($url, ['event_key' => $event, 'email' => false])
        ->assertOk()
        ->assertJsonPath('data.preference.in_app', false)
        ->assertJsonPath('data.preference.email', false);
});
