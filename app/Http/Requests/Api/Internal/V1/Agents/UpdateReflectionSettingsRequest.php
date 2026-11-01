<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use App\Enums\Agents\ReflectionApplyBehavior;
use Closure;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReflectionSettingsRequest extends FormRequest
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
            'apply_behavior' => ['sometimes', Rule::enum(ReflectionApplyBehavior::class)],
            'schedule_cron' => [
                'sometimes', 'string', 'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! CronExpression::isValidExpression($value)) {
                        $fail('The schedule cron is not a valid cron expression.');
                    }
                },
            ],
            'min_chats_threshold' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'extra_instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notify_on_skip' => ['sometimes', 'boolean'],
        ];
    }
}
