<?php

namespace App\Enums\Account;

/**
 * Lifecycle of a personal data export: queued, built by
 * `BuildDataExportJob`, then either ready to download or failed. A completed
 * export becomes `Expired` once its file is pruned.
 */
enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Whether an export in this state still blocks a new request: only one
     * export per account is built at a time.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
