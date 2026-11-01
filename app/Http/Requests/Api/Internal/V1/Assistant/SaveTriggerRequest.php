<?php

namespace App\Http\Requests\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantTriggerType;
use App\Rules\Timezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Schedule and timezone rules live in `TriggerDefinitions`, shared with
     * the assistant's own tools.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::enum(AssistantTriggerType::class)],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'prompt' => [$creating ? 'required' : 'sometimes', 'string', 'max:'.config('assistant.triggers.prompt_max_chars')],
            'cron' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'nullable', 'string', new Timezone],
            'run_at' => ['sometimes', 'nullable', 'date'],
            'status' => [$creating ? 'prohibited' : 'sometimes', Rule::in(['active', 'paused'])],
        ];
    }
}
