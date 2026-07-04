<?php

namespace App\Http\Requests\Api\V1\StickyNote;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreStickyNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowUpdate->value);
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:7'],
            'position_x' => ['nullable', 'numeric'],
            'position_y' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric', 'min:50'],
            'height' => ['nullable', 'numeric', 'min:50'],
            'z_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
