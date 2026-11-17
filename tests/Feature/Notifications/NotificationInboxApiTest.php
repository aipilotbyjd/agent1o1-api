<?php

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationType;
use App\Enums\Workspaces\Role;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\Notifications\InboxNotification;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationPreference;
use App\Models\Notifications\NotificationRoute;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Http\SsrfGuard;
use App\Services\Notifications\NotificationLinks;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->user, ['name' => 'Acme']);
    Passport::actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function inboxRow(User $user, NotificationType $event, ?Workspace $workspace = null, array $attributes = []): InboxNotification
{
    $definition = $event->definition();

    return InboxNotification::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'workspace_id' => $workspace?->id,
        'event_key' => $event->value,
        'category' => $definition->category->value,
        'severity' => $definition->severity->value,
        'group_count' => 1,
        'occurred_at' => now(),
        'data' => [
            'workspace_id' => $workspace?->id,
            'type' => $event->value,
            'title' => $event->label(),
            'body' => null,
            'data' => ['run_id' => 'run-1'],
        ],
        ...$attributes,
    ]);
}

describe('inbox', function () {
    it('returns category, severity and an in-app link for each notification', function () {
        inboxRow($this->user, NotificationType::RunFailed, $this->workspace);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'runs')
            ->assertJsonPath('data.0.severity', 'critical')
            ->assertJsonPath('data.0.action.label', 'View run')
            ->assertJsonPath('data.0.action.url', "/{$this->workspace->id}/trail?run=run-1");
    });

    it('derives category and severity for rows stored before the inbox columns existed', function () {
        $row = inboxRow($this->user, NotificationType::PaymentFailed, $this->workspace, ['category' => null, 'severity' => null, 'event_key' => null]);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $row->id)
            ->assertJsonPath('data.0.category', 'billing')
            ->assertJsonPath('data.0.severity', 'critical');
    });

    it('filters by category, workspace and needing action', function () {
        $billing = inboxRow($this->user, NotificationType::PaymentFailed, $this->workspace);
        $joined = inboxRow($this->user, NotificationType::MemberJoined, $this->workspace);
        $security = inboxRow($this->user, NotificationType::SecurityAlert);

        $this->getJson('/api/v1/notifications?category=billing')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $billing->id);
        $this->getJson('/api/v1/notifications?workspace_id=none')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $security->id);
        $this->getJson('/api/v1/notifications?needs_action=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $billing->id);
        $this->getJson("/api/v1/notifications?workspace_id={$this->workspace->id}")->assertJsonCount(2, 'data');

        expect($joined->exists)->toBeTrue();
    });

    it('rejects an unknown category with 422', function () {
        $this->getJson('/api/v1/notifications?category=nope')->assertUnprocessable()->assertJsonValidationErrors('category');
    });

    it('lists the most recent occurrence first', function () {
        $older = inboxRow($this->user, NotificationType::MemberJoined, $this->workspace, ['occurred_at' => now()->subHour()]);
        $newer = inboxRow($this->user, NotificationType::MemberRemoved, $this->workspace);
        $older->forceFill(['occurred_at' => now()->addMinute()])->save();

        $this->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);
    });

    it('archives notifications out of the inbox and back', function () {
        $row = inboxRow($this->user, NotificationType::MemberJoined, $this->workspace);

        $this->postJson('/api/v1/notifications/archive', ['ids' => [$row->id]])->assertOk()->assertJsonPath('data.updated', 1);
        $this->getJson('/api/v1/notifications')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notifications?status=archived')->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/notifications/unarchive', ['ids' => [$row->id]])->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonCount(1, 'data');
    });

    it('does not archive or mark read another member\'s notifications', function () {
        $other = inboxRow(User::factory()->create(), NotificationType::MemberJoined);

        $this->postJson('/api/v1/notifications/archive', ['ids' => [$other->id]])->assertOk()->assertJsonPath('data.updated', 0);
        $this->postJson('/api/v1/notifications/read', ['ids' => [$other->id]])->assertOk()->assertJsonPath('data.updated', 0);

        expect($other->fresh())->archived_at->toBeNull()->read_at->toBeNull();
    });

    it('marks every unread notification in one category read', function () {
        $billing = inboxRow($this->user, NotificationType::PaymentFailed, $this->workspace);
        $joined = inboxRow($this->user, NotificationType::MemberJoined, $this->workspace);

        $this->postJson('/api/v1/notifications/read', ['all' => true, 'category' => 'billing'])->assertOk()->assertJsonPath('data.updated', 1);

        expect($billing->fresh()->read_at)->not->toBeNull()
            ->and($joined->fresh()->read_at)->toBeNull();
    });

    it('requires ids or all to mark notifications read', function () {
        $this->postJson('/api/v1/notifications/read', [])->assertUnprocessable()->assertJsonValidationErrors(['ids', 'all']);
    });

    it('summarises unread notifications by category and workspace, leaving out archived ones', function () {
        inboxRow($this->user, NotificationType::PaymentFailed, $this->workspace);
        inboxRow($this->user, NotificationType::MemberJoined, $this->workspace);
        inboxRow($this->user, NotificationType::SecurityAlert);
        inboxRow($this->user, NotificationType::MemberRemoved, $this->workspace, ['archived_at' => now()]);
        inboxRow($this->user, NotificationType::MemberRemoved, $this->workspace, ['read_at' => now()]);

        $this->getJson('/api/v1/notifications/summary')
            ->assertOk()
            ->assertJsonPath('data.unread', 3)
            ->assertJsonPath('data.needs_action', 1)
            ->assertJsonPath('data.by_category.billing', 1)
            ->assertJsonPath('data.by_category.workspace', 1)
            ->assertJsonPath('data.by_category.security', 1)
            ->assertJsonPath("data.by_workspace.{$this->workspace->id}", 2)
            ->assertJsonPath('data.by_workspace.none', 1);
    });
});

