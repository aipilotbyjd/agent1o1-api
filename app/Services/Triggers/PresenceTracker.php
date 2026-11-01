<?php

namespace App\Services\Triggers;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Finds what has gone missing between two looks at a list.
 *
 * "Row deleted" and "item deleted" have no feed to read: the only evidence is
 * that something that was in the list last time isn't now. The tracker keeps
 * the ids that were present (with a short label, so the event can say what it
 * was) and reports the ones that are gone.
 *
 * The first look reports nothing: it is the baseline. Gone items are handed
 * over at most `triggers.poll_max_items` at a time; the rest stay in the state
 * so the next poll reports them, since a poll never re-reads an item it was
 * handed.
 *
 * @phpstan-type Presence array<int|string, string>
 */
final class PresenceTracker
{
    /**
     * The longest label kept per item.
     */
    private const int LABEL_LENGTH = 100;

    /**
     * Compare what is there now with the state of the last look.
     *
     * @param  array<int|string, string>  $present  id => label, everything there right now
     * @param  array<string, mixed>|null  $state  what the last look returned; null on the first
     * @return array{items: list<array<string, mixed>>, state: array<string, mixed>}
     *
     * @throws RuntimeException when there are too many items to keep track of
     */
    public static function track(array $present, ?array $state, ?Carbon $now = null): array
    {
        $now ??= now();
        $limit = max(1, (int) config('triggers.removal.max_tracked'));

        if (count($present) > $limit) {
            throw new RuntimeException("There are more than {$limit} items to watch for removals. Narrow it to a smaller range, folder or filter.");
        }

        $present = array_map(fn (string $label): string => mb_substr($label, 0, self::LABEL_LENGTH), $present);

        if ($state === null) {
            return ['items' => [], 'state' => ['present' => $present, 'at' => $now->toIso8601String()]];
        }

        $gone = array_diff_key((array) ($state['present'] ?? []), $present);
        $now = $now->toIso8601String();
        $deliver = array_slice($gone, 0, max(1, (int) config('triggers.poll_max_items')), true);
        $carried = array_diff_key($gone, $deliver);

        $items = [];

        foreach ($deliver as $id => $label) {
            $items[] = ['id' => (string) $id, 'label' => (string) $label, 'last_seen_at' => $state['at'] ?? null, 'removed_at' => $now];
        }

        // Items still waiting to be reported keep the time they were last seen,
        // so the id they are reported under doesn't change between attempts.
        return [
            'items' => $items,
            'state' => ['present' => $present + $carried, 'at' => $carried === [] ? $now : ($state['at'] ?? $now)],
        ];
    }

    /**
     * The id a removal is delivered under: the item and the look that last
     * saw it. The same removal always gets the same id, so a poll that is
     * repeated after a failure can't report it twice; an item that comes back
     * and is removed again is a new removal.
     *
     * @param  array<string, mixed>  $item
     */
    public static function deliveryId(array $item): string
    {
        return 'removed:'.sha1((string) ($item['id'] ?? '').'@'.(string) ($item['last_seen_at'] ?? ''));
    }
}
