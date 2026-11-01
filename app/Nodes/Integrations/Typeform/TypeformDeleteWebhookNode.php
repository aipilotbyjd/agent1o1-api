<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformDeleteWebhookNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_delete_webhook';
    }

    public function name(): string
    {
        return 'Typeform: Delete Webhook';
    }

    public function description(): string
    {
        return 'Removes a webhook from a form by its tag.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'tag'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'tag' => Field::text('Tag', 'The webhook\'s tag.', 'crm-sync'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $tag = $this->requiredString($config, 'tag', 'The tag');

        $this->typeform($run, $config, 'DELETE', "/forms/{$form}/webhooks/".rawurlencode($tag));

        return ['deleted' => true, 'form_id' => $form, 'tag' => $tag];
    }
}
