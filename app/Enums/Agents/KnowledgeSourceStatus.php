<?php

namespace App\Enums\Agents;

enum KnowledgeSourceStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Ready = 'ready';
    case Failed = 'failed';
}
