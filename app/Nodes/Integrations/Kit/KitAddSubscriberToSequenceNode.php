<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitAddSubscriberToSequenceNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_add_subscriber_to_sequence';
    }

    public function name(): string
    {
        return 'Kit: Add Subscriber to Sequence';
    }

    public function description(): string
    {
        return 'Starts a subscriber on an email sequence.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sequence_id', 'subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'sequence_id' => Field::dynamic('Sequence', 'kit.sequences', 'Pick a sequence.', 'Welcome series', type: 'integer'),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sequence = $this->numericId($config, 'sequence_id', 'The sequence');
        $value = trim((string) ($config['subscriber'] ?? ''));

        if (ctype_digit($value)) {
            $this->kit($run, $config, 'POST', "/sequences/{$sequence}/subscribers/{$value}");
        } else {
            $this->kit($run, $config, 'POST', "/sequences/{$sequence}/subscribers", ['email_address' => $this->email($config, 'subscriber')]);
        }

        return ['added' => true, 'sequence_id' => $sequence, 'subscriber' => $value];
    }
}
