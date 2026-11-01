<?php

namespace App\Enums\Agents;

/**
 * `ActionReviewerAgent`'s assessment of one call in Smart mode, only `Low`
 * runs without asking.
 */
enum ActionRisk: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
