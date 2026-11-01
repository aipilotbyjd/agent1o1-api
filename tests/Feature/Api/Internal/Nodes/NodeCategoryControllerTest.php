<?php

use App\Models\Nodes\CatalogFunction;
use App\Models\Nodes\NodeCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

it('lists node categories ordered by sort_order', function () {
    Passport::actingAs(User::factory()->create());

    NodeCategory::factory()->create(['name' => 'Second', 'slug' => 'second', 'sort_order' => 2]);
    NodeCategory::factory()->create(['name' => 'First', 'slug' => 'first', 'sort_order' => 1]);

    $response = $this->getJson('/api/v1/node-categories');

    $response->assertOk();
    expect($response->json('data.categories.*.slug'))->toBe(['first', 'second']);
});

it('requires authentication to list node categories', function () {
    $this->getJson('/api/v1/node-categories')->assertUnauthorized();
});

it('shows a single category with its builtin nodes', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'slack']);

    $response = $this->getJson("/api/v1/node-categories/{$category->id}");

    $response->assertOk();
    expect($response->json('data.category.slug'))->toBe('slack');
    expect($response->json('data.nodes_count'))->toBe(count(glob(app_path('Nodes/Integrations/Slack/Slack*Node.php'))));
    expect(collect($response->json('data.nodes'))->pluck('type'))->toContain('slack_post_message');
});

it('lists every node that can start a flow under Triggers & Events', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'triggers-events']);

    $nodes = collect($this->getJson("/api/v1/node-categories/{$category->id}")->assertOk()->json('data.nodes'));

    expect($nodes->pluck('type'))->toContain('webhook_trigger', 'schedule_trigger', 'gmail_list_messages', 'slack_get_channel_history', 'google_sheets_get_values')
        ->and($nodes->firstWhere('type', 'gmail_list_messages'))->toMatchArray(['name' => 'Gmail: New email', 'category' => 'gmail', 'trigger_mode' => true])
        ->and($nodes->firstWhere('type', 'slack_post_message'))->toBeNull();
});

it('does not count the retired manual trigger in Triggers & Events', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'triggers-events']);

    $response = $this->getJson("/api/v1/node-categories/{$category->id}")->assertOk();

    expect($response->json('data.nodes_count'))->toBe(collect($response->json('data.nodes'))->where('listed', true)->count())
        ->and(collect($response->json('data.nodes'))->firstWhere('type', 'manual_trigger')['listed'])->toBeFalse();
});

it('keeps the app readers listed under their own app too', function () {
    Passport::actingAs(User::factory()->create());

    $category = NodeCategory::factory()->create(['slug' => 'gmail']);

    $reader = collect($this->getJson("/api/v1/node-categories/{$category->id}")->assertOk()->json('data.nodes'))
        ->firstWhere('type', 'gmail_list_messages');

    expect($reader['name'])->toBe('Gmail: List Messages')
        ->and($reader)->not->toHaveKey('trigger_mode');
});

it('lists the functions an app is used for, the primary first, and who makes it', function () {
    Passport::actingAs(User::factory()->create());

    NodeCategory::factory()->create([
        'slug' => 'google_analytics',
        'kind' => 'app',
        'functions' => ['analytics', 'marketing'],
        'vendor' => 'google',
    ]);

    $category = collect($this->getJson('/api/v1/node-categories')->assertOk()->json('data.categories'))->firstWhere('slug', 'google_analytics');

    expect($category['function'])->toBe('analytics')
        ->and(collect($category['functions'])->pluck('slug')->all())->toBe(['analytics', 'marketing'])
        ->and($category['functions'][1])->toMatchArray(['label' => 'Marketing', 'order' => CatalogFunction::query()->where('slug', 'marketing')->value('sort_order')])
        ->and($category['vendor'])->toBe(['slug' => 'google', 'label' => 'Google']);
});

it('shows an app with no function under Other apps and leaves core categories out of the taxonomy', function () {
    Passport::actingAs(User::factory()->create());

    NodeCategory::factory()->create(['slug' => 'brand-new-app', 'kind' => 'app', 'sort_order' => 2]);
    NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core', 'sort_order' => 1]);

    $categories = collect($this->getJson('/api/v1/node-categories')->assertOk()->json('data.categories'));

    expect($categories->firstWhere('slug', 'brand-new-app'))->toMatchArray(['function' => 'other', 'vendor' => null])
        ->and($categories->firstWhere('slug', 'brand-new-app')['functions'][0]['label'])->toBe('Other apps')
        ->and($categories->firstWhere('slug', 'flow-logic'))->toMatchArray(['function' => null, 'functions' => [], 'vendor' => null]);
});

