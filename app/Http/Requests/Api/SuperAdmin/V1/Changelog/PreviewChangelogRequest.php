<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Changelog;

use Illuminate\Foundation\Http\FormRequest;

class PreviewChangelogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:50000']];
    }
}
