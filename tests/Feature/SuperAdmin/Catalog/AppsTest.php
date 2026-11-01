<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\CatalogNodeOverride;
use App\Models\Nodes\NodeCategory;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

function appWithConnector(string $slug = 'slack', array $category = [], array $connector = []): array
{
    return [
        NodeCategory::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'kind' => 'app', 'functions' => ['messaging'], 'icon' => $slug, 'color' => '#111111', 'sort_order' => 1, ...$category]),
        Connector::factory()->create(['key' => $slug, 'name' => ucfirst($slug), 'is_active' => true, 'is_featured' => false, 'icon' => $slug, 'color' => '#111111', 'sort_order' => 1, ...$connector]),
    ];
}

it('lists apps with their connector, who uses them and what the admin can choose from', function () {
    [$slack, $connector] = appWithConnector('slack');
    appWithConnector('gmail', ['functions' => ['email', 'productivity'], 'vendor' => 'google', 'sort_order' => 2], ['is_active' => false, 'sort_order' => 2]);
    NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core']);
    ConnectorCredential::factory()->create(['connector_id' => $connector->id, 'last_test_ok' => false]);
    ConnectorCredential::factory()->create(['connector_id' => $connector->id, 'last_test_ok' => true]);

    $response = $this->withHeaders($this->headers)->getJson(saUrl('catalog/apps'))->assertOk();
    $apps = collect($response->json('data.apps'));

    expect($apps->pluck('slug')->all())->toBe(['slack', 'gmail'])
        ->and($apps->firstWhere('slug', 'slack'))->toMatchArray([
            'functions' => ['messaging'],
            'connector' => ['id' => $connector->id, 'is_active' => true, 'is_featured' => false],
            'usage' => ['workspaces' => 2, 'credentials' => 2, 'failing_credentials' => 1],
        ])
        ->and($apps->firstWhere('slug', 'slack')['nodes_count'])->toBeGreaterThan(0)
        ->and(collect($response->json('data.functions'))->pluck('slug')->all())->toContain('marketing')->not->toContain('other')
        ->and(collect($response->json('data.vendors'))->pluck('slug')->all())->toContain('google')
        ->and($response->json('data.icons'))->toContain('slack', 'gmail');
});

it('filters apps by search, function, maker and connector state', function () {
    appWithConnector('slack');
    appWithConnector('gmail', ['functions' => ['email', 'productivity'], 'vendor' => 'google', 'sort_order' => 2], ['is_active' => false]);

    $slugs = fn (string $query) => collect($this->withHeaders($this->headers)->getJson(saUrl("catalog/apps?{$query}"))->json('data.apps'))->pluck('slug')->all();

    expect($slugs('q=mail'))->toBe(['gmail'])
        ->and($slugs('function=messaging'))->toBe(['slack'])
        ->and($slugs('function=productivity'))->toBe(['gmail'])
        ->and($slugs('vendor=google'))->toBe(['gmail'])
        ->and($slugs('state=off'))->toBe(['gmail'])
        ->and($slugs('state=on'))->toBe(['slack']);
});

it('changes only what is sent, writes it to the connector too, and audits before and after', function () {
    [$slack, $connector] = appWithConnector('slack');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), [
        'name' => 'Slack Pro',
        'color' => '#4A154B',
        'functions' => ['messaging', 'productivity'],
        'is_featured' => true,
        'reason' => 'Rebranding the tile',
    ])->assertOk()->assertJsonPath('data.name', 'Slack Pro')->assertJsonPath('data.functions', ['messaging', 'productivity'])->assertJsonPath('data.connector.is_featured', true);

    expect($slack->fresh()->only(['name', 'color', 'icon']))->toBe(['name' => 'Slack Pro', 'color' => '#4A154B', 'icon' => 'slack'])
        ->and($connector->fresh()->only(['name', 'color', 'is_featured']))->toBe(['name' => 'Slack Pro', 'color' => '#4A154B', 'is_featured' => true])
        ->and($slack->fresh()->admin_edited_fields)->toEqualCanonicalizing(['name', 'color', 'functions'])
        ->and($connector->fresh()->admin_edited_fields)->toEqualCanonicalizing(['name', 'color', 'is_featured']);

    $log = SuperAdminAuditLog::query()->where('action', 'catalog.app.updated')->firstOrFail();

    expect($log->before)->toEqual(['name' => 'Slack', 'color' => '#111111', 'functions' => ['messaging'], 'is_featured' => false])
        ->and($log->after)->toEqual(['name' => 'Slack Pro', 'color' => '#4A154B', 'functions' => ['messaging', 'productivity'], 'is_featured' => true])
        ->and($log->reason)->toBe('Rebranding the tile');
});

