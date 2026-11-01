<?php

namespace Database\Factories\Assistant;

use App\Enums\Assistant\AssistantTurnStatus;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantTurn>
 */
class AssistantTurnFactory extends Factory
{
    protected $model = AssistantTurn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_session_id' => AssistantSession::factory(),
            'status' => AssistantTurnStatus::Queued,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['status' => AssistantTurnStatus::Running, 'started_at' => now()]);
    }
}