describe('catalog', function () {
    it('groups every event under its category', function () {
        $response = $this->getJson('/api/v1/notifications/catalog')->assertOk();

        $keys = collect($response->json('data.categories'))->flatMap(fn (array $category): array => array_column($category['events'], 'key'));

        expect($keys->sort()->values()->all())->toBe(collect(NotificationType::cases())->map->value->sort()->values()->all());
        $response->assertJsonPath('data.categories.0.key', NotificationCategory::Workspace->value);
    });
});

describe('workspace preferences', function () {
    it('saves a whole category at once and clears that category\'s event rows', function () {
        NotificationPreference::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'event_key' => NotificationType::PaymentFailed->value,
            'email' => true,
        ]);

        $this->putJson("/api/v1/workspaces/{$this->workspace->id}/notification-preferences/bulk", [
            'preferences' => [['key' => 'category:billing', 'email' => false, 'email_digest' => true]],
        ])->assertOk()->assertJsonCount(1, 'data.preferences');

        expect(NotificationPreference::query()->sole())
            ->event_key->toBe('category:billing')
            ->email->toBeFalse()
            ->email_digest->toBeTrue();
    });

    it('refuses to switch off a setting the member does not control', function (string $key) {
        $this->putJson("/api/v1/workspaces/{$this->workspace->id}/notification-preferences/bulk", [
            'preferences' => [['key' => $key, 'email' => false]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['preferences.0.key' => 'This notification setting can\'t be changed here.']);
    })->with([
        'a mandatory event' => NotificationType::SecurityAlert->value,
        'an event a super admin decides' => NotificationType::AiProviderAvailabilityChanged->value,
        'an account-level event' => NotificationType::AssistantDailyReport->value,
        'an unknown key' => 'nope',
    ]);

    it('still accepts the single-event upsert the settings page sends', function () {
        $this->putJson("/api/v1/workspaces/{$this->workspace->id}/notification-preferences", [
            'event_key' => NotificationType::TriggerDisabled->value,
            'email' => false,
        ])->assertOk()
            ->assertJsonPath('data.preference.in_app', true)
            ->assertJsonPath('data.preference.email', false);
    });

    it('returns 403 for another workspace\'s preferences', function () {
        $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);

        $this->putJson("/api/v1/workspaces/{$other->id}/notification-preferences/bulk", [
            'preferences' => [['key' => 'category:billing', 'email' => false]],
        ])->assertForbidden();
    });
});

