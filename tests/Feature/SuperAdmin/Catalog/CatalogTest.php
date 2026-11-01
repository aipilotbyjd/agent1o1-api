<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\Nodes\NodeCategory;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

it('switches a connector off and on, recording why', function () {
    $connector = Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail', 'is_active' => true]);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/connectors'))->assertOk()->assertJsonPath('data.0.key', 'gmail');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/disable"), ['reason' => 'Provider outage, case 77'])
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/connectors?active=0'))->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/enable"), ['reason' => 'Provider is back'])
        ->assertOk()->assertJsonPath('data.is_active', true);

    $log = SuperAdminAuditLog::query()->where('action', 'catalog.connector.disabled')->firstOrFail();

    expect($log->before)->toBe(['is_active' => true])->and($log->reason)->toBe('Provider outage, case 77');
});

it('switches a custom node off and on', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $node = CustomNode::factory()->create(['workspace_id' => $workspace->id, 'is_active' => true]);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/nodes'))->assertOk()->assertJsonPath('data.0.workspace.name', 'Acme');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/nodes/{$node->id}/disable"), ['reason' => 'Calls a blocked host'])
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($node->fresh()->is_active)->toBeFalse();
});

it('publishes and unpublishes platform templates but never a workspace\'s own', function () {
    $platform = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $private = WorkflowTemplate::factory()->create(['workspace_id' => $workspace->id, 'visibility' => 'private']);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/workflow?platform=1'))->assertOk()->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$platform->id}/publish"), ['reason' => 'Reviewed and approved'])
        ->assertOk()->assertJsonPath('data.visibility', 'public');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$private->id}/publish"), ['reason' => 'Reviewed and approved'])
        ->assertStatus(409);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$platform->id}/unpublish"), ['reason' => 'Contains a broken step'])
        ->assertOk()->assertJsonPath('data.visibility', 'private');

    expect(SuperAdminAuditLog::query()->whereIn('action', ['catalog.template.published', 'catalog.template.unpublished'])->count())->toBe(2);
});

it('handles each template type and refuses an unknown one', function () {
    $agent = AgentTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/agent/{$agent->id}/publish"), ['reason' => 'Reviewed and approved'])->assertOk();
    $this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/spreadsheet'))->assertNotFound();
});

it('needs a reason and the catalog ability', function () {
    $connector = Connector::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/disable"))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    foreach ([SuperAdminRole::Support, SuperAdminRole::Finance, SuperAdminRole::Viewer] as $role) {
        $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('catalog/connectors'))->assertForbidden();
    }
});

it('lists app categories with the choices they can be filed under', function () {
    NodeCategory::factory()->create(['slug' => 'google_analytics', 'name' => 'Google Analytics', 'kind' => 'app', 'functions' => ['analytics'], 'vendor' => 'google']);
    NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core']);

    $response = $this->withHeaders($this->headers)->getJson(saUrl('catalog/app-categories'))->assertOk();

    expect($response->json('data.categories'))->toHaveCount(1)
        ->and($response->json('data.categories.0'))->toMatchArray(['slug' => 'google_analytics', 'functions' => ['analytics'], 'vendor' => 'google'])
        ->and(collect($response->json('data.functions'))->pluck('slug')->all())->toContain('marketing', 'cloud-hosting')->not->toContain('other')
        ->and(collect($response->json('data.vendors'))->pluck('slug')->all())->toContain('google', 'zoho');

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/app-categories?q=analytics'))->assertJsonCount(1, 'data.categories');
    $this->withHeaders($this->headers)->getJson(saUrl('catalog/app-categories?q=nothing'))->assertJsonCount(0, 'data.categories');
});

it('re-files an app category and records what changed and why', function () {
    $category = NodeCategory::factory()->create(['slug' => 'google_analytics', 'kind' => 'app', 'functions' => ['analytics'], 'vendor' => null]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/app-categories/{$category->id}"), [
        'functions' => ['analytics', 'marketing'],
        'vendor' => 'google',
        'reason' => 'Used for campaign reporting',
    ])->assertOk()->assertJsonPath('data.functions', ['analytics', 'marketing'])->assertJsonPath('data.vendor', 'google');

    $fresh = $category->fresh();

    expect($fresh->functions)->toBe(['analytics', 'marketing'])
        ->and($fresh->vendor)->toBe('google');

    $log = SuperAdminAuditLog::query()->where('action', 'catalog.app_category.updated')->firstOrFail();

    expect($log->before)->toBe(['functions' => ['analytics'], 'vendor' => null])
        ->and($log->after)->toBe(['functions' => ['analytics', 'marketing'], 'vendor' => 'google'])
        ->and($log->reason)->toBe('Used for campaign reporting');
});

it('writes nothing when an app category is filed the way it already is', function () {
    $category = NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['email'], 'vendor' => null]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/app-categories/{$category->id}"), ['functions' => ['email'], 'vendor' => null, 'reason' => 'No change needed'])->assertOk();

    expect(SuperAdminAuditLog::query()->where('action', 'catalog.app_category.updated')->count())->toBe(0);
});

it('refuses a bad filing for an app category', function (array $payload) {
    $category = NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['email']]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/app-categories/{$category->id}"), ['reason' => 'Because of a good reason', ...$payload])->assertUnprocessable();
})->with([
    'no function' => [['functions' => []]],
    'four functions' => [['functions' => ['email', 'marketing', 'crm-sales', 'support']]],
    'a repeated function' => [['functions' => ['email', 'email']]],
    'other' => [['functions' => ['other']]],
    'an unknown function' => [['functions' => ['telepathy']]],
    'an unknown vendor' => [['functions' => ['email'], 'vendor' => 'acme']],
]);

it('only files app categories, not the core ones', function () {
    $core = NodeCategory::factory()->create(['slug' => 'flow-logic', 'kind' => 'core']);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/app-categories/{$core->id}"), ['functions' => ['email'], 'reason' => 'Trying it on a core one'])->assertNotFound();
});
