<?php

namespace App\Enums\Agents;

/**
 * What a synced knowledge source reads. App sources go through a connected
 * account, read-only.
 */
enum KnowledgeSourceType: string
{
    case Url = 'url';
    case GoogleDrive = 'google_drive';
    case Gmail = 'gmail';
    case Outlook = 'outlook';
    case GitHub = 'github';
    case Slack = 'slack';

    /**
     * The connector key for app sources, null for a web page.
     */
    public function connector(): ?string
    {
        return $this === self::Url ? null : $this->value;
    }
}
