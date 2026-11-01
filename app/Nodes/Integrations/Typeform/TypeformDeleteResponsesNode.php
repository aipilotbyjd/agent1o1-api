<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TypeformDeleteResponsesNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_delete_responses';
    }

    public function name(): string
    {
        return 'Typeform: Delete Responses';
    }

    public function description(): string
    {
        return 'Deletes responses from a form by their tokens (up to 1000). This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'response_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'response_ids' => Field::list('Response tokens', 'Up to 1000.', 'a1b2c3d4e5f6'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $ids = $config['response_ids'] ?? [];
        $ids = array_values(array_unique(array_filter(array_map('trim', array_map('strval', is_array($ids) ? $ids : explode(',', (string) $ids))))));

        if ($ids === [] || count($ids) > 1000) {
            throw new InvalidArgumentException('Give between 1 and 1000 response tokens.');
        }

        $this->typeform($run, $config, 'DELETE', "/forms/{$form}/responses", query: ['included_response_ids' => implode(',', $ids)]);

        return ['deleted' => true, 'form_id' => $form, 'count' => count($ids)];
    }
}
