<?php

namespace App\Services\Broadcasts;

use App\Enums\Broadcasts\BroadcastCategory;
use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Jobs\Broadcasts\SendBroadcastCampaignJob;
use App\Mail\Broadcasts\BroadcastCampaignMail;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The life of an email campaign: write it (draft), check who it reaches
 * (preview), try it on yourself (test send), then start or schedule it. A send
 * is guarded four ways: the admin must echo the recipient count they previewed,
 * the audience is capped per campaign and per day, a start needs a fresh
 * authenticator code (see the form request), and each person is mailed at most
 * once because a recipient row is claimed before its message leaves.
 *
 * Starting only records the intent and queues `SendBroadcastCampaignJob`; the
 * mail itself goes out from the `system-broadcast` queue at a throttled rate.
 * Every change here is audited; a start by the scheduler is attributed to `system`.
 */
class BroadcastCampaignService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly BroadcastAudience $audience,
        private readonly MarketingPreferences $preferences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): BroadcastCampaign
    {
        return DB::transaction(function () use ($actor, $data): BroadcastCampaign {
            $campaign = new BroadcastCampaign($this->prepared($data));
            $campaign->created_by = $actor->id;
            $campaign->save();

            $this->audit->record($actor, 'broadcasts.created', $campaign, after: $this->snapshot($campaign));

            return $campaign;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, BroadcastCampaign $campaign, array $data): BroadcastCampaign
    {
        abort_unless($campaign->status->isEditable(), 409, 'Only a draft can be edited. Cancel it and make a new one.');

        return DB::transaction(function () use ($actor, $campaign, $data): BroadcastCampaign {
            $before = $this->snapshot($campaign);

            $campaign->fill($this->prepared($data))->save();

            $this->audit->recordChanges($actor, 'broadcasts.updated', $campaign, $before);

            return $campaign;
        });
    }

    public function delete(SuperAdmin $actor, BroadcastCampaign $campaign): void
    {
        abort_unless($campaign->status->canDelete(), 409, 'Only a draft or canceled campaign can be deleted; the rest are the record of what was sent.');

        DB::transaction(function () use ($actor, $campaign): void {
            $this->audit->record($actor, 'broadcasts.deleted', $campaign, $this->snapshot($campaign));

            $campaign->delete();
        });
    }

    /**
     * Who a send would reach right now.
     *
     * @param  array<string, mixed>|null  $audience
     * @return array{recipient_count: int, sample: list<array{id: string, name: string, email: string}>}
     */
    public function previewAudience(?array $audience, BroadcastCategory $category): array
    {
        return [
            'recipient_count' => $this->audience->count($audience, $category),
            'sample' => $this->audience->sample($audience, $category)
                ->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                ->all(),
        ];
    }

    /**
     * Mails the campaign to the admin alone. Limited per admin per hour.
     */
    public function testSend(SuperAdmin $actor, BroadcastCampaign $campaign): void
    {
        $limited = ! RateLimiter::attempt(
            'broadcast-test:'.$actor->id,
            (int) config('broadcasts.test_sends_per_hour'),
            fn () => true,
            3600,
        );

        abort_if($limited, 429, 'Too many test sends. Try again later.');

        Mail::to($actor->email)->send(new BroadcastCampaignMail(
            campaignSubject: $campaign->subject,
            bodyMarkdown: $campaign->body_markdown,
            recipientName: $actor->name,
            recipientEmail: $actor->email,
            unsubscribeUrl: $campaign->category->respectsOptOut() ? url('/broadcasts/unsubscribe/preview') : null,
            isTest: true,
        ));

        $this->audit->record($actor, 'broadcasts.test_sent', $campaign, after: ['to' => $actor->email]);
    }

    /**
     * Starts sending now. `$expectedRecipients` is what the admin previewed;
     * if the audience has moved since, nothing is sent and they are told the
     * new number.
     */
    public function start(SuperAdmin $actor, BroadcastCampaign $campaign, int $expectedRecipients, string $reason): BroadcastCampaign
    {
        $this->begin($campaign, $actor, 'broadcasts.started', $expectedRecipients, $reason);

        // A synchronous queue has already sent by now; show where it got to.
        return $campaign->refresh();
    }

    /**
     * Holds the campaign until `$at`; `broadcasts:dispatch-due` starts it then.
     * The audience is checked now so an oversized send is refused up front, and
     * again when it actually starts.
     */
    public function schedule(SuperAdmin $actor, BroadcastCampaign $campaign, CarbonInterface $at, int $expectedRecipients, string $reason): BroadcastCampaign
    {
        abort_unless($campaign->status->canStart(), 409, "A {$campaign->status->value} campaign can't be scheduled.");

        $count = $this->guardedRecipientCount($campaign, $expectedRecipients);

        DB::transaction(function () use ($actor, $campaign, $at, $count, $reason): void {
            $before = $this->snapshot($campaign);

            $campaign->forceFill(['status' => BroadcastStatus::Scheduled, 'scheduled_at' => $at, 'recipient_count' => $count])->save();

            $this->audit->record($actor, 'broadcasts.scheduled', $campaign, $before, $this->snapshot($campaign), $reason);
        });

        return $campaign;
    }

    /**
     * Starts every scheduled campaign whose time has come. A campaign that
     * breaks a limit by then is failed with the reason rather than sent.
     *
     * @return int how many were started
     */
    public function startDue(): int
    {
        $started = 0;

        BroadcastCampaign::query()
            ->where('status', BroadcastStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->each(function (BroadcastCampaign $campaign) use (&$started): void {
                try {
                    $this->begin($campaign, null, 'broadcasts.started', null, 'Scheduled send');
                    $started++;
                } catch (HttpException $e) {
                    $campaign->forceFill([
                        'status' => BroadcastStatus::Failed,
                        'failure_reason' => $e->getMessage(),
                        'finished_at' => now(),
                    ])->save();

                    $this->audit->record(null, 'broadcasts.failed', $campaign, after: ['reason' => $e->getMessage()]);
                }
            });

        return $started;
    }

    /**
     * Picks up campaigns whose sending went quiet (a worker died mid-run) and
     * resumes them. Safe by construction: recipient rows are unique and claimed.
     *
     * @return int how many were resumed
     */
    public function resumeStalled(): int
    {
        $resumed = 0;

        BroadcastCampaign::query()
            ->where('status', BroadcastStatus::Sending)
            ->where('updated_at', '<', now()->subMinutes((int) config('broadcasts.stalled_after_minutes')))
            ->each(function (BroadcastCampaign $campaign) use (&$resumed): void {
                $campaign->touch();
                SendBroadcastCampaignJob::dispatch($campaign->id);
                $resumed++;
            });

        return $resumed;
    }

    public function cancel(SuperAdmin $actor, BroadcastCampaign $campaign, string $reason): BroadcastCampaign
    {
        abort_unless($campaign->status->canCancel(), 409, "A {$campaign->status->value} campaign can't be canceled.");

        DB::transaction(function () use ($actor, $campaign, $reason): void {
            $before = $this->snapshot($campaign);

            $campaign->forceFill(['status' => BroadcastStatus::Canceled, 'finished_at' => now()])->save();

            $campaign->recipients()
                ->where('status', BroadcastRecipientStatus::Pending)
                ->update(['status' => BroadcastRecipientStatus::Skipped, 'error' => 'Campaign canceled', 'updated_at' => now()]);

            $this->audit->record($actor, 'broadcasts.canceled', $campaign, $before, $this->snapshot($campaign), $reason);
        });

        $this->refreshProgress($campaign);

        return $campaign;
    }

    /**
     * Gives every failed recipient another go, without touching anyone who
     * already received the message.
     */
    public function retryFailed(SuperAdmin $actor, BroadcastCampaign $campaign, string $reason): BroadcastCampaign
    {
        abort_unless(
            in_array($campaign->status, [BroadcastStatus::Sending, BroadcastStatus::Sent, BroadcastStatus::Failed], true),
            409,
            "A {$campaign->status->value} campaign has nothing to retry.",
        );

        $failed = $campaign->recipients()->where('status', BroadcastRecipientStatus::Failed)->count();

        abort_if($failed === 0, 409, 'No message failed, so there is nothing to retry.');

        DB::transaction(function () use ($actor, $campaign, $reason, $failed): void {
            $campaign->recipients()
                ->where('status', BroadcastRecipientStatus::Failed)
                ->update(['status' => BroadcastRecipientStatus::Pending, 'attempts' => 0, 'error' => null, 'updated_at' => now()]);

            $campaign->forceFill(['status' => BroadcastStatus::Sending, 'finished_at' => null, 'failure_reason' => null])->save();

            $this->audit->record($actor, 'broadcasts.retried', $campaign, after: ['failed_recipients' => $failed], reason: $reason);
        });

        SendBroadcastCampaignJob::dispatch($campaign->id)->afterCommit();

        return $campaign->refresh();
    }

    /**
     * Recomputes the counters from the recipient rows (the source of truth)
     * and closes the campaign once nothing is waiting. Called after every
     * message and after the fan-out, and safe to call at any time.
     */
    public function refreshProgress(BroadcastCampaign $campaign): BroadcastCampaign
    {
        $counts = $campaign->recipients()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (BroadcastRecipientStatus $status): int => (int) ($counts[$status->value] ?? 0);
        $open = $count(BroadcastRecipientStatus::Pending) + $count(BroadcastRecipientStatus::Sending);

        $campaign->forceFill([
            'recipient_count' => (int) $counts->sum(),
            'sent_count' => $count(BroadcastRecipientStatus::Sent),
            'failed_count' => $count(BroadcastRecipientStatus::Failed),
            'skipped_count' => $count(BroadcastRecipientStatus::Skipped),
        ])->save();

        if ($open === 0) {
            $allFailed = $campaign->sent_count === 0 && $campaign->failed_count > 0;

            // Only the worker that wins this update closes the campaign.
            $closed = BroadcastCampaign::query()
                ->whereKey($campaign->id)
                ->where('status', BroadcastStatus::Sending)
                ->update([
                    'status' => $allFailed ? BroadcastStatus::Failed : BroadcastStatus::Sent,
                    'failure_reason' => $allFailed ? 'Every message failed to send.' : null,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($closed) {
                $campaign->refresh();
                $this->audit->record(null, $allFailed ? 'broadcasts.failed' : 'broadcasts.completed', $campaign, after: [
                    'sent' => $campaign->sent_count,
                    'failed' => $campaign->failed_count,
                    'skipped' => $campaign->skipped_count,
                ]);
            }
        }

        return $campaign;
    }

    /**
     * The mail for one recipient row.
     */
    public function mailFor(BroadcastCampaign $campaign, BroadcastRecipient $recipient): BroadcastCampaignMail
    {
        $user = $recipient->user;
        $marketing = $campaign->category->respectsOptOut();

        return new BroadcastCampaignMail(
            campaignSubject: $campaign->subject,
            bodyMarkdown: $campaign->body_markdown,
            recipientName: $user?->name ?? '',
            recipientEmail: $recipient->email,
            unsubscribeUrl: $marketing && $user ? $this->preferences->pageUrl($user) : null,
            oneClickUrl: $marketing && $user ? $this->preferences->oneClickUrl($user) : null,
        );
    }

    /**
     * The one path into `Sending`. Locks the campaign so two starts (a double
     * click, the scheduler racing an admin) can't both run.
     */
    private function begin(BroadcastCampaign $campaign, ?SuperAdmin $actor, string $action, ?int $expectedRecipients, string $reason): void
    {
        DB::transaction(function () use ($campaign, $actor, $action, $expectedRecipients, $reason): void {
            $locked = BroadcastCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->status->canStart(), 409, "A {$locked->status->value} campaign can't be started.");

            $count = $this->guardedRecipientCount($locked, $expectedRecipients);
            $before = $this->snapshot($locked);

            $locked->forceFill([
                'status' => BroadcastStatus::Sending,
                'started_at' => now(),
                'finished_at' => null,
                'recipient_count' => $count,
            ])->save();

            $this->audit->record($actor, $action, $locked, $before, $this->snapshot($locked), $reason);

            $campaign->setRawAttributes($locked->getAttributes(), true);
        });

        SendBroadcastCampaignJob::dispatch($campaign->id);
    }

    /**
     * The audience size, after refusing a send that is empty, too big, over
     * the day's budget, or no longer the size the admin confirmed.
     */
    private function guardedRecipientCount(BroadcastCampaign $campaign, ?int $expected): int
    {
        $count = $this->audience->count($campaign->audience, $campaign->category);

        abort_if($count === 0, 422, 'This audience has no one to send to.');
        abort_if($count > (int) config('broadcasts.max_recipients'), 422, "This audience has {$count} people; a campaign may reach at most ".config('broadcasts.max_recipients').'.');

        $sentToday = (int) BroadcastCampaign::query()
            ->whereKeyNot($campaign->id)
            ->whereIn('status', [BroadcastStatus::Sending, BroadcastStatus::Sent])
            ->where('started_at', '>=', now()->startOfDay())
            ->sum('recipient_count');

        abort_if($sentToday + $count > (int) config('broadcasts.daily_limit'), 422, 'This would go over the daily sending limit of '.config('broadcasts.daily_limit').' emails.');

        abort_if($expected !== null && $expected !== $count, 409, "The audience changed: it now has {$count} people, not {$expected}. Preview it again to confirm.");

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepared(array $data): array
    {
        if (array_key_exists('audience', $data)) {
            $data['audience'] = $this->audience->normalize($data['audience']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BroadcastCampaign $campaign): array
    {
        return $campaign->only(['name', 'category', 'subject', 'body_markdown', 'audience', 'status', 'scheduled_at', 'recipient_count']);
    }
}
