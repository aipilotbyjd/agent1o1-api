<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\EvaluationCriterionAction;
use App\Enums\Agents\EvaluationCriterionType;
use App\Enums\Agents\EvaluationDataPointType;
use App\Models\Agents\AgentEvaluationSettings;
use App\Rules\AllowedModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentEvaluationSettingsRequest extends FormRequest
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
            'is_enabled' => ['sometimes', 'boolean'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255', new AllowedModel($this->route('workspace'), bySlug: true)],
            'sentiment_enabled' => ['sometimes', 'boolean'],
            'sentiment_affects_grade' => ['sometimes', 'boolean'],
            'sentiment_guidance' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'suggest_tags_automatically' => ['sometimes', 'boolean'],

            'criteria' => ['sometimes', 'array', 'max:'.AgentEvaluationSettings::MAX_CRITERIA],
            'criteria.*.id' => ['sometimes', 'string', 'max:100'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.prompt' => ['required', 'string', 'max:2000'],
            'criteria.*.type' => ['required', Rule::enum(EvaluationCriterionType::class)],
            'criteria.*.priority' => ['required', Rule::enum(EvaluationCriterionAction::class)],

            'tags' => ['sometimes', 'array', 'max:'.AgentEvaluationSettings::MAX_TAGS],
            'tags.*.name' => ['required', 'string', 'max:100'],
            'tags.*.description' => ['required', 'string', 'max:500'],

            'data_points' => ['sometimes', 'array', 'max:'.AgentEvaluationSettings::MAX_DATA_POINTS],
            'data_points.*.id' => ['sometimes', 'string', 'max:100'],
            'data_points.*.name' => ['required', 'string', 'max:255'],
            'data_points.*.data_type' => ['required', Rule::enum(EvaluationDataPointType::class)],
            'data_points.*.description' => ['required', 'string', 'max:2000'],
        ];
    }
}
