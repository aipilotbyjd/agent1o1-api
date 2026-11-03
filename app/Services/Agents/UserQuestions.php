<?php

namespace App\Services\Agents;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The shape of a question an agent asks the person it's chatting with
 * (`AskUserTool`), and of the answer that comes back. A question is one of:
 *
 * - free text: just `question`;
 * - a choice: `options` shown as buttons, `multiple` to pick several, and
 *   `allow_other` to also accept a typed answer;
 * - a form: `fields`, each a labelled input the person fills in.
 *
 * The model writes the question, so everything is trimmed and capped before
 * it is stored or shown; the person writes the answer, so it is checked
 * against the question before the agent sees it.
 */
class UserQuestions
{
    public const int MAX_QUESTION_CHARS = 1000;

    public const int MAX_OPTIONS = 10;

    public const int MAX_FIELDS = 10;

    public const int MAX_LABEL_CHARS = 200;

    public const int MAX_ANSWER_CHARS = 5000;

    /** @var list<string> */
    public const array FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'checkbox'];

    /**
     * The model's arguments as a question that is safe to store and render.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{question: string, options: list<string>, multiple: bool, allow_other: bool, fields: list<array{name: string, label: string, type: string, options: list<string>, required: bool, placeholder: string|null}>}
     */
    public function normalize(array $arguments): array
    {
        $fields = collect(is_array($arguments['fields'] ?? null) ? $arguments['fields'] : [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['label'] ?? $field['name'] ?? null))
            ->take(self::MAX_FIELDS)
            ->values()
            ->map(function (array $field, int $index): array {
                $type = in_array($field['type'] ?? null, self::FIELD_TYPES, true) ? $field['type'] : 'text';
                $options = $this->options($field['options'] ?? []);

                // A select with nothing to pick from can only be typed into.
                if ($type === 'select' && $options === []) {
                    $type = 'text';
                }

                $label = $this->clean((string) ($field['label'] ?? $field['name']), self::MAX_LABEL_CHARS);

                return [
                    'name' => $this->fieldName((string) ($field['name'] ?? $label), $index),
                    'label' => $label,
                    'type' => $type,
                    'options' => $type === 'select' ? $options : [],
                    'required' => (bool) ($field['required'] ?? false),
                    'placeholder' => filled($field['placeholder'] ?? null) ? $this->clean((string) $field['placeholder'], self::MAX_LABEL_CHARS) : null,
                ];
            })
            ->unique('name')
            ->values()
            ->all();

        $options = $fields === [] ? $this->options($arguments['options'] ?? []) : [];

        return [
            'question' => $this->clean((string) ($arguments['question'] ?? ''), self::MAX_QUESTION_CHARS),
            'options' => $options,
            'multiple' => $options !== [] && (bool) ($arguments['multiple'] ?? false),
            // Free text is always allowed when there is nothing to pick.
            'allow_other' => $options === [] || (bool) ($arguments['allow_other'] ?? true),
            'fields' => $fields,
        ];
    }

    /**
     * The person's answer, checked against the question it answers: a
     * string for free text, the picked option(s) for a choice, or the
     * filled-in values keyed by field name for a form.
     *
     * @param  array<string, mixed>  $question  as `normalize()` returns it
     * @return string|list<string>|array<string, string|bool|null>
     *
     * @throws ValidationException
     */
    public function answer(array $question, mixed $answer, string $attribute = 'answer'): string|array
    {
        if (($question['fields'] ?? []) !== []) {
            return $this->formAnswer($question['fields'], $answer, $attribute);
        }

        $options = $question['options'] ?? [];

        if ($options !== [] && ($question['multiple'] ?? false) && is_array($answer)) {
            $picked = collect($answer)->map(fn (mixed $value): string => $this->clean((string) $value, self::MAX_ANSWER_CHARS))->filter()->unique()->values();

            $unknown = $picked->reject(fn (string $value): bool => in_array($value, $options, true));

            if ($picked->isEmpty() || ($unknown->isNotEmpty() && ! ($question['allow_other'] ?? false))) {
                throw ValidationException::withMessages([$attribute => 'Pick at least one of the offered options.']);
            }

            return $picked->all();
        }

        if (! is_scalar($answer)) {
            throw ValidationException::withMessages([$attribute => 'The answer must be text.']);
        }

        $text = trim((string) $answer);

        if ($text === '') {
            throw ValidationException::withMessages([$attribute => 'The answer is empty.']);
        }

        if (mb_strlen($text) > self::MAX_ANSWER_CHARS) {
            throw ValidationException::withMessages([$attribute => 'The answer is too long.']);
        }

        if ($options !== [] && ! ($question['allow_other'] ?? false) && ! in_array($text, $options, true)) {
            throw ValidationException::withMessages([$attribute => 'Pick one of the offered options.']);
        }

        return $text;
    }

    /**
     * What the agent is told the person answered.
     *
     * @param  string|array<int|string, mixed>  $answer
     */
    public function resultText(string|array $answer): string
    {
        return json_encode([
            'status' => 'answered',
            'answer' => $answer,
            'note' => 'This is the user\'s answer to your question. Carry on with the task using it.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * The answer as one readable line, for the chat transcript.
     *
     * @param  string|array<int|string, mixed>  $answer
     */
    public function summary(string|array $answer): string
    {
        if (is_string($answer)) {
            return $answer;
        }

        if (array_is_list($answer)) {
            return implode(', ', $answer);
        }

        return collect($answer)
            ->map(fn (mixed $value, string $name): string => $name.': '.(is_bool($value) ? ($value ? 'yes' : 'no') : (string) ($value ?? '')))
            ->implode('; ');
    }

    /**
     * @param  list<array{name: string, label: string, type: string, options: list<string>, required: bool}>  $fields
     * @return array<string, string|bool|null>
     *
     * @throws ValidationException
     */
    private function formAnswer(array $fields, mixed $answer, string $attribute): array
    {
        if (! is_array($answer) || array_is_list($answer) && $answer !== []) {
            throw ValidationException::withMessages([$attribute => 'Fill in the form.']);
        }

        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $raw = $answer[$field['name']] ?? null;
            $key = "{$attribute}.{$field['name']}";

            if ($field['type'] === 'checkbox') {
                $values[$field['name']] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($value === '') {
                if ($field['required']) {
                    $errors[$key] = "{$field['label']} is required.";
                }

                $values[$field['name']] = null;

                continue;
            }

            $error = match (true) {
                mb_strlen($value) > self::MAX_ANSWER_CHARS => "{$field['label']} is too long.",
                $field['type'] === 'number' && ! is_numeric($value) => "{$field['label']} must be a number.",
                $field['type'] === 'date' && strtotime($value) === false => "{$field['label']} must be a date.",
                $field['type'] === 'select' && ! in_array($value, $field['options'], true) => "Pick one of the options for {$field['label']}.",
                default => null,
            };

            if ($error !== null) {
                $errors[$key] = $error;
            }

            $values[$field['name']] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function options(mixed $options): array
    {
        return collect(is_array($options) ? $options : [])
            ->filter(fn (mixed $option): bool => is_scalar($option))
            ->map(fn (mixed $option): string => $this->clean((string) $option, self::MAX_LABEL_CHARS))
            ->filter()
            ->unique()
            ->take(self::MAX_OPTIONS)
            ->values()
            ->all();
    }

    private function fieldName(string $name, int $index): string
    {
        return Str::limit(Str::slug($name, '_'), 60, '') ?: "field_{$index}";
    }

    private function clean(string $text, int $limit): string
    {
        return Str::limit(trim($text), $limit, '…');
    }
}
