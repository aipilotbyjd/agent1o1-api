<?php

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Jobs\Broadcasts\SendBroadcastCampaignJob;
use App\Jobs\Broadcasts\SendBroadcastEmailJob;
use App\Mail\Broadcasts\BroadcastCampaignMail;
use App\Models\Billing\Plan;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function draftCampaign(array $attributes = []): BroadcastCampaign
{
    return BroadcastCampaign::factory()->create($attributes);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function startPayload(object $test, int $count, array $payload = []): array
{
    return stepUp($test->admin, ['expected_recipient_count' => $count, ...$payload]);
}

it('drafts a campaign and shows its normalised audience', function () {
    Plan::factory()->create(['slug' => 'pro']);

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), [
            'name' => 'October launch',
            'subject' => 'Meet folders',
            'body_markdown' => 'Hello {{ first_name }}',
            'audience' => ['plans' => ['pro']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.category', 'marketing')
        ->assertJsonPath('data.audience.roles', 'owners');
});

it('rejects a subject with a line break and an unknown audience key', function () {
    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), ['name' => 'x', 'subject' => "Hi\nBcc: a@b.test", 'body_markdown' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subject');

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/campaigns'), ['name' => 'x', 'subject' => 'x', 'body_markdown' => 'x', 'audience' => ['everyone' => true]])
        ->assertUnprocessable();
});

it('only edits and deletes a draft', function () {
    $draft = draftCampaign();
    $sent = draftCampaign(['status' => BroadcastStatus::Sent]);

    $this->withHeaders($this->headers)->patchJson(saUrl("broadcasts/campaigns/{$draft->id}"), ['subject' => 'New subject'])->assertOk();
    $this->withHeaders($this->headers)->patchJson(saUrl("broadcasts/campaigns/{$sent->id}"), ['subject' => 'Changed'])->assertStatus(409);
    $this->withHeaders($this->headers)->deleteJson(saUrl("broadcasts/campaigns/{$sent->id}"))->assertStatus(409);
    $this->withHeaders($this->headers)->deleteJson(saUrl("broadcasts/campaigns/{$draft->id}"))->assertOk();

    expect(BroadcastCampaign::query()->count())->toBe(1)->and($sent->fresh()->subject)->not->toBe('Changed');
});

it('sends a test copy to the admin alone and marks it', function () {
    referralUser();
    $campaign = draftCampaign(['subject' => 'Meet folders']);

    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))->assertOk();

    Mail::assertSent(BroadcastCampaignMail::class, 1);
    Mail::assertSent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => $mail->hasTo($this->admin->email) && $mail->isTest && $mail->envelope()->subject === '[TEST] Meet folders');

    expect(BroadcastRecipient::query()->count())->toBe(0)
        ->and(SuperAdminAuditLog::query()->where('action', 'broadcasts.test_sent')->exists())->toBeTrue();
});

it('limits test sends per admin', function () {
    config(['broadcasts.test_sends_per_hour' => 2]);
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/test-send"))->assertStatus(429);
});

it('previews the audience and then sends exactly that many, once each', function () {
    $people = collect(range(1, 3))->map(fn () => referralUser());
    referralUser(['suspended_at' => now()]);
    referralUser(['marketing_opted_out_at' => now()]);
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl('broadcasts/audience/preview'), ['audience' => []])
        ->assertJsonPath('data.recipient_count', 3);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 3))
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.recipient_count', 3)
        ->assertJsonPath('data.sent_count', 3)
        ->assertJsonPath('data.progress', 100);

    Mail::assertSent(BroadcastCampaignMail::class, 3);

    foreach ($people as $person) {
        Mail::assertSent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => $mail->hasTo($person->email));
    }

    expect(BroadcastRecipient::query()->where('status', 'sent')->count())->toBe(3);

    foreach (['broadcasts.started', 'broadcasts.completed'] as $action) {
        expect(SuperAdminAuditLog::query()->where('action', $action)->where('subject_id', $campaign->id)->exists())->toBeTrue();
    }
});

