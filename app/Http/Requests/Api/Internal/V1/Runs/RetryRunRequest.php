<?php

namespace App\Http\Requests\Api\Internal\V1\Runs;

use Illuminate\Foundation\Http\FormRequest;

class RetryRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `from_node_key` is validated against the run's own graph in
     * `RetryRunAction` (a 409, not a 422: the key may be perfectly valid for
     * a different run), so all that's checked here is its shape.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_node_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
