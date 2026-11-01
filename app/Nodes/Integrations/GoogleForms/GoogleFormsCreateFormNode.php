<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleFormsCreateFormNode extends AbstractGoogleFormsNode
{
    public function type(): string
    {
        return 'google_forms_create_form';
    }

    public function name(): string
    {
        return 'Google Forms: Create Form';
    }

    public function description(): string
    {
        return 'Creates a form with short-answer or paragraph questions.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', 'Shown to people filling in the form.', 'Customer feedback'),
                'description' => Field::textarea('Description'),
                'questions' => Field::list('Questions', 'One question per row. Each becomes a short-answer question.', 'What did you like most?'),
                'paragraph_questions' => Field::boolean('Use paragraph answers', 'Let people write longer answers.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $title = (string) $config['title'];

        // A new form takes only its title; everything else goes in a second request.
        $form = $this->post($run, '/forms', $config, ['info' => ['title' => $title, 'documentTitle' => $title]]);
        $formId = rawurlencode((string) $form['formId']);

        $requests = [];
        $description = trim((string) ($config['description'] ?? ''));

        if ($description !== '') {
            $requests[] = ['updateFormInfo' => ['info' => ['description' => $description], 'updateMask' => 'description']];
        }

        $questions = array_values(array_filter(array_map(fn (mixed $question): string => trim((string) $question), (array) ($config['questions'] ?? []))));

        foreach ($questions as $index => $question) {
            $requests[] = ['createItem' => [
                'item' => [
                    'title' => $question,
                    'questionItem' => ['question' => ['textQuestion' => ['paragraph' => (bool) ($config['paragraph_questions'] ?? false)]]],
                ],
                'location' => ['index' => $index],
            ]];
        }

        if ($requests !== []) {
            $this->post($run, "/forms/{$formId}:batchUpdate", $config, ['requests' => $requests]);
        }

        return [
            'form_id' => $form['formId'],
            'title' => $form['info']['title'] ?? $title,
            'responder_url' => $form['responderUri'] ?? null,
            'edit_url' => 'https://docs.google.com/forms/d/'.$form['formId'].'/edit',
            'question_count' => count($questions),
        ];
    }
}
