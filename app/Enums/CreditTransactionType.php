<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Usage = 'usage';
    case Refund = 'refund';
    case PackPurchase = 'pack_purchase';
    case Rollover = 'rollover';
    case Grant = 'grant';
    case Adjustment = 'adjustment';
}
