<?php

use App\Enums\Broadcasts\BroadcastChannel;
use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Jobs\Broadcasts\DeliverBroadcastRecipientJob;
use App\Jobs\Broadcasts\SendBroadcastEmailJob;
use App\Mail\Broadcasts\BroadcastCampaignMail;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Notifications\Broadcasts\AdminMessageNotification;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;

beforeEach(function () {
    Mail::fake();

    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function inAppCampaign(array $attributes = []): BroadcastCampaign
{
    return BroadcastCampaign::factory()->inApp()->create($attributes);
}

function deliverRecipient(BroadcastRecipient $recipient, string $job = DeliverBroadcastRecipientJob::class): void
{
    (new $job($recipient->id))->handle(app(BroadcastCampaignService::class), app(BroadcastAudience::class));
}

it('drafts an in-app campaign without an email body, but needs its summary', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), ['name' => 'Folders', 'channel' => 'in_app', 'subject' => 'Meet folders'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('notification_summary')
        ->assertJsonMissingValidationErrors('body_markdown');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), [
            'name' => 'Folders',
            'channel' => 'in_app',
            'subject' => 'Meet folders',
            'notification_summary' => 'Group your workflows into folders.',
            'action_label' => 'Try it',
            'action_url' => '/w/:workspace/playbooks',
        ])
        ->assertCreated()
        ->assertJsonPath('data.channel', 'in_app')
        ->assertJsonPath('data.body_markdown', null)
        ->assertJsonPath('data.action_url', '/w/:workspace/playbooks');
});

it('needs both the email body and the summary when it goes both ways', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), ['name' => 'x', 'channel' => 'email_and_in_app', 'subject' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body_markdown', 'notification_summary']);
});

it('only accepts a button link inside the app or on https, and only with its label', function (array $button, bool $accepted) {
    $response = $this->withHeaders($this->headers)->postJson(saUrl('broadcasts/campaigns'), [
        'name' => 'x', 'channel' => 'in_app', 'subject' => 'x', 'notification_summary' => 'x', ...$button,
    ]);

    $accepted ? $response->assertCreated() : $response->assertUnprocessable();
})->with([
    'app path' => [['action_label' => 'Open', 'action_url' => '/settings/billing'], true],
    'https page' => [['action_label' => 'Read more', 'action_url' => 'https://example.com/blog/folders'], true],
    'javascript' => [['action_label' => 'Open', 'action_url' => 'javascript:alert(1)'], false],
    'protocol-relative' => [['action_label' => 'Open', 'action_url' => '//evil.example'], false],
    'backslash host' => [['action_label' => 'Open', 'action_url' => '/\\evil.example'], false],
    'plain http' => [['action_label' => 'Open', 'action_url' => 'http://example.com'], false],
    'data' => [['action_label' => 'Open', 'action_url' => 'data:text/html,hi'], false],
    'link without label' => [['action_url' => '/settings'], false],
    'label without link' => [['action_label' => 'Open'], false],
]);

it('puts the message in the bell of everyone it reaches, unverified included, without emailing', function () {
    $verified = referralUser();
    $unverified = referralUser(['email_verified_at' => null]);
    referralUser(['suspended_at' => now()]);
    referralUser(['marketing_opted_out_at' => now()]);
    $campaign = inAppCampaign(['subject' => 'Meet folders', 'notification_summary' => 'Group your workflows.', 'action_label' => 'Try it', 'action_url' => '/settings']);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/audience/preview'), ['audience' => [], 'channel' => 'in_app'])
        ->assertJsonPath('data.recipient_count', 2);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), stepUp($this->admin, ['expected_recipient_count' => 2]))
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.sent_count', 2);

    Mail::assertNothingSent();

    foreach ([$verified, $unverified] as $member) {
        $notification = $member->notifications()->sole();

        expect($notification->id)->toBe(AdminMessageNotification::idFor($campaign->id, $member->id))
            ->and($notification->data)->toMatchArray([
                'workspace_id' => null,
                'type' => 'admin.message',
                'title' => 'Meet folders',
                'body' => 'Group your workflows.',
            ])
            ->and($notification->data['data'])->toBe([
                'campaign_id' => $campaign->id,
                'category' => 'marketing',
                'action_label' => 'Try it',
                'action_url' => '/settings',
            ]);
    }

    expect(DatabaseNotification::query()->count())->toBe(2)
        ->and(BroadcastRecipient::query()->whereNotNull('notified_at')->count())->toBe(2);

    Passport::actingAs($unverified);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'admin.message')
        ->assertJsonPath('data.0.workspace_id', null)
        ->assertJsonPath('data.0.data.action_label', 'Try it');
});

