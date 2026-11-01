<?php

namespace App\Jobs\Broadcasts;

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Enums\Queue;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mails one recipient of a campaign. The row is claimed (`pending` → `sending`)
 * in a single UPDATE before anything leaves, so two workers, a duplicate
 * dispatch or a retry can never send the same person two copies. A transient
 * failure puts the row back to `pending` for the retry; running out of
 * attempts marks it `failed`, which `retry-failed` can later pick up.
 *
 * Throttled by the `broadcast-emails` limiter, and re-checks the person at the
 * last moment: a member who opted out, was suspended or lost their address
 * since the campaign started is skipped, not mailed.
 */
class SendBroadcastEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Throttle releases don't count against this; real failures do. */
    public int $maxExceptions = 3;

    public int $tries = 0;

    public function __construct(public readonly string $recipientId)
    {
        $this->onQueue(Queue::Broadcast->value);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new RateLimited('broadcast-emails'))->releaseAfter(5)];
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

        if ($campaign->status !== BroadcastStatus::Sending) {
            $this->finish($recipient, BroadcastRecipientStatus::Skipped, 'Campaign is no longer sending');
        } elseif ($recipient->user === null || ! $audience->isMailable($recipient->user, $campaign->category)) {
            $this->finish($recipient, BroadcastRecipientStatus::Skipped, 'No longer eligible to receive this email');
        } else {
            try {
                Mail::to($recipient->email)->send($campaigns->mailFor($campaign, $recipient));
            } catch (Throwable $exception) {
                // Back to the queue for the retry; `failed()` ends it for good.
                $recipient->forceFill(['status' => BroadcastRecipientStatus::Pending, 'error' => Str::limit($exception->getMessage(), 500, '')])->save();

                throw $exception;
            }

            $recipient->forceFill(['sent_at' => now()]);
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

    private function finish(BroadcastRecipient $recipient, BroadcastRecipientStatus $status, ?string $error = null): void
    {
        $recipient->forceFill(['status' => $status, 'error' => $error])->save();
    }
}
