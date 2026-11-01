<?php

namespace App\Actions\Billing;

use App\Models\Billing\Subscription;
use App\Models\Workspaces\Workspace;

class ResumeSubscriptionAction
{
    public function execute(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription('default');

        abort_if($subscription === null, 404, 'Workspace has no subscription to resume.');
        abort_unless($subscription->onGracePeriod(), 422, 'Subscription is not on its grace period.');

        $subscription->resume();

        return $subscription;
    }
}