it('reaches opted-out members with a service message in the bell', function () {
    $optedOut = referralUser(['marketing_opted_out_at' => now()]);
    $campaign = inAppCampaign(['category' => 'service']);

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Incident');

    expect($optedOut->notifications()->count())->toBe(1);
});

it('never puts two copies in a bell, however often a delivery runs', function () {
    $member = referralUser();
    $campaign = inAppCampaign();

    Bus::fake([DeliverBroadcastRecipientJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');

    $recipient = BroadcastRecipient::query()->sole();
    deliverRecipient($recipient);
    deliverRecipient($recipient);

    // A worker that wrote the notification but died before recording it.
    $recipient->forceFill(['status' => BroadcastRecipientStatus::Pending, 'notified_at' => null, 'notification_id' => null])->save();
    deliverRecipient($recipient);

    expect($member->notifications()->count())->toBe(1)
        ->and($recipient->fresh()->status)->toBe(BroadcastRecipientStatus::Sent)
        ->and($recipient->fresh()->notification_id)->toBe(AdminMessageNotification::idFor($campaign->id, $member->id));
});

it('sends only the email on a retry once the bell already has the message', function () {
    $member = referralUser();
    $campaign = BroadcastCampaign::factory()->emailAndInApp()->create();

    Bus::fake([DeliverBroadcastRecipientJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');

    $recipient = BroadcastRecipient::query()->sole();
    deliverRecipient($recipient);

    expect($member->notifications()->count())->toBe(1);
    Mail::assertSent(BroadcastCampaignMail::class, 1);

    // The email bounced after the bell had it; the retry must not add a second notification.
    $recipient->forceFill(['status' => BroadcastRecipientStatus::Failed, 'sent_at' => null])->save();
    app(BroadcastCampaignService::class)->retryFailed($this->admin, $campaign->refresh(), 'Mailbox fixed');
    deliverRecipient($recipient->fresh());

    expect($member->notifications()->count())->toBe(1)
        ->and($recipient->fresh()->status)->toBe(BroadcastRecipientStatus::Sent);
    Mail::assertSent(BroadcastCampaignMail::class, 2);
});

it('leaves unverified members out when the campaign also goes by email', function () {
    referralUser();
    referralUser(['email_verified_at' => null]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/audience/preview'), ['audience' => [], 'channel' => 'email_and_in_app'])
        ->assertJsonPath('data.recipient_count', 1);
});

it('sends in-app deliveries unthrottled and keeps them out of the daily email budget', function () {
    referralUser();
    referralUser();
    config(['broadcasts.daily_limit' => 1]);
    $campaign = inAppCampaign();

    Bus::fake([DeliverBroadcastRecipientJob::class]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), stepUp($this->admin, ['expected_recipient_count' => 2]))
        ->assertOk();

    Bus::assertDispatchedTimes(DeliverBroadcastRecipientJob::class, 2);
    Bus::assertDispatched(DeliverBroadcastRecipientJob::class, fn (DeliverBroadcastRecipientJob $job) => $job->throttled === false && $job->middleware() === []);

    // An email campaign the same day still has the whole budget: the bell used none of it.
    $email = BroadcastCampaign::factory()->create(['audience' => ['user_ids' => [User::query()->value('id')]]]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$email->id}/start"), stepUp($this->admin, ['expected_recipient_count' => 1]))
        ->assertOk();

    Bus::assertDispatched(DeliverBroadcastRecipientJob::class, fn (DeliverBroadcastRecipientJob $job) => $job->throttled === true);
});

it('reaches exactly the people named, owners or not', function () {
    $owner = referralUser();
    $member = User::factory()->create();
    referralUser();
    $campaign = inAppCampaign(['audience' => ['user_ids' => [$owner->id, $member->id]]]);

    $this->withHeaders($this->headers)
        ->getJson(saUrl("broadcasts/campaigns/{$campaign->id}"))
        ->assertJsonPath('data.audience.user_ids', [$owner->id, $member->id])
        ->assertJsonPath('data.audience.roles', 'all');

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Personal note');

    expect(DatabaseNotification::query()->pluck('notifiable_id')->sort()->values()->all())
        ->toBe(collect([$owner->id, $member->id])->sort()->values()->all());
});

it('refuses an unknown person in the audience', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), [
            'name' => 'x', 'channel' => 'in_app', 'subject' => 'x', 'notification_summary' => 'x',
            'audience' => ['user_ids' => [(string) str()->uuid()]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audience.user_ids.0');
});

it('refuses to send a draft that is missing what its channel shows', function () {
    referralUser();
    $campaign = BroadcastCampaign::factory()->create(['channel' => BroadcastChannel::InApp, 'notification_summary' => null]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), stepUp($this->admin, ['expected_recipient_count' => 1]))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'summary'));

    expect($campaign->fresh()->status)->toBe(BroadcastStatus::Draft);
});

it('recalls the notifications of that campaign only, and records it', function () {
    $member = referralUser();
    $campaign = inAppCampaign();
    $other = inAppCampaign();

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');
    app(BroadcastCampaignService::class)->start($this->admin, $other, 1, 'Launch');

    expect($member->notifications()->count())->toBe(2);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/recall"), ['reason' => 'Sent the wrong link'])
        ->assertOk()
        ->assertJsonPath('message', 'Removed 1 notification.')
        ->assertJsonPath('data.recalled_at', fn (?string $at) => $at !== null);

    expect($member->notifications()->sole()->id)->toBe(AdminMessageNotification::idFor($other->id, $member->id))
        ->and(SuperAdminAuditLog::query()->where('action', 'broadcasts.recalled')->where('subject_id', $campaign->id)->exists())->toBeTrue();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/recall"), ['reason' => 'Recalling it again'])
        ->assertStatus(409);
});

it('stops a sending campaign when it is recalled', function () {
    referralUser();
    referralUser();
    $campaign = inAppCampaign();

    Bus::fake([DeliverBroadcastRecipientJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Launch');
    deliverRecipient(BroadcastRecipient::query()->first());

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/recall"), ['reason' => 'Wrong audience'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    foreach (BroadcastRecipient::query()->get() as $recipient) {
        deliverRecipient($recipient);
    }

    expect(DatabaseNotification::query()->count())->toBe(0)
        ->and(BroadcastRecipient::query()->where('status', 'skipped')->count())->toBe(1);
});

it('cannot recall an email-only campaign or a draft', function () {
    $email = BroadcastCampaign::factory()->status(BroadcastStatus::Sent)->create();
    $draft = inAppCampaign();

    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$email->id}/recall"), ['reason' => 'Recall by mistake'])->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$draft->id}/recall"), ['reason' => 'Recall by mistake'])->assertStatus(409);
});

