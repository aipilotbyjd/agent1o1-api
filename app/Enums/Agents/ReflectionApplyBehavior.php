<?php

namespace App\Enums\Agents;

enum ReflectionApplyBehavior: string
{
    case ReviewQueue = 'review_queue';
    case AutoApply = 'auto_apply';
}
