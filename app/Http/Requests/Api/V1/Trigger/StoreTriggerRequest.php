<?php

namespace App\Http\Requests\Api\V1\Trigger;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowUpdate->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger_type_id' => ['nullable', 'integer', 'exists:trigger_types,id'],
            'type' => ['required_without:trigger_type_id', 'nullable', Rule::in(['webhook', 'polling', 'scheduled', 'manual'])],
            'credential_id' => ['nullable', 'uuid', 'exists:credentials,id'],
            'field_values' => ['nullable', 'array'],
            'polling_interval_seconds' => ['nullable', 'integer', 'min:30'],
            'schedule_expression' => ['nullable', 'string', 'max:100'],
            'schedule_timezone' => ['nullable', 'timezone'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