it('says how many bells it reached and how many were read', function () {
    $reader = referralUser();
    referralUser();
    $campaign = inAppCampaign();

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Launch');
    $reader->notifications()->sole()->markAsRead();

    $this->withHeaders($this->headers)
        ->getJson(saUrl("broadcasts/campaigns/{$campaign->id}"))
        ->assertJsonPath('data.notified_count', 2)
        ->assertJsonPath('data.read_count', 1);

    $this->withHeaders($this->headers)
        ->getJson(saUrl('broadcasts/campaigns'))
        ->assertJsonMissingPath('data.0.read_count');

    $this->withHeaders($this->headers)
        ->getJson(saUrl("broadcasts/campaigns/{$campaign->id}/recipients?q=".urlencode($reader->email)))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.notification_read_at', fn (?string $at) => $at !== null);

    $this->withHeaders($this->headers)
        ->getJson(saUrl('broadcasts/campaigns?channel=email'))
        ->assertJsonCount(0, 'data');
});

it('sends a test to the bell of the admin\'s own member account', function () {
    $member = referralUser(['email' => $this->admin->email]);
    $campaign = inAppCampaign(['subject' => 'Meet folders']);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))
        ->assertOk();

    expect($member->notifications()->sole()->data['title'])->toBe('[TEST] Meet folders')
        ->and(BroadcastRecipient::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('explains a test send with no member account to receive it', function () {
    $campaign = inAppCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'member account'));
});

it('still delivers a job queued under the old email job name', function () {
    referralUser();
    $campaign = BroadcastCampaign::factory()->create();

    Bus::fake([DeliverBroadcastRecipientJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');

    // A job queued before the rename comes back without ever running the new constructor.
    $legacy = (new ReflectionClass(SendBroadcastEmailJob::class))->newInstanceWithoutConstructor();

    expect($legacy->throttled)->toBeTrue()->and($legacy->middleware())->toHaveCount(1);

    deliverRecipient(BroadcastRecipient::query()->sole(), SendBroadcastEmailJob::class);

    Mail::assertSent(BroadcastCampaignMail::class, 1);
});
