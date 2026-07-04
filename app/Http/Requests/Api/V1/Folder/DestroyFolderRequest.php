<?php

namespace App\Http\Requests\Api\V1\Folder;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class DestroyFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowDelete->value);
    }

    public function rules(): array
    {
        return [];
    }
}
