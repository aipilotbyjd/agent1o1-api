<?php

use App\Enums\Notifications\DeliveryStatus;
use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\Notifications\NotificationSeverity;
use App\Enums\Workspaces\Role;
use App\Events\Notifications\InboxNotificationChanged;
use App\Jobs\Notifications\DeliverIntegrationJob;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\Notifications\InboxNotification;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationDigestItem;
use App\Models\Notifications\NotificationPreference;
use App\Models\Notifications\NotificationRoute;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Notifications\Auth\SecurityAlertNotification;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Notifications\Channels\InboxChannel;
use App\Notifications\Workspace\MemberJoinedNotification;
use App\Notifications\Workspace\RunFailedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workspaces\WorkspaceService;
use App\Enums\Auth\AuthEvent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->dispatcher = app(NotificationDispatcher::class);
});

function failedRunIn(Workspace $workspace, ?Workflow $workflow = null, ?User $triggeredBy = null): Run
{
    $workflow ??= Workflow::factory()->forWorkspace($workspace)->create();

    return Run::factory()->create([
        'workspace_id' => $workspace->id,
        'runnable_type' => $workflow->getMorphClass(),
        'runnable_id' => $workflow->id,
        'workflow_id' => $workflow->id,
        'triggered_by' => $triggeredBy?->id,
        'error' => 'Timed out',
    ]);
}

it('stores an inbox row with the event, category, severity and workspace filled in', function () {
    Event::fake([InboxNotificationChanged::class]);

    $this->dispatcher->dispatch([$this->owner], new PaymentFailedNotification($this->workspace, 2, 'in_1'));

    $row = InboxNotification::query()->sole();

    expect($row->event_key)->toBe(NotificationEvent::PaymentFailed->value)
        ->and($row->category)->toBe(NotificationCategory::Billing->value)
        ->and($row->severity)->toBe(NotificationSeverity::Critical->value)
        ->and($row->workspace_id)->toBe($this->workspace->id)
        ->and($row->occurred_at)->not->toBeNull()
        ->and($row->data['title'])->toBe('Payment for Acme failed again (attempt 2)');

    Event::assertDispatched(InboxNotificationChanged::class, fn (InboxNotificationChanged $event): bool => $event->notification->is($row) && ! $event->grouped);
});

it('chooses channels from the event defaults when the member has no preference', function () {
    expect((new PaymentFailedNotification($this->workspace))->via($this->owner))->toBe([InboxChannel::class, 'mail'])
        ->and((new MemberJoinedNotification($this->workspace, $this->workspace->members()->sole()))->via($this->owner))->toBe([InboxChannel::class]);
});

it('applies a category preference to every event in it, and lets an event preference override it', function () {
    NotificationPreference::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'event_key' => NotificationCategory::Billing->preferenceKey(),
        'in_app' => false,
        'email' => false,
    ]);
    NotificationPreference::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'event_key' => NotificationEvent::PaymentFailed->value,
        'in_app' => true,
        'email' => false,
    ]);

    expect((new PaymentFailedNotification($this->workspace))->via($this->owner))->toBe([InboxChannel::class])
        ->and((new CreditsExhaustedNotification($this->workspace))->via($this->owner))->toBe([]);
});

it('holds email back for the digest when the member chose one', function () {
    NotificationPreference::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'event_key' => NotificationEvent::PaymentFailed->value,
        'in_app' => false,
        'email' => true,
        'email_digest' => true,
    ]);
    Mail::fake();

    $this->dispatcher->dispatch([$this->owner], new PaymentFailedNotification($this->workspace, 1, 'in_2'));

    expect(NotificationDigestItem::query()->sole())
        ->event_key->toBe(NotificationEvent::PaymentFailed->value)
        ->action_url->toEndWith("/{$this->workspace->id}/settings/billing");
    Mail::assertNothingOutgoing();
});

it('always sends a mandatory event, whatever the account preferences say', function () {
    AccountNotificationPreference::factory()->create([
        'user_id' => $this->owner->id,
        'key' => NotificationEvent::SecurityAlert->value,
        'in_app' => false,
        'email' => false,
    ]);

    expect((new SecurityAlertNotification(AuthEvent::PasswordChanged))->via($this->owner))->toBe([InboxChannel::class, 'mail']);
});

it('folds repeated failures of one workflow into the unread notification and emails once', function () {
    NotificationPreference::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'event_key' => NotificationEvent::RunFailed->value,
        'in_app' => true,
        'email' => true,
    ]);
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    foreach (range(1, 3) as $attempt) {
        $this->dispatcher->dispatch([$this->owner], new RunFailedNotification($this->workspace, failedRunIn($this->workspace, $workflow)));
    }

    $row = InboxNotification::query()->sole();

    expect($row->group_count)->toBe(3)
        ->and($row->group_key)->toBe("run.failed:{$this->workspace->id}:{$workflow->id}");
    expect(NotificationDelivery::query()->where('channel', 'mail')->count())->toBe(1);
});

