<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TypeformDeleteFormNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_delete_form';
    }

    public function name(): string
    {
        return 'Typeform: Delete Form';
    }

    public function description(): string
    {
        return 'Deletes a form and all its responses. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->formId($config);

        $this->typeform($run, $config, 'DELETE', "/forms/{$id}");

        return ['deleted' => true, 'form_id' => $id];
    }
}
