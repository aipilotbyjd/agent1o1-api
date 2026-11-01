<?php

namespace App\Nodes\Integrations\Twilio;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * "Message failed". Reads recently sent messages every two minutes and keeps the
 * ones Twilio could not deliver (failed, or undelivered to the phone). Twilio
 * only reports that after the send, so a message becomes an item when its status
 * turns to failed or undelivered.
 */
class TwilioListFailedMessagesNode extends TwilioListMessagesNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'twilio_list_failed_messages';
    }

    public function name(): string
    {
        return 'Twilio: List Failed Messages';
    }

    public function description(): string
    {
        return 'Lists the messages sent from the account that Twilio could not deliver, newest first. As a trigger it starts the flow for each message that fails.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::dynamic('Sent from', 'twilio.numbers', 'Only messages sent from this number. Leave empty for every number.', '+14155550123'),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $limit = $this->limit($config, 20);
        $failed = [];

        foreach (['failed', 'undelivered'] as $status) {
            array_push($failed, ...$this->readMessages($run, [...$config, 'to' => null, 'direction' => 'outbound', 'status' => $status, 'limit' => $limit]));
        }

        usort($failed, fn (array $a, array $b): int => (int) strtotime((string) ($b['date_created'] ?? '')) <=> (int) strtotime((string) ($a['date_created'] ?? '')));
        $messages = array_map($this->shapeMessage(...), array_slice($failed, 0, $limit));

        return ['messages' => $messages, 'count' => count($messages)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['sid'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Twilio: Message failed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a message sent from the account fails or is not delivered.';
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
