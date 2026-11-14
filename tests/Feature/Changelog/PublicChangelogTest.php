<?php

use App\Enums\Changelog\ChangelogVisibility;
use App\Models\Changelog\ChangelogEntry;

it('lists only released public entries, newest first, without signing in', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Older', 'published_at' => now()->subDays(2)]);
    ChangelogEntry::factory()->published()->create(['title' => 'Newer', 'published_at' => now()->subDay()]);
    ChangelogEntry::factory()->create(['title' => 'Draft']);
    ChangelogEntry::factory()->scheduled()->create(['title' => 'Scheduled']);
    ChangelogEntry::factory()->archived()->create(['title' => 'Archived']);
    ChangelogEntry::factory()->published()->create(['title' => 'Future', 'published_at' => now()->addHour()]);
    ChangelogEntry::factory()->published()->visibleTo(ChangelogVisibility::Authenticated)->create(['title' => 'Members only']);
    ChangelogEntry::factory()->published()->visibleTo(ChangelogVisibility::Audience, ['inactive_days' => 30])->create(['title' => 'Audience only']);

    $this->getJson('/api/v1/public/changelog')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Newer')
        ->assertJsonPath('data.1.title', 'Older')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissingPath('data.0.audience')
        ->assertJsonMissingPath('data.0.created_by')
        ->assertHeader('Cache-Control');
});

it('filters by category, area and search term', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Faster runs', 'category' => 'improvement', 'areas' => ['runs']]);
    ChangelogEntry::factory()->published()->create(['title' => 'Billing fix', 'category' => 'fix', 'areas' => ['billing']]);
    ChangelogEntry::factory()->published()->pinned()->create(['title' => 'Pinned news', 'summary' => 'Read me first']);

    $this->getJson('/api/v1/public/changelog?category=fix')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Billing fix');
    $this->getJson('/api/v1/public/changelog?area=runs')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Faster runs');
    $this->getJson('/api/v1/public/changelog?q=faster')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/public/changelog?pinned=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pinned news');
    $this->getJson('/api/v1/public/changelog?category=nope')->assertUnprocessable();
});

it('treats % and _ in a search as plain characters', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Plain entry']);

    $this->getJson('/api/v1/public/changelog?q=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('shows one entry with sanitised html', function () {
    $entry = ChangelogEntry::factory()->published()->create(['slug' => 'hello', 'body' => "**hi** <script>x</script>\n\n[bad](javascript:alert(1))"]);

    $response = $this->getJson('/api/v1/public/changelog/hello')
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.category_label', 'New feature');

    expect($response->json('data.body_html'))->toContain('<strong>hi</strong>')->not->toContain('<script>')->not->toContain('javascript:');
});

it('answers 404 for anything a visitor may not read', function (string $slug) {
    ChangelogEntry::factory()->create(['slug' => 'draft-one']);
    ChangelogEntry::factory()->scheduled()->create(['slug' => 'scheduled-one']);
    ChangelogEntry::factory()->archived()->create(['slug' => 'archived-one']);
    ChangelogEntry::factory()->published()->visibleTo(ChangelogVisibility::Authenticated)->create(['slug' => 'members-one']);

    $this->getJson("/api/v1/public/changelog/{$slug}")->assertNotFound();
})->with(['draft-one', 'scheduled-one', 'archived-one', 'members-one', 'missing']);

it('serves an RSS feed of public entries only', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Shipped <fast> & well', 'slug' => 'shipped', 'version' => '3.0.0']);
    ChangelogEntry::factory()->create(['title' => 'Secret draft']);

    $response = $this->get('/api/v1/public/changelog/feed.rss')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse();
    expect(count($xml->channel->item))->toBe(1);
    expect((string) $xml->channel->item[0]->title)->toBe('3.0.0: Shipped <fast> & well');
    expect($response->getContent())->not->toContain('Secret draft');
});

it('serves a JSON feed of public entries only', function () {
    ChangelogEntry::factory()->published()->create(['title' => 'Shipped', 'slug' => 'shipped', 'category' => 'fix', 'areas' => ['api']]);
    ChangelogEntry::factory()->create(['title' => 'Secret draft']);

    $this->getJson('/api/v1/public/changelog/feed.json')
        ->assertOk()
        ->assertJsonPath('version', 'https://jsonfeed.org/version/1.1')
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.tags', ['fix', 'api']);
});
