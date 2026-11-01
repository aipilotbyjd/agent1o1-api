<?php

namespace Database\Factories\Broadcasts;

use App\Enums\Broadcasts\AnnouncementType;
use App\Models\Broadcasts\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AnnouncementType::Banner,
            'severity' => 'info',
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'dismissible' => true,
            'blocks_writes' => false,
            'audience' => null,
        ];
    }

    /**
     * Published and open now, with no end.
     */
    public function live(): static
    {
        return $this->state(fn () => ['published_at' => now()->subMinute(), 'starts_at' => now()->subMinute()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now(), 'starts_at' => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(fn () => ['published_at' => now()->subDays(2), 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
    }

    /**
     * A maintenance window open now that stops writes.
     */
    public function maintenance(bool $blocksWrites = true): static
    {
        return $this->state(fn () => [
            'type' => AnnouncementType::Maintenance,
            'severity' => 'warning',
            'blocks_writes' => $blocksWrites,
            'published_at' => now()->subMinute(),
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);
    }
}