it('refuses to start when the audience is not the size the admin confirmed', function () {
    referralUser();
    referralUser();
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 5))
        ->assertStatus(409)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'now has 2'));

    Mail::assertNothingSent();
    expect($campaign->fresh()->status)->toBe(BroadcastStatus::Draft);
});

it('needs a fresh authenticator code and the confirmed count to start', function () {
    referralUser();
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), ['reason' => 'Launch day', 'expected_recipient_count' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), stepUp($this->admin))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expected_recipient_count');

    Mail::assertNothingSent();
});

it('refuses an empty audience, an oversized one, and one over the daily budget', function () {
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 1))
        ->assertUnprocessable();

    referralUser();
    referralUser();

    config(['broadcasts.max_recipients' => 1]);
    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 2))
        ->assertUnprocessable();

    config(['broadcasts.max_recipients' => 100, 'broadcasts.daily_limit' => 3]);
    draftCampaign(['status' => BroadcastStatus::Sent, 'started_at' => now(), 'recipient_count' => 2]);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 2))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'daily'));

    Mail::assertNothingSent();
});

it('cannot start a campaign twice', function () {
    referralUser();
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 1))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 1))->assertStatus(409);

    Mail::assertSent(BroadcastCampaignMail::class, 1);
});

it('answers a retried request with the same key from memory instead of running it again', function () {
    referralUser();
    $campaign = draftCampaign();
    $headers = [...$this->headers, 'Idempotency-Key' => 'start-1'];

    $first = $this->withHeaders($headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 1))->assertOk();
    $second = $this->withHeaders($headers)->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/start"), startPayload($this, 1))->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    Mail::assertSent(BroadcastCampaignMail::class, 1);
});

it('puts an unsubscribe link and one-click headers on marketing mail only', function () {
    $user = referralUser(['name' => 'Ada Lovelace']);
    $marketing = draftCampaign(['body_markdown' => 'Hi {{ first_name }}, **big** news.']);

    app(BroadcastCampaignService::class)->start($this->admin, $marketing, 1, 'Launch');

    Mail::assertSent(BroadcastCampaignMail::class, function (BroadcastCampaignMail $mail) use ($user) {
        $rendered = $mail->render();

        return $mail->hasTo($user->email)
            && str_contains($rendered, 'Hi Ada, <strong>big</strong> news.')
            && str_contains($rendered, 'Unsubscribe')
            && str_contains($mail->headers()->text['List-Unsubscribe'], '/api/v1/broadcasts/unsubscribe/'.$user->id)
            && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

it('sends service mail without an unsubscribe link, even to someone who opted out', function () {
    $optedOut = referralUser(['marketing_opted_out_at' => now()]);
    $campaign = draftCampaign(['category' => 'service', 'subject' => 'Incident report']);

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Incident');

    Mail::assertSent(BroadcastCampaignMail::class, function (BroadcastCampaignMail $mail) use ($optedOut) {
        return $mail->hasTo($optedOut->email)
            && $mail->unsubscribeUrl === null
            && $mail->headers()->text === []
            && ! str_contains($mail->render(), 'Unsubscribe');
    });
});

it('cannot inject markup or unsafe links through the body', function () {
    referralUser();
    $campaign = draftCampaign(['body_markdown' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))"]);

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Test');

    Mail::assertSent(BroadcastCampaignMail::class, function (BroadcastCampaignMail $mail) {
        $rendered = $mail->render();

        return ! str_contains($rendered, '<script>') && ! str_contains($rendered, 'javascript:');
    });
});

it('escapes a hostile name in the placeholders', function () {
    referralUser(['name' => '<img src=x onerror=alert(1)>']);
    $campaign = draftCampaign(['body_markdown' => 'Hi {{ name }}']);

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Test');

    Mail::assertSent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => ! str_contains($mail->render(), '<img'));
});

it('resumes a stopped campaign without mailing anyone twice', function () {
    $people = collect(range(1, 3))->map(fn () => referralUser());
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 3, 'Launch');

    // The fan-out wrote the rows and queued the mail, but nothing has gone out yet.
    expect(BroadcastRecipient::query()->where('status', 'pending')->count())->toBe(3);
    Bus::assertDispatchedTimes(SendBroadcastEmailJob::class, 3);

    // One message goes out; then the worker dies and the whole fan-out runs again.
    $first = BroadcastRecipient::query()->first();
    (new SendBroadcastEmailJob($first->id))->handle(app(BroadcastCampaignService::class), app(BroadcastAudience::class));

    (new SendBroadcastCampaignJob($campaign->id))->handle(app(BroadcastAudience::class), app(BroadcastCampaignService::class));

    expect(BroadcastRecipient::query()->count())->toBe(3);
    Bus::assertDispatchedTimes(SendBroadcastEmailJob::class, 5);

    // Every queued job runs, including the duplicate for the person already mailed.
    foreach (BroadcastRecipient::query()->get() as $recipient) {
        (new SendBroadcastEmailJob($recipient->id))->handle(app(BroadcastCampaignService::class), app(BroadcastAudience::class));
    }

    Mail::assertSent(BroadcastCampaignMail::class, 3);
    foreach ($people as $person) {
        expect(Mail::sent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => $mail->hasTo($person->email)))->toHaveCount(1);
    }

    expect($campaign->fresh()->status)->toBe(BroadcastStatus::Sent)->and($campaign->fresh()->sent_count)->toBe(3);
});

it('skips someone who opts out between the start and their message', function () {
    $stays = referralUser();
    $leaves = referralUser();
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Launch');

    $leaves->forceFill(['marketing_opted_out_at' => now()])->save();

    foreach (BroadcastRecipient::query()->get() as $recipient) {
        (new SendBroadcastEmailJob($recipient->id))->handle(app(BroadcastCampaignService::class), app(BroadcastAudience::class));
    }

    Mail::assertSent(BroadcastCampaignMail::class, 1);
    Mail::assertSent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => $mail->hasTo($stays->email));

    $skipped = BroadcastRecipient::query()->where('user_id', $leaves->id)->first();

    expect($skipped->status)->toBe(BroadcastRecipientStatus::Skipped)
        ->and($campaign->fresh()->skipped_count)->toBe(1)
        ->and($campaign->fresh()->status)->toBe(BroadcastStatus::Sent);
});