it('starts a new notification once the grouped one has been read', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();

    $this->dispatcher->dispatch([$this->owner], new RunFailedNotification($this->workspace, failedRunIn($this->workspace, $workflow)));
    InboxNotification::query()->update(['read_at' => now()]);
    $this->dispatcher->dispatch([$this->owner], new RunFailedNotification($this->workspace, failedRunIn($this->workspace, $workflow)));

    expect(InboxNotification::query()->count())->toBe(2);
});

it('starts a new notification once the grouping window has passed', function () {
    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();

    $this->dispatcher->dispatch([$this->owner], new RunFailedNotification($this->workspace, failedRunIn($this->workspace, $workflow)));
    $this->travel(61)->minutes();
    $this->dispatcher->dispatch([$this->owner], new RunFailedNotification($this->workspace, failedRunIn($this->workspace, $workflow)));

    expect(InboxNotification::query()->count())->toBe(2);
});

it('reports a retried payment failure once per member', function () {
    $notification = fn () => new PaymentFailedNotification($this->workspace, 1, 'in_retried');

    $this->dispatcher->dispatch([$this->owner], $notification());
    $this->dispatcher->dispatch([$this->owner], $notification());

    expect(InboxNotification::query()->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('channel', 'mail')->count())->toBe(1);
});

it('tells whoever started a failed run, as well as the owners and admins', function () {
    Notification::fake();
    $editor = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $editor->id, 'role' => Role::Editor, 'joined_at' => now()]);
    $outsider = User::factory()->create();

    $this->dispatcher->dispatch(
        $this->dispatcher->ownersAdminsAnd($this->workspace, $editor, $outsider),
        new RunFailedNotification($this->workspace, failedRunIn($this->workspace, triggeredBy: $editor)),
    );

    Notification::assertSentTo([$this->owner, $editor], RunFailedNotification::class);
    Notification::assertNotSentTo($outsider, RunFailedNotification::class);
});

it('sends a routed event to a channel once, however many members were notified', function () {
    Bus::fake([DeliverIntegrationJob::class]);
    $admin = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $admin->id, 'role' => Role::Admin, 'joined_at' => now()]);
    $channel = NotificationChannel::factory()->create(['workspace_id' => $this->workspace->id, 'type' => 'slack']);
    NotificationRoute::factory()->forChannel($channel)->create(['event_key' => NotificationEvent::PaymentFailed]);
    NotificationRoute::factory()->forChannel($channel)->create(['category' => NotificationCategory::Billing]);

    $this->dispatcher->dispatch(
        $this->dispatcher->ownersAndAdmins($this->workspace),
        new PaymentFailedNotification($this->workspace, 1, 'in_3'),
    );

    $delivery = NotificationDelivery::query()->where('channel', 'slack')->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->payload['text'])->toStartWith('A payment for Acme failed');
    Bus::assertDispatchedTimes(DeliverIntegrationJob::class, 1);
});

it('leaves out channels whose route asks for louder events', function () {
    Bus::fake([DeliverIntegrationJob::class]);
    $channel = NotificationChannel::factory()->create(['workspace_id' => $this->workspace->id]);
    NotificationRoute::factory()->forChannel($channel)->create(['min_severity' => NotificationSeverity::Critical]);

    $this->dispatcher->dispatch([$this->owner], new MemberJoinedNotification($this->workspace, $this->workspace->members()->sole()));

    expect(NotificationDelivery::query()->count())->toBe(0);
    Bus::assertNotDispatched(DeliverIntegrationJob::class);
});

it('stores account-level notifications without a workspace and never routes them to a channel', function () {
    Bus::fake([DeliverIntegrationJob::class]);
    $channel = NotificationChannel::factory()->create(['workspace_id' => $this->workspace->id]);
    NotificationRoute::factory()->forChannel($channel)->create();

    $this->dispatcher->dispatchTo($this->owner, new SecurityAlertNotification(AuthEvent::PasswordChanged));

    expect(InboxNotification::query()->sole())
        ->workspace_id->toBeNull()
        ->category->toBe(NotificationCategory::Security->value);
    Bus::assertNotDispatched(DeliverIntegrationJob::class);
});

it('adds a button, the reason and one-click unsubscribe to a workspace email', function () {
    $mail = (new PaymentFailedNotification($this->workspace))->toMail($this->owner);

    expect($mail->actionText)->toBe('Update payment')
        ->and($mail->actionUrl)->toEndWith("/{$this->workspace->id}/settings/billing")
        ->and($mail->salutation)->toContain('Manage notification settings');
});
