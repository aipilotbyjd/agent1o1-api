<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkGetInboundMessageNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_inbound_message';
    }

    public function name(): string
    {
        return 'Postmark: Get Received Email';
    }

    public function description(): string
    {
        return 'Reads one received email: sender, recipients, text, HTML, the reply without the quoted history, and attachment details.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Message id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->inbound((array) $this->postmark($run, $config, 'GET', '/messages/inbound/'.$this->pathPart($config, 'message_id', 'The message id').'/details'));
    }
}
