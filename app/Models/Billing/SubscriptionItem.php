<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
    use HasUuids;
}
