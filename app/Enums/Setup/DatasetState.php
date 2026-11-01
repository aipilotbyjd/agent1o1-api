<?php

namespace App\Enums\Setup;

/**
 * Where a dataset stands against its code, as `setup:catalog:status` reports.
 */
enum DatasetState: string
{
    case InSync = 'in sync';
    case OutOfDate = 'out of date';
    case NeverSynced = 'never synced';
    case Incomplete = 'incomplete';
    case Failed = 'failed';

    public function needsSync(): bool
    {
        return $this !== self::InSync;
    }
}
