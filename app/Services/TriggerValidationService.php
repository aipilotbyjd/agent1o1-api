<?php

namespace App\Services;

use App\Models\TriggerType;
use App\Models\TriggerTypeField;
use InvalidArgumentException;

class TriggerValidationService
{
    /**
     * Validate user-supplied field values against a trigger type's field schema.
     * Values are keyed by field_name.
     *
     * @param  array<string, mixed>  $fieldValues
     *
     * @throws InvalidArgumentException
     */
    public function validateFieldValues(TriggerType $triggerType, array $fieldValues): void
    {
        if ($triggerType->requires_credential === false && $triggerType->requires_config_fields === false) {
            return;
        }

        foreach ($triggerType->fields as $field) {
            $value = $fieldValues[$field->field_name] ?? null;

            if ($field->is_required && (is_null($value) || trim((string) $value) === '')) {
                throw new InvalidArgumentException("Field '{$field->field_label}' is required");
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($field->validation_regex) {
                $result = @preg_match('/'.$field->validation_regex.'/', (string) $value);

                if ($result === false) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' has an invalid validation pattern");
                }

                if ($result === 0) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' has invalid format");
                }
            }

            $this->validateFieldType($field, $value);
        }
    }

    private function validateFieldType(TriggerTypeField $field, mixed $value): void
    {
        switch ($field->field_type) {
            case 'number':
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' must be a number");
                }
                break;

            case 'time':
                if (! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', (string) $value)) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' must be in HH:MM format");
                }
                break;

            case 'date':
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' must be in YYYY-MM-DD format");
                }
                break;

            case 'select':
                $validValues = array_column($field->options ?? [], 'value');
                if (! in_array($value, $validValues, true)) {
                    throw new InvalidArgumentException("Field '{$field->field_label}' has an invalid value");
                }
                break;

            case 'multiselect':
                $values = is_array($value) ? $value : [$value];
                $validValues = array_column($field->options ?? [], 'value');
                foreach ($values as $v) {
                    if (! in_array($v, $validValues, true)) {
                        throw new InvalidArgumentException("Field '{$field->field_label}' has an invalid value");
                    }
                }
                break;
        }
    }
}
