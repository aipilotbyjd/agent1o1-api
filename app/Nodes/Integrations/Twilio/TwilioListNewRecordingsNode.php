<?php

namespace App\Nodes\Integrations\Twilio;

use App\Contracts\SupportsTriggerMode;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

/**
 * "Recording ready". Reads the account's recordings every two minutes; a
 * recording is listed once it is complete.
 */
class TwilioListNewRecordingsNode extends TwilioListRecordingsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'twilio_list_new_recordings';
    }

    public function name(): string
    {
        return 'Twilio: List New Recordings';
    }

    public function description(): string
    {
        return 'Lists call recordings, newest first. As a trigger it starts the flow for each recording that becomes available.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'recordings';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['sid'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Twilio: Recording ready';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a call recording is ready. Checks every two minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerPollMinutes(): int
    {
        return 2;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
