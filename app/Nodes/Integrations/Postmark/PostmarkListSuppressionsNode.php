<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkListSuppressionsNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_list_suppressions';
    }

    public function name(): string
    {
        return 'Postmark: List Suppressions';
    }

    public function description(): string
    {
        return 'Lists the addresses Postmark will not send to in a stream, and why.';
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
                'stream' => $this->streamField(),
                'reason' => Field::advanced(Field::select('Reason', ['any' => 'Any', 'HardBounce' => 'Hard bounce', 'SpamComplaint' => 'Spam complaint', 'ManualSuppression' => 'Added by hand'], null, 'any')),
                'email' => Field::advanced(Field::text('Email address')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $reason = (string) ($config['reason'] ?? 'any');
        $found = (array) $this->postmark($run, $config, 'GET', '/message-streams/'.rawurlencode($this->stream($config)).'/suppressions/dump', query: ['SuppressionReason' => $reason === 'any' ? null : $reason, 'EmailAddress' => filled($config['email'] ?? null) ? trim((string) $config['email']) : null]);
        $suppressions = array_map(fn (array $row): array => ['email' => $row['EmailAddress'] ?? null, 'reason' => $row['SuppressionReason'] ?? null, 'origin' => $row['Origin'] ?? null, 'created_at' => $row['CreatedAt'] ?? null], array_values(array_filter((array) ($found['Suppressions'] ?? []), 'is_array')));

        return ['suppressions' => $suppressions, 'count' => count($suppressions)];
    }
}
