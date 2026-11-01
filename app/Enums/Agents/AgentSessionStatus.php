<?php

namespace App\Enums\Agents;

enum AgentSessionStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
