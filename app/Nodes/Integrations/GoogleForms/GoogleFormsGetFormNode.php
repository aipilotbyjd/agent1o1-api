<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleFormsGetFormNode extends AbstractGoogleFormsNode
{
    public function type(): string
    {
        return 'google_forms_get_form';
    }

    public function name(): string
    {
        return 'Google Forms: Get Form';
    }

    public function description(): string
    {
        return 'Reads a form\'s title, link and questions.';
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
        $form = $this->fetchForm($run, $config);
        $questions = [];

        foreach ($this->questionTitles($form) as $id => $title) {
            $questions[] = ['question_id' => (string) $id, 'question' => $title];
        }

        return [
            'form_id' => $form['formId'] ?? $config['form_id'],
            'title' => $form['info']['title'] ?? null,
            'description' => $form['info']['description'] ?? null,
            'responder_url' => $form['responderUri'] ?? null,
            'questions' => $questions,
        ];
    }
}
