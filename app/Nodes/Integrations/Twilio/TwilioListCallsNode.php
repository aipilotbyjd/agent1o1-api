<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TwilioListCallsNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_list_calls';
    }

    public function name(): string
    {
        return 'Twilio: List Calls';
    }

    public function description(): string
    {
        return 'Lists calls made or received, newest first, optionally of one number, direction or status.';
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
                'to' => Field::text('To', 'Only calls to this number.', '+14155550123'),
                'from' => Field::text('From', 'Only calls from this number.', '+14155550199'),
                'direction' => Field::select('Direction', ['any' => 'Any', 'inbound' => 'Received', 'outbound' => 'Made'], null, 'any'),
                'status' => Field::select('Status', ['any' => 'Any', 'queued' => 'Queued', 'ringing' => 'Ringing', 'in-progress' => 'In progress', 'completed' => 'Completed', 'busy' => 'Busy', 'failed' => 'Failed', 'no-answer' => 'No answer', 'canceled' => 'Canceled'], null, 'any'),
                'started_after' => Field::advanced(Field::datetime('Started after', 'Only calls that started at or after this time.')),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $calls = $this->readCalls($run, $config);

        return ['calls' => array_map($this->shapeCall(...), $calls), 'count' => count($calls)];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function readCalls(Run $run, array $config): array
    {
        $direction = (string) ($config['direction'] ?? 'any');
        $status = (string) ($config['status'] ?? 'any');
        $limit = $this->limit($config, 20);

        $calls = $this->listAll($run, $config, '/Calls.json', 'calls', [
            'To' => filled($config['to'] ?? null) ? $this->phone($config['to'], 'To') : null,
            'From' => filled($config['from'] ?? null) ? $this->phone($config['from'], 'From') : null,
            'Status' => $status !== 'any' && $status !== '' ? $status : null,
            'StartTime>' => filled($config['started_after'] ?? null) ? Carbon::parse((string) $config['started_after'])->utc()->toIso8601String() : null,
        ], $direction !== 'any' && $direction !== '' ? min(1000, $limit * 5) : $limit);

        // "inbound" and "outbound-api" / "outbound-dial": Twilio doesn't filter by direction.
        $calls = array_values(array_filter($calls, fn (array $call): bool => $direction === 'any' || $direction === '' || str_starts_with((string) ($call['direction'] ?? ''), $direction)));

        return array_slice($calls, 0, $limit);
    }
}
