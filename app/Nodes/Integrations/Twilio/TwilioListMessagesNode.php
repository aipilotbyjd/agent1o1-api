<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TwilioListMessagesNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_list_messages';
    }

    public function name(): string
    {
        return 'Twilio: List Messages';
    }

    public function description(): string
    {
        return 'Lists messages sent or received, newest first, optionally of one number, direction or status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::text('To', 'Only messages to this number (use whatsapp:+1... for WhatsApp).', '+14155550123'),
                'from' => Field::text('From', 'Only messages from this number.', '+14155550199'),
                'direction' => Field::select('Direction', ['any' => 'Any', 'inbound' => 'Received', 'outbound' => 'Sent'], null, 'any'),
                'status' => Field::select('Status', ['any' => 'Any', 'received' => 'Received', 'delivered' => 'Delivered', 'sent' => 'Sent', 'queued' => 'Queued', 'failed' => 'Failed', 'undelivered' => 'Undelivered'], null, 'any'),
                'sent_after' => Field::advanced(Field::datetime('Sent after', 'Only messages sent at or after this time.')),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $messages = $this->readMessages($run, $config);

        return ['messages' => array_map($this->shapeMessage(...), $messages), 'count' => count($messages)];
    }

    /**
     * The messages the settings describe. Twilio filters by number and date; direction and
     * status are checked here, so a filter can't ask for more than one page of what it wants.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function readMessages(Run $run, array $config): array
    {
        $direction = (string) ($config['direction'] ?? 'any');
        $status = (string) ($config['status'] ?? 'any');
        $limit = $this->limit($config, 20);
        $params = [
            'To' => filled($config['to'] ?? null) ? $this->phone($config['to'], 'To', allowChannel: true) : null,
            'From' => filled($config['from'] ?? null) ? $this->phone($config['from'], 'From', allowChannel: true) : null,
            'DateSent>' => filled($config['sent_after'] ?? null) ? Carbon::parse((string) $config['sent_after'])->utc()->toIso8601String() : null,
        ];

        $filtered = $direction !== 'any' && $direction !== '' || $status !== 'any' && $status !== '';

        // A local filter may drop most of a page: read wider so `limit` still means what the person sees.
        $messages = $this->listAll($run, $config, '/Messages.json', 'messages', $params, $filtered ? min(1000, $limit * 5) : $limit);

        $messages = array_values(array_filter($messages, fn (array $message): bool => ($direction === 'any' || $direction === '' || str_starts_with((string) ($message['direction'] ?? ''), $direction))
            && ($status === 'any' || $status === '' || ($message['status'] ?? null) === $status)));

        return array_slice($messages, 0, $limit);
    }
}
