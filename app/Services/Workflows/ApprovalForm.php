<?php

namespace App\Services\Workflows;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The questions an approval step asks the person who decides it: a reason, a
 * budget, a choice of options. The step's settings define the form; the
 * answers are checked against it before the run moves on, so a later step can
 * rely on the types it gets.
 *
 * @phpstan-type Field array{key: string, label: string, type: string, required: bool, options: list<string>, help: ?string, default: mixed}
 */
final class ApprovalForm
{
    public const int MAX_FIELDS = 20;

    public const int MAX_OPTIONS = 50;

    public const int MAX_TEXT = 5000;

    /**
     * @var list<string>
     */
    public const array TYPES = ['text', 'textarea', 'number', 'boolean', 'select', 'email', 'date'];

    private const string KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/';

    /**
     * What is wrong with an approval step's form, as sentences for the person
     * building it. Empty when the form is fine or there is none.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function problems(array $config): array
    {
        $fields = $config['fields'] ?? null;

        if ($fields === null || $fields === [] || $fields === '') {
            return [];
        }

        // A template resolves at run time; there is nothing to check yet.
        if (is_string($fields) && str_contains($fields, '{{')) {
            return [];
        }

        if (! is_array($fields) || ! array_is_list($fields)) {
            return ['The form’s fields must be a list.'];
        }

        if (count($fields) > self::MAX_FIELDS) {
            return ['An approval form can have at most '.self::MAX_FIELDS.' fields.'];
        }

        $problems = [];
        $seen = [];

        foreach ($fields as $index => $field) {
            $position = $index + 1;

            if (! is_array($field)) {
                $problems[] = "Field {$position} is not a field definition.";

                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                $problems[] = "Field {$position} needs a name made of letters, numbers and underscores, starting with a letter.";
            } elseif (isset($seen[$key])) {
                $problems[] = "Two fields are both called “{$key}”.";
            }

            $seen[$key] = true;

            if (! in_array($type, self::TYPES, true)) {
                $problems[] = "The field “{$key}” has an unknown type “{$type}”. Use one of: ".implode(', ', self::TYPES).'.';
            }

            if ($type === 'select' && self::options($field) === []) {
                $problems[] = "The dropdown “{$key}” needs at least one option.";
            }

            if (count(self::options($field)) > self::MAX_OPTIONS) {
                $problems[] = "The dropdown “{$key}” can have at most ".self::MAX_OPTIONS.' options.';
            }
        }

        return $problems;
    }

    /**
     * The form as the person deciding sees it, with every setting filled in.
     *
     * @param  array<string, mixed>  $config
     * @return list<Field>
     */
    public static function fields(array $config): array
    {
        $fields = $config['fields'] ?? [];

        if (! is_array($fields) || ! array_is_list($fields)) {
            return [];
        }

        $normalized = [];

        foreach (array_slice($fields, 0, self::MAX_FIELDS) as $field) {
            if (! is_array($field) || preg_match(self::KEY_PATTERN, (string) ($field['key'] ?? '')) !== 1) {
                continue;
            }

            $type = in_array($field['type'] ?? 'text', self::TYPES, true) ? (string) ($field['type'] ?? 'text') : 'text';
            $key = (string) $field['key'];

            $normalized[] = [
                'key' => $key,
                'label' => trim((string) ($field['label'] ?? '')) ?: ucfirst(str_replace('_', ' ', $key)),
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'options' => $type === 'select' ? self::options($field) : [],
                'help' => filled($field['help'] ?? null) ? trim((string) $field['help']) : null,
                'default' => $field['default'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * Checks the answers against the form and returns the values to keep, one
     * per field and typed (a number is a number, a yes/no is a boolean), or
     * the messages to show.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $answers
     * @return array{values: array<string, mixed>, errors: array<string, list<string>>}
     */
    public static function check(array $fields, array $answers): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules[$field['key']] = self::rulesFor($field);
        }

        $validator = Validator::make($answers, $rules, [], array_column($fields, 'label', 'key'));

        if ($validator->fails()) {
            return ['values' => [], 'errors' => $validator->errors()->toArray()];
        }

        $values = [];

        foreach ($fields as $field) {
            $values[$field['key']] = self::cast($field, $answers[$field['key']] ?? $field['default']);
        }

        return ['values' => $values, 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function options(array $field): array
    {
        $options = $field['options'] ?? [];

        if (is_string($options)) {
            $options = preg_split('/[\r\n,]+/', $options) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(fn (mixed $option): string => trim((string) $option), is_array($options) ? $options : []), fn (string $option): bool => $option !== '')));
    }

    /**
     * @param  Field  $field
     * @return list<mixed>
     */
    private static function rulesFor(array $field): array
    {
        $presence = $field['required'] ? 'required' : 'nullable';

        return match ($field['type']) {
            'number' => [$presence, 'numeric'],
            // Real booleans, 1/0, and the words people send: true/false, yes/no, on/off.
            'boolean' => [$presence, fn (string $attribute, mixed $value, \Closure $fail): mixed => is_bool($value) || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
                ? null
                : $fail('The :attribute field must be yes or no.')],
            'email' => [$presence, 'email', 'max:254'],
            'date' => [$presence, 'date'],
            'select' => [$presence, 'string', Rule::in($field['options'])],
            'textarea' => [$presence, 'string', 'max:'.self::MAX_TEXT],
            default => [$presence, 'string', 'max:500'],
        };
    }

    /**
     * @param  Field  $field
     */
    private static function cast(array $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $field['type'] === 'boolean' ? false : null;
        }

        return match ($field['type']) {
            'number' => $value + 0,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_string($value) ? trim($value) : $value,
        };
    }
}
