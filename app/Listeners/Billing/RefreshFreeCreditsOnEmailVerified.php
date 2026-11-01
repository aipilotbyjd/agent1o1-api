<?php

namespace App\Listeners\Billing;

use App\Enums\Queue;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\Policies\FreeCreditPolicy;
use App\Services\Billing\UsagePeriods;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Free credits wait for a verified email. When the owner verifies, their
 * free workspace's current period is re-decided, so the credits arrive at
 * once instead of at the next period.
 */
class RefreshFreeCreditsOnEmailVerified implements ShouldQueue
{
    public string $queue = Queue::Billing->value;

    public function __construct(
        private readonly FreeCreditPolicy $free,
        private readonly UsagePeriods $periods,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $workspaceId = $this->free->freeWorkspaceIdFor($user);
        $workspace = $workspaceId !== null ? Workspace::query()->find($workspaceId) : null;

        if ($workspace !== null) {
            $this->periods->refreshAllowance($workspace);
        }
    }
}
