<?php

namespace Database\Seeders\Changelog;

use App\Enums\Changelog\ChangelogCategory;
use App\Enums\Changelog\ChangelogStatus;
use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Changelog\ChangelogEntry;
use Illuminate\Database\Seeder;

/**
 * A few published sample entries, for a fresh local or staging environment.
 * Not part of `DatabaseSeeder`: production starts with an empty changelog.
 * Run with `php artisan db:seed --class='Database\Seeders\Changelog\ChangelogEntrySeeder'`.
 * Safe to run twice: entries are matched by slug.
 */
class ChangelogEntrySeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'slug' => 'welcome-to-the-changelog',
                'title' => 'Welcome to the changelog',
                'summary' => 'Every new feature, improvement and fix now has a home. Follow along here or by RSS.',
                'body' => "From now on, release notes live here.\n\n### What you will find\n\n- New features and improvements, as they ship\n- Fixes worth knowing about\n- Heads-up on anything that changes how you work\n\nYou can filter by category or area, and subscribe with the RSS feed.",
                'category' => ChangelogCategory::Feature,
                'areas' => ['platform'],
                'is_major' => true,
                'is_pinned' => true,
                'published_at' => now()->subDays(1),
            ],
            [
                'slug' => 'faster-workflow-runs',
                'title' => 'Faster workflow runs',
                'summary' => 'Runs now start sooner and finish quicker, especially for workflows with many steps.',
                'body' => "### Improved\n\n- Runs start up to 40% sooner\n- Long workflows keep their place in the queue under load",
                'category' => ChangelogCategory::Improvement,
                'areas' => ['workflows', 'runs'],
                'published_at' => now()->subDays(6),
            ],
            [
                'slug' => 'billing-page-fixes',
                'title' => 'Billing page fixes',
                'summary' => 'Invoices now load reliably, and the usage chart shows the right billing period.',
                'body' => "### Fixed\n\n- Invoices sometimes failed to load on slow connections\n- The usage chart showed the previous period after a plan change",
                'category' => ChangelogCategory::Fix,
                'areas' => ['billing'],
                'published_at' => now()->subDays(12),
            ],
        ];

        foreach ($entries as $entry) {
            ChangelogEntry::query()->firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    ...$entry,
                    'visibility' => ChangelogVisibility::Public,
                    'status' => ChangelogStatus::Published,
                ],
            );
        }
    }
}
