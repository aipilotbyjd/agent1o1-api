<?php

namespace App\Jobs\Broadcasts;

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Enums\Queue;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Notifications\Broadcasts\AdminMessageNotification;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers one recipient of a campaign on each of its channels: the
 * notification bell first, then email. The row is claimed (`pending` →
 * `sending`) in a single UPDATE before anything leaves, so two workers, a
 * duplicate dispatch or a retry can never deliver the same person two copies.
 * The bell's part is recorded (`notified_at`) as soon as it is done, so a
 * retry after a failed email only sends the email. A transient failure puts
 * the row back to `pending` for the retry; running out of attempts marks it
 * `failed`, which `retry-failed` can later pick up.
 *
 * Throttled by the `broadcast-emails` limiter only when email is involved,
 * and re-checks the person at the last moment: a member who opted out, was
 * suspended or lost their address since the campaign started is skipped.
 */
class DeliverBroadcastRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Throttle releases don't count against this; real failures do. */
    public int $maxExceptions = 3;

    public int $tries = 0;

    /**
     * Whether the email rate limit applies. A plain property with a default,
     * not a promoted one, so a job queued before this existed still reads as
     * throttled when it is unserialized.
     */
    public bool $throttled = true;

    public function __construct(public readonly string $recipientId, bool $throttled = true)
    {
        $this->throttled = $throttled;
        $this->onQueue(Queue::Broadcast->value);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return $this->throttled ? [(new RateLimited('broadcast-emails'))->releaseAfter(5)] : [];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(BroadcastCampaignService $campaigns, BroadcastAudience $audience): void
    {
        $claimed = BroadcastRecipient::query()
            ->whereKey($this->recipientId)
            ->where('status', BroadcastRecipientStatus::Pending)
            ->update(['status' => BroadcastRecipientStatus::Sending, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $recipient = BroadcastRecipient::query()->with(['campaign', 'user'])->findOrFail($this->recipientId);
        $campaign = $recipient->campaign;

        if ($campaign->status !== BroadcastStatus::Sending || $campaign->isRecalled()) {
            $this->finish($recipient, BroadcastRecipientStatus::Skipped, 'Campaign is no longer sending');
        } elseif ($recipient->user === null || ! $audience->isReachable($recipient->user, $campaign->category, $campaign->channel)) {
            $this->finish($recipient, BroadcastRecipientStatus::Skipped, 'No longer eligible to receive this message');
        } else {
            try {
                $this->deliverInApp($campaign, $recipient);

                if ($campaign->channel->includesEmail()) {
                    Mail::to($recipient->email)->send($campaigns->mailFor($campaign, $recipient));
                    $recipient->forceFill(['sent_at' => now()]);
                }
            } catch (Throwable $exception) {
                // Back to the queue for the retry; `failed()` ends it for good.
                $recipient->forceFill(['status' => BroadcastRecipientStatus::Pending, 'error' => Str::limit($exception->getMessage(), 500, '')])->save();

                throw $exception;
            }

            $this->finish($recipient, BroadcastRecipientStatus::Sent);
        }

        $campaigns->refreshProgress($campaign->refresh());
    }

    public function failed(Throwable $exception): void
    {
        $recipient = BroadcastRecipient::query()->with('campaign')->find($this->recipientId);

        if ($recipient === null) {
            return;
        }

        $this->finish($recipient, BroadcastRecipientStatus::Failed, Str::limit($exception->getMessage(), 500, ''));

        app(BroadcastCampaignService::class)->refreshProgress($recipient->campaign->refresh());
    }

    /**
     * Puts the message in the member's bell, once. A row already written by
     * an earlier attempt (the worker died before recording it) counts as done.
     */
    private function deliverInApp(BroadcastCampaign $campaign, BroadcastRecipient $recipient): void
    {
        if (! $campaign->channel->includesInApp() || $recipient->notified_at !== null) {
            return;
        }

        $notification = new AdminMessageNotification($campaign);
        $notification->id = AdminMessageNotification::idFor($campaign->id, $recipient->user_id);

        if (! DatabaseNotification::query()->whereKey($notification->id)->exists()) {
            try {
                $recipient->user->notifyNow($notification);
            } catch (UniqueConstraintViolationException) {
                // A parallel attempt wrote it between the check and the insert.
            }
        }

        $recipient->forceFill(['notification_id' => $notification->id, 'notified_at' => now()])->save();
    }

    private function finish(BroadcastRecipient $recipient, BroadcastRecipientStatus $status, ?string $error = null): void
    {
        $recipient->forceFill(['status' => $status, 'error' => $error])->save();
    }
}