it('seeds every integration with one to three real functions', function () {
    $this->syncSetup('app-node-categories');

    $apps = NodeCategory::query()->where('kind', 'app')->get();

    $known = CatalogFunction::query()->where('is_system', false)->pluck('slug')->all();

    expect($apps)->not->toBeEmpty()
        ->and($apps->filter(fn (NodeCategory $app): bool => ($app->functions ?? []) === [] || count($app->functions) > 3 || array_diff($app->functions, $known) !== [])->pluck('slug')->all())->toBe([])
        ->and($apps->filter(fn (NodeCategory $app): bool => count(array_unique($app->functions)) !== count($app->functions))->pluck('slug')->all())->toBe([]);
});

it('cross-lists Google Analytics under Marketing and gathers a vendor family', function () {
    $this->syncSetup('app-node-categories');

    $analytics = NodeCategory::query()->where('slug', 'google_analytics')->firstOrFail();

    expect($analytics->functions)->toBe(['analytics', 'marketing'])
        ->and(NodeCategory::query()->where('vendor', 'google')->count())->toBeGreaterThan(1);
});

it('pins an app for the signed-in user and shows it as pinned in the list', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);
    $app = NodeCategory::factory()->create(['slug' => 'slack', 'kind' => 'app']);

    expect(collect($this->getJson('/api/v1/node-categories')->json('data.categories'))->firstWhere('slug', 'slack')['is_pinned'])->toBeFalse();

    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => true])->assertOk()->assertJsonPath('data.is_pinned', true);

    expect(collect($this->getJson('/api/v1/node-categories')->json('data.categories'))->firstWhere('slug', 'slack')['is_pinned'])->toBeTrue()
        ->and($this->getJson("/api/v1/node-categories/{$app->id}")->json('data.category.is_pinned'))->toBeTrue()
        ->and($user->pinnedNodeCategories()->pluck('slug')->all())->toBe(['slack']);

    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => false])->assertOk()->assertJsonPath('data.is_pinned', false);

    expect($user->pinnedNodeCategories()->count())->toBe(0);
});

it('keeps a pin on the account, not on one browser, and private to that user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $app = NodeCategory::factory()->create(['slug' => 'gmail', 'kind' => 'app']);

    Passport::actingAs($owner);
    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => true])->assertOk();

    // Same account, a fresh session elsewhere: still pinned.
    Passport::actingAs($owner);
    expect(collect($this->getJson('/api/v1/node-categories')->json('data.categories'))->firstWhere('slug', 'gmail')['is_pinned'])->toBeTrue();

    // Someone else does not see it.
    Passport::actingAs($other);
    expect(collect($this->getJson('/api/v1/node-categories')->json('data.categories'))->firstWhere('slug', 'gmail')['is_pinned'])->toBeFalse();
});

it('pinning twice keeps one pin and unpinning something never pinned is harmless', function () {
    $user = User::factory()->create();
    Passport::actingAs($user);
    $app = NodeCategory::factory()->create(['kind' => 'app']);

    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => true])->assertOk();
    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => true])->assertOk();
    expect($user->pinnedNodeCategories()->count())->toBe(1);

    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => false])->assertOk();
    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => false])->assertOk();
    expect($user->pinnedNodeCategories()->count())->toBe(0);
});

it('only lets apps be pinned and needs a signed-in user', function () {
    $core = NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core']);
    $app = NodeCategory::factory()->create(['kind' => 'app']);

    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", ['is_pinned' => true])->assertUnauthorized();

    Passport::actingAs(User::factory()->create());

    $this->patchJson("/api/v1/node-categories/{$core->id}/pin", ['is_pinned' => true])->assertNotFound();
    $this->patchJson("/api/v1/node-categories/{$app->id}/pin", [])->assertUnprocessable();
});

it('drops a user pins when the user or the app is deleted', function () {
    $user = User::factory()->create();
    $app = NodeCategory::factory()->create(['kind' => 'app']);
    $user->pinnedNodeCategories()->attach($app);

    $app->delete();

    expect($user->pinnedNodeCategories()->count())->toBe(0)
        ->and(DB::table('node_category_pins')->count())->toBe(0);
});
