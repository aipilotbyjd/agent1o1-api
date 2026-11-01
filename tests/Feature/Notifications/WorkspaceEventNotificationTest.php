<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

it('notifies workspace owners and admins when a run needs approval', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $admin = User::factory()->create();
    $workspace->members()->create(['user_id' => $admin->id, 'role' => Role::Admin, 'joined_at' => now()]);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'review', 'type' => 'human_approval', 'config' => []]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    app(StartWorkflowRunAction::class)->execute($workflow->fresh());

    Notification::assertSentTo([$owner, $admin], RunApprovalRequestedNotification::class, function (RunApprovalRequestedNotification $notification): bool {
        return $notification->event === NotificationEvent::RunApprovalRequested;
    });
});