it('cancels: nothing more is sent and the waiting rows are skipped', function () {
    referralUser();
    referralUser();
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Launch');

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/cancel"), ['reason' => 'Wrong audience, fixing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    foreach (BroadcastRecipient::query()->get() as $recipient) {
        (new SendBroadcastEmailJob($recipient->id))->handle(app(BroadcastCampaignService::class), app(BroadcastAudience::class));
    }

    Mail::assertNothingSent();
    expect(BroadcastRecipient::query()->where('status', 'skipped')->count())->toBe(2)
        ->and(SuperAdminAuditLog::query()->where('action', 'broadcasts.canceled')->exists())->toBeTrue();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/cancel"), ['reason' => 'Again for good measure'])
        ->assertStatus(409);
});

it('retries only the failed messages', function () {
    $people = collect(range(1, 2))->map(fn () => referralUser());
    $campaign = draftCampaign();

    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 2, 'Launch');

    $failed = BroadcastRecipient::query()->where('user_id', $people[0]->id)->first();
    $failed->forceFill(['status' => BroadcastRecipientStatus::Failed, 'error' => 'Mailbox full'])->save();
    app(BroadcastCampaignService::class)->refreshProgress($campaign->refresh());

    Mail::fake();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/retry-failed"), ['reason' => 'Mailbox was fixed'])
        ->assertOk();

    Mail::assertSent(BroadcastCampaignMail::class, 1);
    Mail::assertSent(BroadcastCampaignMail::class, fn (BroadcastCampaignMail $mail) => $mail->hasTo($people[0]->email));

    expect($failed->fresh()->status)->toBe(BroadcastRecipientStatus::Sent)
        ->and($campaign->fresh()->status)->toBe(BroadcastStatus::Sent);

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/retry-failed"), ['reason' => 'Nothing left now'])
        ->assertStatus(409);
});

