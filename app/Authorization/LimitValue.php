<?php

namespace App\Authorization;

final readonly class LimitValue
{
    public function __construct(
        public bool $unlimited,
        public ?int $value,
    ) {}
}
