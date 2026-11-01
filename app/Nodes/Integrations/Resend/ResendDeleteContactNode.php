<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendDeleteContactNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_delete_contact';
    }

    public function name(): string
    {
        return 'Resend: Delete Contact';
    }

    public function description(): string
    {
        return 'Removes a contact from an audience. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'contact'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
                'contact' => Field::text('Contact', 'Their email address or Resend id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->resend($run, $config, 'DELETE', '/audiences/'.$this->pathPart($config, 'audience_id', 'The audience').'/contacts/'.$this->pathPart($config, 'contact', 'The contact'));

        return ['deleted' => true, 'contact' => trim((string) $config['contact'])];
    }
}
