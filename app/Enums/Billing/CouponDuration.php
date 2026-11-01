<?php

namespace App\Enums\Billing;

enum CouponDuration: string
{
    case Once = 'once';
    case Repeating = 'repeating';
    case Forever = 'forever';
}