it('marks a failed message failed once its attempts run out, and closes the campaign', function () {
    referralUser();
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');

    $recipient = BroadcastRecipient::query()->firstOrFail();
    (new SendBroadcastEmailJob($recipient->id))->failed(new RuntimeException('SMTP 550 mailbox unavailable'));

    expect($recipient->fresh()->status)->toBe(BroadcastRecipientStatus::Failed)
        ->and($recipient->fresh()->error)->toContain('550')
        ->and($campaign->fresh()->status)->toBe(BroadcastStatus::Failed)
        ->and($campaign->fresh()->failed_count)->toBe(1);
});

it('lists recipients with their delivery status', function () {
    $campaign = draftCampaign();
    BroadcastRecipient::factory()->create(['campaign_id' => $campaign->id, 'email' => 'a@example.test', 'status' => 'sent']);
    BroadcastRecipient::factory()->create(['campaign_id' => $campaign->id, 'email' => 'b@example.test', 'status' => 'failed', 'error' => 'Mailbox full']);

    $this->withHeaders($this->headers)->getJson(saUrl("broadcasts/campaigns/{$campaign->id}/recipients"))->assertJsonCount(2, 'data');
    $this->withHeaders($this->headers)->getJson(saUrl("broadcasts/campaigns/{$campaign->id}/recipients?status=failed"))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.error', 'Mailbox full');
    $this->withHeaders($this->headers)->getJson(saUrl("broadcasts/campaigns/{$campaign->id}/recipients?q=a@"))->assertJsonCount(1, 'data');
});

it('schedules a campaign and starts it when due, not before', function () {
    referralUser();
    $campaign = draftCampaign();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/schedule"), startPayload($this, 1, ['scheduled_at' => now()->subMinute()->toIso8601String()]))
        ->assertUnprocessable();

    $this->withHeaders($this->headers)
        ->postJson(saUrl("broadcasts/campaigns/{$campaign->id}/schedule"), startPayload($this, 1, ['scheduled_at' => now()->addHour()->toIso8601String()]))
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travel(61)->minutes();
    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();

    Mail::assertSent(BroadcastCampaignMail::class, 1);
    expect($campaign->fresh()->status)->toBe(BroadcastStatus::Sent);

    $log = SuperAdminAuditLog::query()->where('action', 'broadcasts.started')->firstOrFail();
    expect($log->actor_label)->not->toBeNull()->and($log->super_admin_id)->toBeNull();
});

it('fails, with the reason, a scheduled campaign that breaks a limit by the time it is due', function () {
    $campaign = draftCampaign(['status' => BroadcastStatus::Scheduled, 'scheduled_at' => now()->subMinute()]);

    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();

    Mail::assertNothingSent();
    expect($campaign->fresh()->status)->toBe(BroadcastStatus::Failed)
        ->and($campaign->fresh()->failure_reason)->toContain('no one to send to');
});

it('resumes a campaign whose sending stalled', function () {
    referralUser();
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');
    Bus::assertDispatchedTimes(SendBroadcastEmailJob::class, 1);

    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();
    Bus::assertDispatchedTimes(SendBroadcastEmailJob::class, 1);

    $this->travel(11)->minutes();
    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();

    Bus::assertDispatchedTimes(SendBroadcastEmailJob::class, 2);
    expect(BroadcastRecipient::query()->count())->toBe(1);
});

it('gives an abandoned claim back to the queue', function () {
    referralUser();
    $campaign = draftCampaign();

    Bus::fake([SendBroadcastEmailJob::class]);
    app(BroadcastCampaignService::class)->start($this->admin, $campaign, 1, 'Launch');

    BroadcastRecipient::query()->update(['status' => BroadcastRecipientStatus::Sending, 'updated_at' => now()->subMinutes(30)]);

    (new SendBroadcastCampaignJob($campaign->id))->handle(app(BroadcastAudience::class), app(BroadcastCampaignService::class));

    expect(BroadcastRecipient::query()->first()->status)->toBe(BroadcastRecipientStatus::Pending);
});
