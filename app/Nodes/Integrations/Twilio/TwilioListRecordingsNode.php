<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class TwilioListRecordingsNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_list_recordings';
    }

    public function name(): string
    {
        return 'Twilio: List Recordings';
    }

    public function description(): string
    {
        return 'Lists call recordings, newest first, optionally of one call.';
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
                'call_sid' => Field::text('Call SID', 'Only recordings of this call.', 'CA...'),
                'created_after' => Field::advanced(Field::datetime('Created after', 'Only recordings made at or after this time.')),
                'limit' => Field::integer('Max results', null, 20, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $recordings = $this->readRecordings($run, $config);

        return ['recordings' => $recordings, 'count' => count($recordings)];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function readRecordings(Run $run, array $config): array
    {
        $records = $this->listAll($run, $config, '/Recordings.json', 'recordings', [
            'CallSid' => filled($config['call_sid'] ?? null) ? $this->sid($config, 'call_sid', 'Call SID', 'CA') : null,
            'DateCreated>' => filled($config['created_after'] ?? null) ? Carbon::parse((string) $config['created_after'])->utc()->toIso8601String() : null,
        ], $this->limit($config, 20));

        [$account] = $this->account($run, $config);

        return array_map(fn (array $recording): array => [
            'sid' => $recording['sid'] ?? null,
            'call_sid' => $recording['call_sid'] ?? null,
            'status' => $recording['status'] ?? null,
            'duration_seconds' => isset($recording['duration']) ? (int) $recording['duration'] : null,
            'channels' => $recording['channels'] ?? null,
            'source' => $recording['source'] ?? null,
            'price' => isset($recording['price']) ? (float) $recording['price'] : null,
            'media_url' => isset($recording['sid']) ? "https://api.twilio.com/2010-04-01/Accounts/{$account}/Recordings/{$recording['sid']}.mp3" : null,
            'date_created' => $this->date($recording['date_created'] ?? null),
        ], $records);
    }
}
