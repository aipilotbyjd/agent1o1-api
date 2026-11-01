<?php

namespace App\Services\Workflows;

/**
 * Validates a node's `config` against its `NodeContract::configSchema()`.
 * Supports the small subset of JSON-schema shapes this project's node
 * `configSchema()` implementations actually use (`type`: object/array/
 * string/integer/boolean, `required`, `properties`, `items`, `enum`), not a
 * general-purpose JSON Schema implementation, and deliberately not a new
 * Composer dependency.
 *
 * A value that is exactly one `{{ }}` template (`SafePattern::WHOLE`) is
 * accepted for any type when saving or publishing: its real value only
 * exists at run time, where `ResolvedConfigCaster` converts and checks it.
 *
 * A required field must be filled in: present, and not null or blank
 * text. A draft may be mid-edit, so saving one skips that check
 * (`$requireFields = false`); publishing and running still need it. Every
 * value that is present is type-checked either way.
 *
 * `issues()` says which top-level field each problem is in, with a message
 * in the field's own title ("Prompt is required."); `validate()` is the
 * same problems in the older technical wording (`config.prompt is
 * required.`).
 */
class ConfigSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $config
     * @param  bool  $allowTemplates  false once templates have been resolved, so a leftover one is reported
     * @param  bool  $requireFields  false to let required fields be missing or blank (a draft being saved)
     * @return array<int, string>
     */
    public function validate(array $schema, array $config, bool $allowTemplates = true, bool $requireFields = true): array
    {
        return array_map(fn (array $issue): string => $issue['detail'], $this->issues($schema, $config, $allowTemplates, $requireFields));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $config
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    public function issues(array $schema, array $config, bool $allowTemplates = true, bool $requireFields = true): array
    {
        return $this->validateValue($schema, $config, '', null, null, $allowTemplates, $requireFields);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    private function validateValue(array $schema, mixed $value, string $path, ?string $field, ?string $title, bool $allowTemplates, bool $requireFields): array
    {
        if ($allowTemplates && is_string($value) && preg_match(SafePattern::WHOLE, $value) === 1) {
            return [];
        }

        return match ($schema['type'] ?? null) {
            'object' => $this->validateObject($schema, $value, $path, $field, $title, $allowTemplates, $requireFields),
            'array' => $this->validateArray($schema, $value, $path, $field, $title, $allowTemplates, $requireFields),
            'string' => $this->validateScalar($schema, $value, $path, $field, $title, 'is_string', 'a string', 'text'),
            'integer' => $this->validateScalar($schema, $value, $path, $field, $title, 'is_int', 'an integer', 'a whole number'),
            'boolean' => $this->validateScalar($schema, $value, $path, $field, $title, 'is_bool', 'a boolean', 'on or off'),
            default => $this->validateEnum($schema, $value, $path, $field, $title),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    private function validateObject(array $schema, mixed $value, string $path, ?string $field, ?string $title, bool $allowTemplates, bool $requireFields): array
    {
        if (! is_array($value)) {
            return [$this->issue($field, $path, $title, 'must be an object.', 'must be a set of fields.')];
        }

        $issues = [];

        foreach ($requireFields ? ($schema['required'] ?? []) : [] as $key) {
            if (! array_key_exists($key, $value) || $this->isBlank($value[$key])) {
                $issues[] = $this->issue($field ?? $key, $this->child($path, $key), $this->titleOf($schema, $key, $title), 'is required.', 'is required.');
            }
        }

        // A null field is an unset one (a field the author cleared) so it
        // isn't type-checked; if it's required, the check above reports it.
        foreach ($schema['properties'] ?? [] as $key => $fieldSchema) {
            if (array_key_exists($key, $value) && $value[$key] !== null && ! ($requireFields && in_array($key, $schema['required'] ?? [], true) && $this->isBlank($value[$key]))) {
                $issues = [...$issues, ...$this->validateValue($fieldSchema, $value[$key], $this->child($path, $key), $field ?? $key, $this->titleOf($schema, $key, $title), $allowTemplates, $requireFields)];
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    private function validateArray(array $schema, mixed $value, string $path, ?string $field, ?string $title, bool $allowTemplates, bool $requireFields): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [$this->issue($field, $path, $title, 'must be an array.', 'must be a list.')];
        }

        if (! isset($schema['items'])) {
            return [];
        }

        $issues = [];

        foreach ($value as $index => $item) {
            $itemTitle = $title !== null ? "{$title} (item ".($index + 1).')' : null;
            $issues = [...$issues, ...$this->validateValue($schema['items'], $item, "{$path}[{$index}]", $field, $itemTitle, $allowTemplates, $requireFields)];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    private function validateScalar(array $schema, mixed $value, string $path, ?string $field, ?string $title, callable $check, string $expected, string $friendly): array
    {
        if (! $check($value)) {
            return [$this->issue($field, $path, $title, "must be {$expected}.", "must be {$friendly}.")];
        }

        return $this->validateEnum($schema, $value, $path, $field, $title);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{field: ?string, message: string, detail: string}>
     */
    private function validateEnum(array $schema, mixed $value, string $path, ?string $field, ?string $title): array
    {
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $choices = implode(', ', $schema['enum']);

            return [$this->issue($field, $path, $title, "must be one of: {$choices}.", "must be one of: {$choices}.")];
        }

        return [];
    }

    /**
     * @return array{field: ?string, message: string, detail: string}
     */
    private function issue(?string $field, string $path, ?string $title, string $detail, string $friendly): array
    {
        return [
            'field' => $field,
            'message' => ($title ?? $this->label($path)).' '.$friendly,
            'detail' => $this->label($path).' '.$detail,
        ];
    }

    /**
     * Nothing, or blank text: what a required field the author hasn't
     * filled in looks like. An empty list or set of fields is a real value
     * (a workflow interface with no inputs yet, a node with no headers).
     */
    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function titleOf(array $schema, string $key, ?string $parentTitle): string
    {
        $own = $schema['properties'][$key]['title'] ?? ucfirst(str_replace('_', ' ', $key));

        return $parentTitle !== null ? "{$parentTitle} → {$own}" : $own;
    }

    private function child(string $path, string $field): string
    {
        return $path === '' ? $field : "{$path}.{$field}";
    }

    private function label(string $path): string
    {
        return $path === '' ? 'config' : "config.{$path}";
    }
}
