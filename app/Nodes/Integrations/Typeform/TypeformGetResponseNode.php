<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class TypeformGetResponseNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_get_response';
    }

    public function name(): string
    {
        return 'Typeform: Get Response';
    }

    public function description(): string
    {
        return 'Gets one response by its token, each answer next to its question.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'response_id' => Field::text('Response token', 'The token or response_id of a response.', 'a1b2c3d4e5f6'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $token = $this->requiredString($config, 'response_id', 'The response token');
        $found = (array) $this->typeform($run, $config, 'GET', "/forms/{$form}/responses", query: ['included_response_ids' => $token, 'page_size' => 1]);
        $response = $found['items'][0] ?? null;

        if (! is_array($response)) {
            throw new RuntimeException("Typeform has no response \"{$token}\" on this form.");
        }

        return [...$this->shapeResponse($response, $this->questions($run, $config, $form)), 'form_id' => $form];
    }
}
