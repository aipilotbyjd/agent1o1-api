<?php

namespace App\Http\Requests\Api\V1\Folder;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'uuid', 'exists:folders,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
