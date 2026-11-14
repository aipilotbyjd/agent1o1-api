<?php

namespace Database\Factories\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Enums\Changelog\ChangelogStatus;
use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Changelog\ChangelogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChangelogEntry>
 */
class ChangelogEntryFactory extends Factory
{
    protected $model = ChangelogEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'version' => null,
            'title' => $title,
            'summary' => fake()->sentence(),
            'body' => "### Added\n\n- ".fake()->sentence()."\n\n### Fixed\n\n- ".fake()->sentence(),
            'category' => ChangelogCategory::Feature,
            'areas' => ['workflows'],
            'visibility' => ChangelogVisibility::Public,
            'audience' => null,
            'is_pinned' => false,
            'is_major' => false,
            'status' => ChangelogStatus::Draft,
        ];
    }

    /**
     * Published and out since a minute ago.
     */
    public function published(): static
    {
        return $this->state(fn () => ['status' => ChangelogStatus::Published, 'published_at' => now()->subMinute()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => ChangelogStatus::Scheduled, 'scheduled_for' => now()->addDay()]);
    }

    public function due(): static
    {
        return $this->state(fn () => ['status' => ChangelogStatus::Scheduled, 'scheduled_for' => now()->subMinute()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ChangelogStatus::Archived, 'published_at' => now()->subWeek()]);
    }

    public function visibleTo(ChangelogVisibility $visibility, ?array $audience = null): static
    {
        return $this->state(fn () => ['visibility' => $visibility, 'audience' => $audience]);
    }

    public function major(): static
    {
        return $this->state(fn () => ['is_major' => true]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
