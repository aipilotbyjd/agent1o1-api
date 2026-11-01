<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Customers;

use App\Services\SuperAdmin\Customers\CustomerAnnotations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The complete list of tags a record should have. Short labels: letters,
 * digits, spaces, dashes and underscores.
 */
class SyncTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>|string>
     */
    public function rules(): array
    {
        return [
            'tags' => ['present', 'array', 'max:'.CustomerAnnotations::MAX_TAGS],
            'tags.*' => ['string', 'min:2', 'max:24', 'regex:/^[A-Za-z0-9][A-Za-z0-9 _-]*$/'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return CustomerAnnotations::normaliseTags($this->validated('tags'));
    }
}