it('writes and audits nothing when the change is the same', function () {
    [$slack] = appWithConnector('slack');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['name' => 'Slack', 'functions' => ['messaging'], 'reason' => 'No change really'])
        ->assertOk()->assertJsonPath('message', 'Nothing to change.');

    expect(SuperAdminAuditLog::query()->where('action', 'catalog.app.updated')->count())->toBe(0)
        ->and($slack->fresh()->admin_edited_fields)->toBeNull();
});

it('keeps a status note only while the app is not operational', function () {
    [$slack] = appWithConnector('slack');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['status' => 'degraded', 'status_note' => 'Slack is slow right now', 'reason' => 'Provider incident'])
        ->assertOk()->assertJsonPath('data.status', 'degraded')->assertJsonPath('data.status_note', 'Slack is slow right now');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['status' => 'operational', 'reason' => 'Provider recovered'])
        ->assertOk()->assertJsonPath('data.status', 'operational')->assertJsonPath('data.status_note', null);
});

it('refuses a bad edit', function (array $payload) {
    [$slack] = appWithConnector('slack');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['reason' => 'A perfectly good reason', ...$payload])->assertUnprocessable();
})->with([
    'a colour that is not hex' => [['color' => 'red']],
    'an icon with spaces' => [['icon' => 'My Icon']],
    'a negative order' => [['sort_order' => -1]],
    'no functions' => [['functions' => []]],
    'four functions' => [['functions' => ['email', 'marketing', 'support', 'media']]],
    'the system function' => [['functions' => ['other']]],
    'an unknown maker' => [['vendor' => 'acme']],
    'an unknown status' => [['status' => 'on fire']],
]);

it('will not switch or feature an app that has no connector, and never touches a core category', function () {
    $app = NodeCategory::factory()->create(['slug' => 'orphan', 'kind' => 'app', 'functions' => ['email']]);
    $core = NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core']);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$app->id}"), ['is_active' => false, 'reason' => 'Switching it off'])->assertStatus(409);
    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$app->id}"), ['name' => 'Orphan app', 'reason' => 'Renaming it only'])->assertOk();
    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$core->id}"), ['name' => 'Nope', 'reason' => 'Trying a core one'])->assertNotFound();
    $this->withHeaders($this->headers)->getJson(saUrl("catalog/apps/{$core->id}"))->assertNotFound();
});

it('keeps what an admin changed when the seeders run again, and refreshes the rest', function () {
    $this->syncSetup('app-node-categories', 'connectors');
    $slack = NodeCategory::query()->where('slug', 'slack')->firstOrFail();
    $connector = Connector::query()->where('key', 'slack')->firstOrFail();
    $seededColor = $slack->color;

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['name' => 'Slack (ours)', 'functions' => ['messaging', 'developer'], 'is_featured' => ! $connector->is_featured, 'is_active' => false, 'reason' => 'Our own catalog choices'])->assertOk();

    // Someone else changes a field the admin did not touch, then the seeders run again.
    $slack->forceFill(['color' => '#000000'])->save();
    $connector->forceFill(['description' => 'stale'])->save();

    $this->syncSetup('app-node-categories', 'connectors');

    expect($slack->fresh()->name)->toBe('Slack (ours)')
        ->and($slack->fresh()->functions)->toBe(['messaging', 'developer'])
        ->and($slack->fresh()->color)->toBe($seededColor)
        ->and($connector->fresh()->name)->toBe('Slack (ours)')
        ->and($connector->fresh()->is_featured)->toBe(! $connector->is_featured)
        ->and($connector->fresh()->is_active)->toBeFalse()
        ->and($connector->fresh()->description)->not->toBe('stale');
});

