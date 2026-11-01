<?php

namespace Database\Factories\Assistant;

use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantTurn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantAction>
 */
class AssistantActionFactory extends Factory
{
    protected $model = AssistantAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_turn_id' => AssistantTurn::factory(),
            'assistant_session_id' => fn (array $attributes) => AssistantTurn::query()->findOrFail($attributes['assistant_turn_id'])->assistant_session_id,
            'tool_call_id' => 'call_'.Str::random(10),
            'tool' => 'send_note',
            'arguments' => ['text' => 'hello'],
            'effect' => AssistantToolEffect::External,
            'status' => AssistantActionStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }
}
