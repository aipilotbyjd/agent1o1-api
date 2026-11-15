<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Snoozing needs a reason, so a task is never silently skipped.
 */
class SnoozeTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'integer', Rule::in(config('operations.snooze_hours'))],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }
}