it('moves an app up and down the order and refuses to go past the ends', function () {
    [$one] = appWithConnector('one', ['sort_order' => 1], ['sort_order' => 1]);
    [$two] = appWithConnector('two', ['sort_order' => 2], ['sort_order' => 2]);
    [$three] = appWithConnector('three', ['sort_order' => 3], ['sort_order' => 3]);

    $order = fn () => collect($this->withHeaders($this->headers)->getJson(saUrl('catalog/apps'))->json('data.apps'))->pluck('slug')->all();

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$three->id}/move"), ['direction' => 'up', 'reason' => 'Promote the third'])->assertOk();
    expect($order())->toBe(['one', 'three', 'two']);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$one->id}/move"), ['direction' => 'up', 'reason' => 'Already first'])->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$two->id}/move"), ['direction' => 'down', 'reason' => 'Already last'])->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$one->id}/move"), ['direction' => 'sideways', 'reason' => 'Not a direction'])->assertUnprocessable();
});

it('shows what switching an app off would touch', function () {
    [$slack, $connector] = appWithConnector('slack');
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Acme']);
    ConnectorCredential::factory()->create(['connector_id' => $connector->id, 'workspace_id' => $workspace->id, 'last_test_ok' => false]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    foreach (['slack_post_message', 'slack_post_message', 'webhook_trigger'] as $type) {
        DB::table('workflow_nodes')->insert(['id' => (string) Str::uuid(), 'workflow_id' => $workflow->id, 'key' => Str::random(8), 'type' => $type, 'config' => '{}', 'position' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    $impact = $this->withHeaders($this->headers)->getJson(saUrl("catalog/apps/{$slack->id}/impact"))->assertOk()->json('data');

    expect($impact)->toMatchArray(['workspaces' => 1, 'credentials' => 1, 'failing_credentials' => 1, 'workflows' => 1, 'runs_30d' => 0])
        ->and($impact['node_types'])->toBeGreaterThan(5);
});

it('lists an app\'s history and puts it back as it was before a change', function () {
    [$slack, $connector] = appWithConnector('slack');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}"), ['name' => 'Wrong name', 'is_active' => false, 'reason' => 'A mistaken edit'])->assertOk();

    $history = $this->withHeaders($this->headers)->getJson(saUrl("catalog/apps/{$slack->id}/history"))->assertOk()->json('data');

    expect($history)->toHaveCount(1)
        ->and($history[0])->toMatchArray(['action' => 'catalog.app.updated', 'reason' => 'A mistaken edit'])
        ->and($history[0]['before'])->toBe(['name' => 'Slack', 'is_active' => true]);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$slack->id}/revert/{$history[0]['id']}"), ['reason' => 'It was a mistake'])->assertOk()->assertJsonPath('message', 'Change reverted.');

    expect($slack->fresh()->name)->toBe('Slack')->and($connector->fresh()->is_active)->toBeTrue()
        ->and(SuperAdminAuditLog::query()->where('action', 'catalog.app.reverted')->count())->toBe(1);

    // A change that was itself a revert is not offered again.
    $log = SuperAdminAuditLog::query()->where('action', 'catalog.app.reverted')->firstOrFail();
    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$slack->id}/revert/{$log->id}"), ['reason' => 'Revert the revert'])->assertNotFound();
});

it('will not revert another app\'s change', function () {
    [$slack] = appWithConnector('slack');
    [$gmail] = appWithConnector('gmail', ['sort_order' => 2]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$gmail->id}"), ['name' => 'Gmail 2', 'reason' => 'Renaming Gmail'])->assertOk();
    $log = SuperAdminAuditLog::query()->where('action', 'catalog.app.updated')->firstOrFail();

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/apps/{$slack->id}/revert/{$log->id}"), ['reason' => 'Wrong app on purpose'])->assertNotFound();
});

it('applies one change to many apps under one reason and says what it skipped', function () {
    [$slack] = appWithConnector('slack');
    [$gmail] = appWithConnector('gmail', ['sort_order' => 2]);
    $orphan = NodeCategory::factory()->create(['slug' => 'orphan', 'kind' => 'app', 'functions' => ['email']]);
    $ids = [$slack->id, $gmail->id, $orphan->id];

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/apps/bulk'), ['ids' => $ids, 'action' => 'set_vendor', 'value' => 'google', 'reason' => 'They are all Google'])
        ->assertOk()->assertJsonPath('data.updated', 3);
    expect(NodeCategory::query()->whereIn('id', $ids)->where('vendor', 'google')->count())->toBe(3);

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/apps/bulk'), ['ids' => $ids, 'action' => 'switch_off', 'reason' => 'Switching these off'])
        ->assertOk()->assertJsonPath('data.updated', 2)->assertJsonPath('data.skipped', ['orphan']);
    expect(Connector::query()->where('is_active', false)->count())->toBe(2);

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/apps/bulk'), ['ids' => $ids, 'action' => 'set_vendor', 'value' => 'google', 'reason' => 'Same again'])
        ->assertOk()->assertJsonPath('data.updated', 0)->assertJsonPath('data.unchanged', 3);

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/apps/bulk'), ['ids' => [$slack->id], 'action' => 'set_functions', 'value' => ['email', 'support'], 'reason' => 'Refile Slack'])->assertOk();
    expect($slack->fresh()->functions)->toBe(['email', 'support']);

    expect(SuperAdminAuditLog::query()->where('action', 'catalog.app.updated')->where('reason', 'They are all Google')->count())->toBe(3);
});

it('refuses a bad bulk request', function (array $payload) {
    [$slack] = appWithConnector('slack');

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/apps/bulk'), ['ids' => [$slack->id], 'reason' => 'A perfectly good reason', ...$payload])->assertUnprocessable();
})->with([
    'an unknown action' => [['action' => 'delete_everything']],
    'a maker that does not exist' => [['action' => 'set_vendor', 'value' => 'acme']],
    'no functions' => [['action' => 'set_functions', 'value' => []]],
    'too many functions' => [['action' => 'set_functions', 'value' => ['email', 'marketing', 'support', 'media']]],
    'no ids' => [['action' => 'switch_off', 'ids' => []]],
]);

it('lists an app\'s nodes and flags one as beta, deprecated or hidden, or clears the flag', function () {
    [$slack] = appWithConnector('slack');

    $nodes = collect($this->withHeaders($this->headers)->getJson(saUrl("catalog/apps/{$slack->id}/nodes"))->assertOk()->json('data'));
    expect($nodes->pluck('type'))->toContain('slack_post_message')->and($nodes->firstWhere('type', 'slack_post_message'))->toMatchArray(['status' => null, 'can_start_flow' => false]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}/nodes/slack_post_message"), ['status' => 'beta', 'note' => 'Threads are still rough', 'reason' => 'New code path'])->assertOk();

    $entry = collect(app(NodeRegistry::class)->catalog())->firstWhere('type', 'slack_post_message');
    expect($entry)->toMatchArray(['status' => 'beta', 'status_note' => 'Threads are still rough', 'listed' => true]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}/nodes/slack_post_message"), ['status' => 'hidden', 'reason' => 'Pulling it for now'])->assertOk();
    expect(collect(app(NodeRegistry::class)->catalog())->firstWhere('type', 'slack_post_message'))->toMatchArray(['status' => 'hidden', 'listed' => false]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}/nodes/slack_post_message"), ['status' => null, 'reason' => 'Fixed, putting it back'])->assertOk();
    expect(collect(app(NodeRegistry::class)->catalog())->firstWhere('type', 'slack_post_message'))->toMatchArray(['status' => null, 'listed' => true])
        ->and(CatalogNodeOverride::query()->count())->toBe(0)
        ->and(SuperAdminAuditLog::query()->where('action', 'catalog.node.status_changed')->count())->toBe(3);
});

it('hides a flagged node from the Triggers list and refuses a node from another app', function () {
    [$slack] = appWithConnector('slack');
    CatalogNodeOverride::factory()->create(['node_type' => 'slack_get_channel_history', 'status' => 'hidden']);

    expect(collect(app(NodeRegistry::class)->triggerCatalog())->pluck('type'))->not->toContain('slack_get_channel_history');

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$slack->id}/nodes/gmail_send_email"), ['status' => 'beta', 'reason' => 'Not a Slack node'])->assertNotFound();
});

it('keeps the apps endpoints away from roles without catalog.manage', function () {
    $viewer = superAdmin(SuperAdminRole::Viewer);
    [$slack] = appWithConnector('slack');

    $this->withHeaders(superAdminHeaders($viewer))->getJson(saUrl('catalog/apps'))->assertForbidden();
    $this->withHeaders(superAdminHeaders($viewer))->putJson(saUrl("catalog/apps/{$slack->id}"), ['name' => 'X', 'reason' => 'Not allowed to'])->assertForbidden();
});
