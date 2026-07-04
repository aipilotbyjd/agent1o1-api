<?php

namespace App\Enums;

enum CreditPackStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Refunded = 'refunded';
}
