<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkAddSuppressionsNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_add_suppressions';
    }

    public function name(): string
    {
        return 'Postmark: Suppress Addresses';
    }

    public function description(): string
    {
        return 'Stops Postmark sending to these addresses in a stream.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['emails'],
            'properties' => [
                ...$this->credentialFields(),
                'emails' => Field::emails('Emails', 'Up to 50 addresses.'),
                'stream' => $this->streamField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $emails = $this->suppressionList($config);
        $found = (array) $this->postmark($run, $config, 'POST', '/message-streams/'.rawurlencode($this->stream($config)).'/suppressions', ['Suppressions' => array_map(fn (string $email): array => ['EmailAddress' => $email], $emails)]);

        return $this->suppressionResult($found, 'suppressed');
    }
}
