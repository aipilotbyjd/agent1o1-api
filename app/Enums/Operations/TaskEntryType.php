<?php

namespace App\Enums\Operations;

enum TaskEntryType: string
{
    case Done = 'done';
    case Snoozed = 'snoozed';
}
