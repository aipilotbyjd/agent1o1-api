<?php

namespace App\Nodes\Integrations\GoogleForms;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleFormsAddQuestionNode extends AbstractGoogleFormsNode
{
    private const array CHOICE_TYPES = ['multiple_choice' => 'RADIO', 'checkboxes' => 'CHECKBOX', 'dropdown' => 'DROP_DOWN'];

    public function type(): string
    {
        return 'google_forms_add_question';
    }

    public function name(): string
    {
        return 'Google Forms: Add Question';
    }

    public function description(): string
    {
        return 'Adds a question to a form: text, choice, scale, date or time. Goes at the end unless you give a position.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'title' => Field::text('Question', null, 'What did you like most?'),
                'help_text' => Field::advanced(Field::textarea('Help text', 'Shown under the question.')),
                'kind' => Field::select('Type', [
                    'short_text' => 'Short answer',
                    'paragraph' => 'Paragraph',
                    'multiple_choice' => 'Multiple choice',
                    'checkboxes' => 'Checkboxes',
                    'dropdown' => 'Dropdown',
                    'scale' => 'Linear scale',
                    'date' => 'Date',
                    'time' => 'Time',
                ], null, 'short_text'),
                'options' => Field::list('Choices', 'For multiple choice, checkboxes and dropdown: one choice per row.', 'Great'),
                'scale_low' => Field::advanced(Field::integer('Scale from', 'Linear scale only: 0 or 1.', 1, 0, 1)),
                'scale_high' => Field::advanced(Field::integer('Scale to', 'Linear scale only: 2 to 10.', 5, 2, 10)),
                'low_label' => Field::advanced(Field::text('Label for the low end')),
                'high_label' => Field::advanced(Field::text('Label for the high end')),
                'required' => Field::boolean('Required', 'People must answer it.', false),
                'position' => Field::advanced(Field::integer('Position', 'Where it goes, starting at 0. Empty is the end.', null, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $title = trim((string) ($config['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('The question is required.');
        }

        $question = ['required' => (bool) ($config['required'] ?? false), ...$this->kind($config)];
        $position = is_numeric($config['position'] ?? null) ? (int) $config['position'] : count((array) ($this->fetchForm($run, $config)['items'] ?? []));

        $help = trim((string) ($config['help_text'] ?? ''));
        $reply = $this->post($run, $this->formPath($config).':batchUpdate', $config, ['requests' => [['createItem' => [
            'item' => ['title' => $title, ...($help === '' ? [] : ['description' => $help]), 'questionItem' => ['question' => $question]],
            'location' => ['index' => $position],
        ]]]]);

        $created = (array) ($reply['replies'][0]['createItem'] ?? []);

        return [
            'added' => true,
            'form_id' => (string) $config['form_id'],
            'item_id' => $created['itemId'] ?? null,
            'question_id' => $created['questionId'][0] ?? null,
            'position' => $position,
        ];
    }

    /**
     * The kind-specific part of the question.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function kind(array $config): array
    {
        $kind = (string) ($config['kind'] ?? 'short_text');

        if (isset(self::CHOICE_TYPES[$kind])) {
            $choices = array_values(array_unique(array_filter(array_map(fn (mixed $choice): string => trim((string) $choice), (array) ($config['options'] ?? [])))));

            if ($choices === []) {
                throw new InvalidArgumentException('Give at least one choice.');
            }

            return ['choiceQuestion' => ['type' => self::CHOICE_TYPES[$kind], 'options' => array_map(fn (string $choice): array => ['value' => $choice], $choices)]];
        }

        return match ($kind) {
            'short_text' => ['textQuestion' => ['paragraph' => false]],
            'paragraph' => ['textQuestion' => ['paragraph' => true]],
            'date' => ['dateQuestion' => ['includeYear' => true]],
            'time' => ['timeQuestion' => ['duration' => false]],
            'scale' => $this->scale($config),
            default => throw new InvalidArgumentException("\"{$kind}\" is not a question type."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function scale(array $config): array
    {
        $low = (int) ($config['scale_low'] ?? 1);
        $high = (int) ($config['scale_high'] ?? 5);

        if (! in_array($low, [0, 1], true) || $high < 2 || $high > 10 || $high <= $low) {
            throw new InvalidArgumentException('A scale runs from 0 or 1 up to a number between 2 and 10.');
        }

        return ['scaleQuestion' => array_filter(['low' => $low, 'high' => $high, 'lowLabel' => trim((string) ($config['low_label'] ?? '')), 'highLabel' => trim((string) ($config['high_label'] ?? ''))], fn (mixed $value): bool => $value !== '')];
    }
}
