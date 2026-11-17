<?php

namespace App\Console\Commands\Notifications;

use App\Models\Notifications\NotificationDigestItem;
use App\Models\User;
use App\Notifications\Account\NotificationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sends each member their daily digest of held-back emails and clears what
 * it sent. Items are claimed (deleted) in the same transaction the digest
 * is queued in, so a crash can't send a digest twice or lose one.
 */
class SendNotificationDigestsCommand extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Sends members their daily digest of notification emails.';

    public function handle(): int
    {
        $maxItems = (int) config('notifications.digest.max_items');
        $sent = 0;

        NotificationDigestItem::query()
            ->select('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->chunk(200)
            ->each(function ($userIds) use ($maxItems, &$sent): void {
                $users = User::query()->whereKey($userIds)->get()->keyBy(fn (User $user): string => (string) $user->getKey());

                foreach ($userIds as $userId) {
                    DB::transaction(function () use ($users, $userId, $maxItems, &$sent): void {
                        $items = NotificationDigestItem::query()
                            ->where('user_id', $userId)
                            ->orderBy('created_at')
                            ->lockForUpdate()
                            ->get();

                        if ($items->isEmpty()) {
                            return;
                        }

                        NotificationDigestItem::query()->whereKey($items->modelKeys())->delete();

                        $user = $users->get((string) $userId);

                        if ($user === null) {
                            return;
                        }

                        $user->notify(new NotificationDigestNotification(
                            items: $items->take($maxItems)->map(fn (NotificationDigestItem $item): array => [
                                'title' => $item->title,
                                'body' => $item->body,
                                'action_url' => $item->action_url,
                            ])->values()->all(),
                            omitted: max(0, $items->count() - $maxItems),
                        ));

                        $sent++;
                    });
                }
            });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
