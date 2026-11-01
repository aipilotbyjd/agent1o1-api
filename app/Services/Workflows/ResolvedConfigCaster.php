<?php

namespace App\Services\Workflows;

use App\Exceptions\InvalidNodeInputException;

/**
 * A field whose whole value is one `{{ }}` template takes whatever type the
 * upstream output had: a number field wired to a text output receives
 * "42", a list field wired to a JSON string receives that string. After
 * `TemplateResolver` runs, this converts each such value to the type the
 * node's `configSchema()` declares, drops one that resolved to nothing (so
 * the node's own default applies, or a required field is reported), then
 * validates the result so the node fails with a clear message instead of
 * receiving the wrong type.
 *
 * Only templated values are converted: a literal config was already
 * validated when the workflow was saved and published.
 */
class ResolvedConfigCaster
{
    public function __construct(private readonly ConfigSchemaValidator $validator) {}

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $raw  the config before templating
     * @param  array<string, mixed>  $resolved  the same config after templating
     * @return array<string, mixed>
     *
     * @throws InvalidNodeInputException
     */
    public function cast(array $schema, array $raw, array $resolved): array
    {
        $config = $this->castValue($schema, $raw, $resolved);
        $errors = $this->validator->validate($schema, $config, allowTemplates: false);

        if ($errors !== []) {
            throw new InvalidNodeInputException($errors);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function castValue(array $schema, mixed $raw, mixed $resolved): mixed
    {
        if (is_string($raw) && preg_match(SafePattern::WHOLE, $raw) === 1) {
            return $this->convert($schema, $resolved);
        }

        $type = $schema['type'] ?? null;

        if ($type === 'object' && is_array($resolved) && is_array($raw) && isset($schema['properties'])) {
            foreach ($schema['properties'] as $field => $fieldSchema) {
                if (! array_key_exists($field, $resolved)) {
                    continue;
                }

                $value = $this->castValue($fieldSchema, $raw[$field] ?? null, $resolved[$field]);

                if ($value === null && $this->isWholeTemplate($raw[$field] ?? null)) {
                    unset($resolved[$field]);
                } else {
                    $resolved[$field] = $value;
                }
            }

            return $resolved;
        }

        if ($type === 'array' && isset($schema['items']) && is_array($resolved) && array_is_list($resolved) && is_array($raw)) {
            return array_map(
                fn (mixed $item, int $index) => $this->castValue($schema['items'], $raw[$index] ?? null, $item),
                $resolved,
                array_keys($resolved),
            );
        }

        return $resolved;
    }

    /**
     * Best-effort conversion; anything that can't be converted is returned
     * unchanged for the validator to report.
     *
     * @param  array<string, mixed>  $schema
     */
    private function convert(array $schema, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return ($schema['type'] ?? null) === 'string' ? '' : null;
        }

        return match ($schema['type'] ?? null) {
            'string' => $this->toString($value),
            'integer' => $this->toInteger($value),
            'number' => is_numeric($value) ? $value + 0 : $value,
            'boolean' => $this->toBoolean($value),
            'array' => $this->toArray($schema, $value),
            'object' => $this->toObject($value),
            default => $value,
        };
    }

    private function toString(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => json_encode($value),
            default => $value,
        };
    }

    private function toInteger(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^\s*-?\d+(\.0+)?\s*$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private function toBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => $value,
            };
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function toArray(array $schema, mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded) && array_is_list($decoded)) {
                $value = $decoded;
            } else {
                return [$value];
            }
        }

        if (! is_array($value)) {
            return [$value];
        }

        if (! array_is_list($value)) {
            return $value;
        }

        return isset($schema['items'])
            ? array_map(fn (mixed $item) => $this->convert($schema['items'], $item), $value)
            : $value;
    }

    private function toObject(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    private function isWholeTemplate(mixed $value): bool
    {
        return is_string($value) && preg_match(SafePattern::WHOLE, $value) === 1;
    }
}
