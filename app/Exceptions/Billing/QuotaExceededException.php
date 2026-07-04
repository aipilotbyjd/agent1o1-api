<?php

namespace App\Exceptions\Billing;

use App\Enums\Limit;
use Exception;

class QuotaExceededException extends Exception
{
    public function __construct(
        public readonly Limit $limit,
        public readonly int $max,
        public readonly int $current,
    ) {
        parent::__construct(
            "Quota exceeded for '{$limit->value}': max {$max}, current {$current}.",
            422,
        );
    }
}
