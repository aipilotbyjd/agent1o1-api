<?php

namespace App\Enums\Setup;

enum SyncStatus: string
{
    case Applied = 'applied';
    case Unchanged = 'unchanged';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /** A dry run found work to do and did not do it. */
    case Pending = 'pending';
}
