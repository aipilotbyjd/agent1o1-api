<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TypeformGetFormNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_get_form';
    }

    public function name(): string
    {
        return 'Typeform: Get Form';
    }

    public function description(): string
    {
        return 'Gets a form: its title, link, settings and every question with its id and ref.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $form = (array) $this->typeform($run, $config, 'GET', "/forms/{$id}");
        $questions = $this->questions($run, $config, $id);

        return [
            ...$this->shapeForm($form),
            'questions' => array_values(array_map(fn (string $fieldId, array $question): array => ['field_id' => $fieldId, ...$question], array_keys($questions), $questions)),
            'question_count' => count($questions),
            'welcome_screens' => count((array) ($form['welcome_screens'] ?? [])),
            'thank_you_screens' => count((array) ($form['thankyou_screens'] ?? [])),
            'hidden_fields' => array_values((array) ($form['hidden'] ?? [])),
        ];
    }
}
