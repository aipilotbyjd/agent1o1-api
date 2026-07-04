<?php

namespace App\Contracts;

use App\Models\Trigger;

interface TriggerExecutor
{
    public function execute(Trigger $trigger): void;
}
