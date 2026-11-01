<?php

namespace App\Enums\Billing;

enum CreditPackStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Refunded = 'refunded';
}
