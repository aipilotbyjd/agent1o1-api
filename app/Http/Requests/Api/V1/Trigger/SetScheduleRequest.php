<?php

namespace App\Http\Requests\Api\V1\Trigger;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowUpdate->value);
    }

    public function rules(): array
    {
        return [
            'schedule_expression' => ['required', 'string', 'max:100'],
            'schedule_timezone' => ['nullable', 'timezone'],
        ];
    }
}
