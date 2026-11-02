<?php

namespace App\Enums\Intelligence;

enum RecommendationStatus: string
{
    case Open = 'open';
    case Applied = 'applied';
    case Dismissed = 'dismissed';
    case Resolved = 'resolved';
}
