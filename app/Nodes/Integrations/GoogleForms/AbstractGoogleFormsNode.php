<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;

/**
 * Shared request handling and response shaping for the Google Forms node
 * family.
 */
abstract class AbstractGoogleFormsNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_forms';
    }

    protected function baseUrl(): string
    {
        return 'https://forms.googleapis.com/v1';
    }

    protected function appName(): string
    {
        return 'Google Forms';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formField(): array
    {
        return Field::dynamic('Form', 'google_forms.forms', 'Pick a form, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp');
    }

    /**
     * `/forms/{id}`, with the ID URL-encoded.
     *
     * @param  array<string, mixed>  $config
     */
    protected function formPath(array $config): string
    {
        return '/forms/'.rawurlencode((string) $config['form_id']);
    }

    /**
     * Question ID => question text, for every question item in a form.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, string>
     */
    protected function questionTitles(array $form): array
    {
        $titles = [];

        foreach ((array) ($form['items'] ?? []) as $item) {
            $title = (string) ($item['title'] ?? '');

            if (isset($item['questionItem']['question']['questionId'])) {
                $titles[(string) $item['questionItem']['question']['questionId']] = $title;
            }

            foreach ((array) ($item['questionGroupItem']['questions'] ?? []) as $question) {
                if (isset($question['questionId'])) {
                    $titles[(string) $question['questionId']] = trim($title.' '.($question['rowQuestion']['title'] ?? ''));
                }
            }
        }

        return $titles;
    }

    /**
     * A response in the flat shape both nodes return: its ids and times, the
     * answers in order, and the same answers keyed by question text.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, string>  $titles  question ID => text
     * @return array<string, mixed>
     */
    protected function response(array $response, array $titles): array
    {
        $answers = [];
        $byQuestion = [];

        foreach ((array) ($response['answers'] ?? []) as $questionId => $answer) {
            $values = array_map(fn (array $value): string => (string) ($value['value'] ?? ''), (array) ($answer['textAnswers']['answers'] ?? []));
            $files = array_map(fn (array $file): string => (string) ($file['fileId'] ?? ''), (array) ($answer['fileUploadAnswers']['answers'] ?? []));
            $value = implode(', ', array_filter([...$values, ...$files], fn (string $part): bool => $part !== ''));
            $question = $titles[$questionId] ?? (string) $questionId;

            $answers[] = ['question_id' => (string) $questionId, 'question' => $question, 'value' => $value];

            // Two questions can share a title: keep both rather than overwrite.
            $key = array_key_exists($question, $byQuestion) ? "{$question} ({$questionId})" : $question;
            $byQuestion[$key] = $value;
        }

        return [
            'response_id' => $response['responseId'] ?? null,
            'created_at' => $response['createTime'] ?? null,
            'submitted_at' => $response['lastSubmittedTime'] ?? null,
            'respondent_email' => $response['respondentEmail'] ?? null,
            'answers' => $answers,
            'answers_by_question' => $byQuestion,
        ];
    }

    /**
     * The form's questions, for the form's own title and the question map.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function fetchForm(Run $run, array $config): array
    {
        return $this->get($run, $this->formPath($config), $config);
    }
}
