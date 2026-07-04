<?php

namespace App\Http\Requests\Api\V1\Trigger;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SetPollingIntervalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowUpdate->value);
    }

    public function rules(): array
    {
        return [
            'polling_interval_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
        ];
    }
}
