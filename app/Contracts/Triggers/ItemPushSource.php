<?php

namespace App\Contracts\Triggers;

use App\Models\Triggers\Trigger;

/**
 * A push source whose events carry the item that starts the trigger.
 *
 * Both paths must give an item the same id (itemId()), so the trigger's
 * delivery id stops one item running twice.
 */
interface ItemPushSource extends PushTriggerSource
{
    /**
     * Whether an item should start this trigger: its channel or resource and
     * its filters.
     *
     * @param  array<string, mixed>  $item
     */
    public function accepts(Trigger $trigger, array $item): bool;

    /**
     * The item a provider event carries, shaped like the item the poll path
     * produces for the same thing.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function itemFor(array $event): array;

    /**
     * The item's stable id: the same as the poll path gives it.
     *
     * @param  array<string, mixed>  $item
     */
    public function itemId(array $item): string;
}