describe('account preferences', function () {
    it('saves account-level choices', function () {
        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [['key' => NotificationType::AssistantDailyReport->value, 'email' => false, 'in_app' => true]],
        ])->assertOk();

        expect(AccountNotificationPreference::query()->sole())
            ->key->toBe(NotificationType::AssistantDailyReport->value)
            ->email->toBeFalse()
            ->in_app->toBeTrue();
    });

    it('treats product updates email as the marketing opt-out', function () {
        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [['key' => 'category:product_updates', 'email' => false]],
        ])->assertOk()->assertJsonFragment(['key' => 'category:product_updates', 'email' => false]);

        expect($this->user->fresh()->hasOptedOutOfMarketing())->toBeTrue();

        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [['key' => 'category:product_updates', 'email' => true]],
        ])->assertOk();

        expect($this->user->fresh()->hasOptedOutOfMarketing())->toBeFalse();
    });

    it('refuses workspace events on the account', function () {
        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [['key' => NotificationType::PaymentFailed->value, 'email' => false]],
        ])->assertUnprocessable()->assertJsonValidationErrors('preferences.0.key');
    });
});

describe('one-click unsubscribe', function () {
    it('switches one event\'s email off from the signed link', function () {
        $url = NotificationLinks::oneClickUnsubscribe($this->user, NotificationType::PaymentFailed, $this->workspace->id);

        app('auth')->forgetGuards();
        $this->postJson($url)->assertOk();

        expect(NotificationPreference::query()->sole())
            ->event_key->toBe(NotificationType::PaymentFailed->value)
            ->email->toBeFalse()
            ->in_app->toBeTrue();
    });

    it('returns 403 for a tampered link', function () {
        $url = NotificationLinks::oneClickUnsubscribe($this->user, NotificationType::PaymentFailed, $this->workspace->id);

        $this->postJson(str_replace('billing.payment_failed', 'billing.credits_low', $url))->assertForbidden();
    });
});

describe('integration routes', function () {
    beforeEach(function () {
        $this->channel = NotificationChannel::factory()->create(['workspace_id' => $this->workspace->id]);
    });

    it('creates, changes and deletes a route', function () {
        $id = $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes", [
            'notification_channel_id' => $this->channel->id,
            'category' => 'billing',
            'min_severity' => 'warning',
        ])->assertCreated()
            ->assertJsonPath('data.route.category', 'billing')
            ->assertJsonPath('data.route.channel.id', $this->channel->id)
            ->json('data.route.id');

        $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes/{$id}", ['min_severity' => 'critical'])
            ->assertOk()
            ->assertJsonPath('data.route.min_severity', 'critical');

        $this->deleteJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes/{$id}")->assertOk();

        expect(NotificationRoute::query()->count())->toBe(0);
    });

    it('rejects a route that names both an event and a category', function () {
        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes", [
            'notification_channel_id' => $this->channel->id,
            'event_key' => 'run.failed',
            'category' => 'runs',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['event_key' => 'A route covers one event or one category, not both.']);
    });

    it('rejects routing account-level events to a channel', function () {
        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes", [
            'notification_channel_id' => $this->channel->id,
            'category' => 'security',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['category' => 'Only workspace events can be sent to a channel.']);
    });

    it('rejects another workspace\'s channel', function () {
        $foreign = NotificationChannel::factory()->create();

        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes", [
            'notification_channel_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('notification_channel_id');
    });

    it('forbids a member without channel permissions from creating a route', function () {
        $member = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
        Passport::actingAs($member);

        $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-routes", [
            'notification_channel_id' => $this->channel->id,
        ])->assertForbidden();
    });

    it('lists what a channel was sent', function () {
        NotificationDelivery::factory()->sent()->create(['notification_channel_id' => $this->channel->id]);
        NotificationDelivery::factory()->failed()->create(['notification_channel_id' => $this->channel->id]);

        $this->getJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$this->channel->id}/deliveries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.payload');
    });

    it('shows a webhook signing secret only when it is created or rotated', function () {
        app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host) => $host === 'hooks.example.com' ? ['93.184.216.34'] : []));

        $created = $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
            'type' => 'webhook',
            'name' => 'Ops',
            'config' => ['url' => 'https://hooks.example.com/hook'],
        ]);

        $secret = $created->json('data.channel.signing_secret');
        expect($secret)->toStartWith('whsec_');

        $channelId = $created->json('data.channel.id');
        $this->getJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels")
            ->assertJsonMissingPath('data.channels.0.signing_secret');

        $rotated = $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channelId}/rotate-secret")
            ->assertOk()
            ->json('data.channel.signing_secret');

        expect($rotated)->toStartWith('whsec_')->not->toBe($secret);
    });
});
