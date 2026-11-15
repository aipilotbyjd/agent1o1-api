<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Operations;

use App\Enums\Operations\TaskCadence;
use App\Models\Operations\OperationsTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new checklist task (POST) or a partial change to one (PATCH). People only
 * add manual tasks; the automatic ones come from the built-in catalogue.
 */
class TaskRequest extends FormRequest
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
        $presence = $this->route('task') instanceof OperationsTask ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cadence' => [$presence, Rule::enum(TaskCadence::class)],
            'target' => ['sometimes', 'nullable', 'string', Rule::in(config('operations.targets'))],
            'due_after_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:744'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'enabled' => ['sometimes', 'boolean'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', Rule::exists('super_admins', 'id')],
        ];
    }
}
