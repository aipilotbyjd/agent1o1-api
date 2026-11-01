<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:2000']];
    }
}
