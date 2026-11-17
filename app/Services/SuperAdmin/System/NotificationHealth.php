<?php

namespace App\Services\SuperAdmin\System;

use App\Enums\Notifications\DeliveryStatus;
use App\Enums\Notifications\NotificationEvent;
use App\Models\Notifications\InboxNotification;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationDigestItem;
use Illuminate\Support\Collection;

/**
 * How member notifications are being delivered across the platform: what
 * went out by email and to chat/webhook channels in a recent window, what
 * failed, which customer channels keep failing, and how much is waiting in
 * digests. Read by the admin panel's Notification health page.
 */
class NotificationHealth
{
    private const int TOP_EVENTS = 15;

    private const int FAILING_CHANNELS = 25;

    /**
     * @return array<string, mixed>
     */
    public function report(int $windowHours = 24): array
    {
        $since = now()->subHours($windowHours);
        $deliveries = NotificationDelivery::query()->where('created_at', '>=', $since);

        return [
            'window_hours' => $windowHours,
            'totals' => $this->countsByStatus((clone $deliveries)->selectRaw('status, count(*) as aggregate')->groupBy('status')->get()),
            'by_channel' => (clone $deliveries)
                ->selectRaw('channel, status, count(*) as aggregate')
                ->groupBy('channel', 'status')
                ->get()
                ->groupBy('channel')
                ->map(fn (Collection $rows, string $channel): array => ['channel' => $channel, ...$this->countsByStatus($rows)])
                ->values()
                ->all(),
            'by_event' => (clone $deliveries)
                ->whereNotNull('event_key')
                ->selectRaw('event_key, status, count(*) as aggregate')
                ->groupBy('event_key', 'status')
                ->get()
                ->groupBy('event_key')
                ->map(fn (Collection $rows, string $eventKey): array => [
                    'event_key' => $eventKey,
                    'label' => NotificationEvent::tryFrom($eventKey)?->label() ?? $eventKey,
                    ...$this->countsByStatus($rows),
                ])
                ->sortByDesc(fn (array $row): int => $row['sent'] + $row['failed'])
                ->take(self::TOP_EVENTS)
                ->values()
                ->all(),
            'failing_channels' => NotificationChannel::query()
                ->where(fn ($query) => $query->where('consecutive_failures', '>', 0)->orWhereNotNull('disabled_at'))
                ->orderByDesc('consecutive_failures')
                ->limit(self::FAILING_CHANNELS)
                ->get()
                ->map(fn (NotificationChannel $channel): array => [
                    'id' => $channel->id,
                    'workspace_id' => $channel->workspace_id,
                    'type' => $channel->type,
                    'name' => $channel->name,
                    'is_active' => $channel->is_active,
                    'consecutive_failures' => $channel->consecutive_failures,
                    'last_error' => $channel->last_error,
                    'last_failed_at' => $channel->last_failed_at?->toIso8601String(),
                    'disabled_at' => $channel->disabled_at?->toIso8601String(),
                ])
                ->all(),
            'digest_backlog' => NotificationDigestItem::query()->count(),
            'inbox' => [
                'created' => InboxNotification::query()->where('created_at', '>=', $since)->count(),
                'unread' => InboxNotification::query()->whereNull('read_at')->whereNull('archived_at')->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, NotificationDelivery>  $rows
     * @return array{sent: int, failed: int, pending: int, skipped: int}
     */
    private function countsByStatus(Collection $rows): array
    {
        $counts = array_fill_keys(array_map(fn (DeliveryStatus $status): string => $status->value, DeliveryStatus::cases()), 0);

        foreach ($rows as $row) {
            $status = $row->status instanceof DeliveryStatus ? $row->status->value : (string) $row->status;
            $counts[$status] = ($counts[$status] ?? 0) + (int) $row->getAttribute('aggregate');
        }

        return $counts;
    }
}
