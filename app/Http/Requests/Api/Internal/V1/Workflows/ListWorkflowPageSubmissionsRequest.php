<?php

namespace App\Http\Requests\Api\Internal\V1\Workflows;

use App\Services\Workflows\Pages\PageInsights;
use Illuminate\Foundation\Http\FormRequest;

class ListWorkflowPageSubmissionsRequest extends FormRequest
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
        return [
            'status' => ['nullable', 'in:'.implode(',', array_keys(PageInsights::STATUSES))],
            'variant' => ['nullable', 'in:a,b'],
            'include_tests' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
