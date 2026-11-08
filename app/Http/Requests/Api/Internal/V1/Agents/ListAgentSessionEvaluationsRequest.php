<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\SessionEvaluationGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAgentSessionEvaluationsRequest extends FormRequest
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
        return ['grade' => ['sometimes', Rule::enum(SessionEvaluationGrade::class)]];
    }
}
