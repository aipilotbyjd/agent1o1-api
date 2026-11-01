<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Enums\Ai\ModelSpeed;
use Illuminate\Validation\Rule;

/**
 * The descriptive fields of a catalog entry, shared by creating and editing
 * one: what the model picker shows about it.
 */
final class ModelFields
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'display_name' => [$presence, 'string', 'max:120'],
            'brand' => [$presence, 'string', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'strengths' => ['sometimes', 'nullable', 'array', 'max:6'],
            'strengths.*' => ['string', 'max:40'],
            'speed' => ['sometimes', 'nullable', Rule::enum(ModelSpeed::class)],
            'cost_tier' => ['sometimes', 'nullable', 'integer', 'between:1,3'],
            'capabilities' => ['sometimes', 'nullable', 'array:context_window,tool_use,vision,reasoning'],
            'capabilities.context_window' => ['integer', 'min:1000', 'max:100000000'],
            'capabilities.tool_use' => ['boolean'],
            'capabilities.vision' => ['boolean'],
            'capabilities.reasoning' => ['boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
            'min_plan_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('plans', 'id')],
        ];
    }
}
