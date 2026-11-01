<?php

namespace App\Enums\Workflows;

/**
 * The input types a workflow's interface can declare. Deliberately small:
 * these map onto both an HTML form control and a Laravel validation rule,
 * and anything richer belongs in the workflow's own logic rather than in its
 * front door.
 */
enum InterfaceFieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Json = 'json';

    /**
     * The validation rule this type contributes, before required/optional is
     * layered on by `WorkflowInterface`.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::String, self::Select => ['string', 'max:65535'],
            self::Text => ['string'],
            self::Number => ['numeric'],
            self::Boolean => ['boolean'],
            self::Json => ['array'],
        };
    }
}
