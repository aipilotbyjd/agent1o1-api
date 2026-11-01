<?php

namespace App\Nodes\Integrations\Twilio;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * "Call received". Twilio asks the number's own voice URL for what to do with a
 * call, which this app must not take over, so incoming calls are read from the
 * call log every minute instead. A call appears there as soon as it starts.
 */
class TwilioListNewCallsNode extends TwilioListCallsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'twilio_list_new_calls';
    }

    public function name(): string
    {
        return 'Twilio: List Received Calls';
    }

    public function description(): string
    {
        return 'Lists the calls the account received, newest first. As a trigger it starts the flow for each new call, within a minute.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::dynamic('Received on', 'twilio.numbers', 'Only calls to this number. Leave empty for every number.', '+14155550123'),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calls = array_map($this->shapeCall(...), $this->readCalls($run, [...$config, 'direction' => 'inbound', 'status' => 'any', 'from' => null]));

        return ['calls' => $calls, 'count' => count($calls)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'calls';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['sid'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Twilio: Call received';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a call comes in to one of the account\'s numbers. Checks every minute.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerPollMinutes(): int
    {
        return 1;
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
