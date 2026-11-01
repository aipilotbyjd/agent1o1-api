<?php

namespace App\Actions\Billing;

use App\Models\Billing\Subscription;
use App\Models\Workspaces\Workspace;

class CancelSubscriptionAction
{
    public function execute(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription('default');

        abort_if($subscription === null, 404, 'Workspace has no active subscription to cancel.');

        $subscription->cancel();

        return $subscription;
    }
}
