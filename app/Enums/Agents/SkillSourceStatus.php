<?php

namespace App\Enums\Agents;

enum SkillSourceStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Ready = 'ready';
    case Conflict = 'conflict';
    case Forking = 'forking';
    case CannotPublish = 'cannot_publish';
    case Failed = 'failed';
}
