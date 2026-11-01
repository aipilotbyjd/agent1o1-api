<?php

namespace Database\Factories\Assistant;

use App\Enums\Assistant\AssistantMessageRole;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessage>
 */
class AssistantMessageFactory extends Factory
{
    protected $model = AssistantMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_session_id' => AssistantSession::factory(),
            'role' => AssistantMessageRole::User,
            'content' => fake()->sentence(),
        ];
    }

    public function fromAssistant(): static
    {
        return $this->state(fn (): array => ['role' => AssistantMessageRole::Assistant]);
    }
}
