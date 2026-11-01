<?php

namespace App\Services\Assistant\Inbox;

use App\Authorization\WorkspaceContext;
use App\Enums\Billing\Feature;
use App\Enums\Workspaces\Permission;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantInboxConfig;

/**
 * Who may use Smart Inbox: a workspace on a plan with the feature, and a
 * member whose role still allows the assistant.
 */
class InboxAccess
{
    public function __construct(private readonly InboxLabels $labels) {}

    public function planAllows(Assistant $assistant): bool
    {
        return (bool) $assistant->workspace->currentPlan()?->hasFeature(Feature::SmartInbox);
    }

    public function allows(Assistant $assistant): bool
    {
        return $this->planAllows($assistant)
            && (WorkspaceContext::resolveRole($assistant->workspace, $assistant->user)?->has(Permission::AssistantUse) ?? false);
    }

    /**
     * Stops watching the mailbox and forgets what it did there; labels and
     * drafts already in the mailbox stay where they are.
     */
    public function switchOff(AssistantInboxConfig $config): void
    {
        $config->forceFill(['enabled' => false, 'last_checked_at' => null])->save();
        $config->messages()->delete();
        $this->labels->unlink($config);
    }
}
