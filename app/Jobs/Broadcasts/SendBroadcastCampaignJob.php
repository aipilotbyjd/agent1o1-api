<?php

namespace App\Jobs\Broadcasts;

use App\Enums\Broadcasts\BroadcastRecipientStatus;
use App\Enums\Broadcasts\BroadcastStatus;
use App\Enums\Queue;
use App\Models\Broadcasts\BroadcastCampaign;
use App\Models\Broadcasts\BroadcastRecipient;
use App\Models\User;
use App\Services\Broadcasts\BroadcastAudience;
use App\Services\Broadcasts\BroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fans a started campaign out: writes one recipient row per person in the
 * audience, then queues one `SendBroadcastEmailJob` per row still waiting.
 *
 * Safe to run again at any point (a retry, `broadcasts:dispatch-due` resuming a
 * stalled campaign): rows are unique per `(campaign, user)` and inserted with
 * "ignore", so nobody gets a second row, and a message already sent is never
 * queued again because only `pending` rows are.
 */
class SendBroadcastCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /** A worker that claimed a message and died leaves it `sending`; after this long it is given back. */
    private const CLAIM_EXPIRES_MINUTES = 15;

    public function __construct(public readonly string $campaignId)
    {
        $this->onQueue(Queue::Broadcast->value);
    }

    public function handle(BroadcastAudience $audience, BroadcastCampaignService $campaigns): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status !== BroadcastStatus::Sending) {
            return;
        }

        $this->writeRecipients($campaign, $audience);
        $this->releaseAbandonedClaims($campaign);

        $campaign->recipients()
            ->where('status', BroadcastRecipientStatus::Pending)
            ->select('id')
            ->chunkById((int) config('broadcasts.chunk_size'), function (Collection $recipients): void {
                foreach ($recipients as $recipient) {
                    SendBroadcastEmailJob::dispatch($recipient->id);
                }
            });

        // Closes a campaign that had no one left to mail.
        $campaigns->refreshProgress($campaign->refresh());
    }

    public function failed(Throwable $exception): void
    {
        $campaign = BroadcastCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status !== BroadcastStatus::Sending) {
            return;
        }

        $campaign->forceFill([
            'status' => BroadcastStatus::Failed,
            'failure_reason' => Str::limit($exception->getMessage(), 500, ''),
            'finished_at' => now(),
        ])->save();
    }

    private function writeRecipients(BroadcastCampaign $campaign, BroadcastAudience $audience): void
    {
        $remaining = (int) config('broadcasts.max_recipients') - $campaign->recipients()->count();

        $audience->each($campaign->audience, $campaign->category, function (Collection $users) use ($campaign, &$remaining): void {
            if ($remaining <= 0) {
                return;
            }

            $now = now();

            $rows = $users->take($remaining)->map(fn (User $user): array => [
                'id' => (string) Str::uuid7(),
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => BroadcastRecipientStatus::Pending->value,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            BroadcastRecipient::query()->insertOrIgnore($rows);

            $remaining -= count($rows);
        });
    }

    private function releaseAbandonedClaims(BroadcastCampaign $campaign): void
    {
        $campaign->recipients()
            ->where('status', BroadcastRecipientStatus::Sending)
            ->where('updated_at', '<', now()->subMinutes(self::CLAIM_EXPIRES_MINUTES))
            ->update(['status' => BroadcastRecipientStatus::Pending, 'updated_at' => now()]);
    }
}
