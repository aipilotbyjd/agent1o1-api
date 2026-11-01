<?php

namespace App\Services\Setup;

use App\Enums\Setup\SyncStatus;

final readonly class SetupSyncResult
{
    public function __construct(
        public string $dataset,
        public SyncStatus $status,
        public int $rowsBefore = 0,
        public int $rowsAfter = 0,
        public ?string $message = null,
    ) {}
}
