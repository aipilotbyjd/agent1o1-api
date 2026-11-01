<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkspaceModelSettingsRequest extends FormRequest
{
    /**
     * Checked before validation, so a member who can't change the settings
     * is told so rather than shown validation errors.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::WorkspaceUpdate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $publicModel = Rule::exists('model_catalog', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_internal', false));

        return [
            'default_model_catalog_id' => ['sometimes', 'nullable', 'uuid', $publicModel],
            'allowed_model_catalog_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'allowed_model_catalog_ids.*' => ['uuid', 'distinct', $publicModel],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = $this->input('allowed_model_catalog_ids');
            $default = $this->input('default_model_catalog_id');

            if (is_array($allowed) && is_string($default) && ! in_array($default, $allowed, true)) {
                $validator->errors()->add('default_model_catalog_id', 'The default model must be one of the allowed models.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowed_model_catalog_ids.min' => 'Allow at least one model, or allow all of them.',
        ];
    }
}
