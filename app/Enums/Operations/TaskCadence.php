<?php

namespace App\Enums\Operations;

/**
 * How often a checklist task comes round. `Once` is a setup task: done one
 * time, then it stays done. The others reset at the start of each UTC day,
 * ISO week (Monday) or month.
 */
enum TaskCadence: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Setup',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Hours after the period starts that a task becomes overdue, unless the
     * task sets its own.
     */
    public function defaultDueAfterHours(): int
    {
        return match ($this) {
            self::Once => 72,
            self::Daily => 12,
            self::Weekly => 96,
            self::Monthly => 168,
        };
    }
}
