<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\CatalogFunction;
use App\Models\Nodes\CatalogVendor;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

function healthIssue(array $report, string $code, ?string $slug = null): ?array
{
    return collect($report['issues'])->first(fn (array $issue): bool => $issue['code'] === $code && ($slug === null || ($issue['app']['slug'] ?? null) === $slug));
}

it('finds what is wrong in the catalog', function () {
    NodeCategory::factory()->create(['slug' => 'slack', 'name' => 'Slack', 'kind' => 'app', 'description' => 'Post messages', 'icon' => 'shared', 'functions' => ['messaging']]);
    NodeCategory::factory()->create(['slug' => 'gmail', 'name' => 'Gmail', 'kind' => 'app', 'description' => null, 'icon' => 'shared', 'functions' => ['other'], 'status' => 'degraded', 'status_note' => 'Slow']);
    NodeCategory::factory()->create(['slug' => 'ghost', 'name' => 'Ghost', 'kind' => 'app', 'description' => 'Has no nodes', 'icon' => 'ghost', 'functions' => ['email']]);
    $slack = Connector::factory()->create(['key' => 'slack']);
    Connector::factory()->create(['key' => 'gmail', 'is_active' => false, 'is_featured' => true]);
    Connector::factory()->create(['key' => 'stray', 'name' => 'Stray']);
    ConnectorCredential::factory()->create(['connector_id' => $slack->id, 'last_test_ok' => false]);

    $report = $this->withHeaders($this->headers)->getJson(saUrl('catalog/health'))->assertOk()->json('data');

    expect(healthIssue($report, 'no_description', 'gmail'))->not->toBeNull()
        ->and(healthIssue($report, 'no_description', 'slack'))->toBeNull()
        ->and(healthIssue($report, 'no_function', 'gmail'))->not->toBeNull()
        ->and(healthIssue($report, 'no_nodes', 'ghost'))->not->toBeNull()
        ->and(healthIssue($report, 'off_but_featured', 'gmail'))->not->toBeNull()
        ->and(healthIssue($report, 'failing_credentials', 'slack')['message'])->toBe('One saved account fails its connection test.')
        ->and(healthIssue($report, 'duplicate_icon')['message'])->toContain('shared', 'Slack', 'Gmail')
        ->and(healthIssue($report, 'status_set', 'gmail')['message'])->toContain('degraded', 'Slow')
        ->and(healthIssue($report, 'connector_without_app')['message'])->toContain('Stray')
        ->and(healthIssue($report, 'unused', 'slack'))->not->toBeNull()
        ->and($report['summary']['apps'])->toBe(3)
        ->and($report['summary']['warnings'])->toBeGreaterThan(0)
        ->and(collect($report['issues'])->first()['severity'])->toBe('warning');
});

it('does not call an app unused once one of its nodes has run', function () {
    NodeCategory::factory()->create(['slug' => 'slack', 'kind' => 'app', 'description' => 'Post', 'functions' => ['messaging']]);
    Connector::factory()->create(['key' => 'slack']);

    expect(healthIssue($this->withHeaders($this->headers)->getJson(saUrl('catalog/health'))->json('data'), 'unused', 'slack'))->not->toBeNull();

    $run = Run::factory()->create();
    NodeRun::factory()->create(['run_id' => $run->id, 'type' => 'slack_post_message']);

    expect(healthIssue($this->withHeaders($this->headers)->getJson(saUrl('catalog/health'))->json('data'), 'unused', 'slack'))->toBeNull();
});

it('lists the taxonomy with how many apps use each entry', function () {
    NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['email', 'marketing'], 'vendor' => 'google']);
    NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['email'], 'vendor' => 'google']);

    $data = $this->withHeaders($this->headers)->getJson(saUrl('catalog/taxonomy'))->assertOk()->json('data');
    $email = collect($data['functions'])->firstWhere('slug', 'email');

    expect($email['apps_count'])->toBe(2)
        ->and(collect($data['functions'])->firstWhere('slug', 'marketing')['apps_count'])->toBe(1)
        ->and(collect($data['functions'])->firstWhere('slug', 'other')['is_system'])->toBeTrue()
        ->and(collect($data['vendors'])->firstWhere('slug', 'google')['apps_count'])->toBe(2);
});

it('adds, renames and reorders a function, and a new one is usable at once', function () {
    $created = $this->withHeaders($this->headers)->postJson(saUrl('catalog/taxonomy/functions'), ['slug' => 'ai-tools', 'label' => 'AI tools', 'reason' => 'We are adding AI apps'])
        ->assertCreated()->json('data');

    $app = NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['email']]);
    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$app->id}"), ['functions' => ['ai-tools'], 'reason' => 'It is an AI app'])->assertOk();

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/taxonomy/functions/{$created['id']}"), ['label' => 'AI & agents', 'sort_order' => 5, 'reason' => 'Better name'])->assertOk()->assertJsonPath('data.label', 'AI & agents');

    $category = collect($this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('catalog/apps'))->json('data.apps'))->firstWhere('id', $app->id);
    expect($category['functions'])->toBe(['ai-tools'])
        ->and(CatalogFunction::query()->where('slug', 'ai-tools')->value('label'))->toBe('AI & agents')
        ->and(SuperAdminAuditLog::query()->whereIn('action', ['catalog.function.created', 'catalog.function.updated'])->count())->toBe(2);
});

it('adds a vendor and shows it on the app', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/taxonomy/vendors'), ['slug' => 'amazon', 'label' => 'Amazon', 'reason' => 'AWS apps are coming'])->assertCreated();
    $app = NodeCategory::factory()->create(['kind' => 'app', 'functions' => ['storage']]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/apps/{$app->id}"), ['vendor' => 'amazon', 'reason' => 'Made by Amazon'])->assertOk();

    $row = collect($this->withHeaders($this->headers)->getJson(saUrl('catalog/apps'))->json('data.apps'))->firstWhere('id', $app->id);

    expect($row['vendor'])->toBe('amazon')
        ->and(CatalogVendor::query()->where('slug', 'amazon')->exists())->toBeTrue()
        ->and(collect($this->withHeaders($this->headers)->getJson(saUrl('catalog/taxonomy'))->json('data.vendors'))->firstWhere('slug', 'amazon')['apps_count'])->toBe(1);
});

it('refuses a bad taxonomy entry', function (array $payload) {
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/taxonomy/functions'), ['reason' => 'A perfectly good reason', ...$payload])->assertUnprocessable();
})->with([
    'a slug with capitals' => [['slug' => 'Bad Slug', 'label' => 'Bad']],
    'a slug that already exists' => [['slug' => 'email', 'label' => 'Email again']],
    'no label' => [['slug' => 'fine-slug', 'label' => '']],
    'no slug' => [['label' => 'No slug']],
]);

it('will not remove a function or vendor an app still uses, nor the built-in one', function () {
    $email = CatalogFunction::query()->where('slug', 'email')->firstOrFail();
    $other = CatalogFunction::query()->where('slug', 'other')->firstOrFail();
    $google = CatalogVendor::query()->where('slug', 'google')->firstOrFail();
    NodeCategory::factory()->create(['name' => 'Gmail', 'kind' => 'app', 'functions' => ['email'], 'vendor' => 'google']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("catalog/taxonomy/functions/{$email->id}"), ['reason' => 'Trying to remove it'])->assertStatus(409)->assertJsonPath('message', fn (string $message) => str_contains($message, 'Gmail'));
    $this->withHeaders($this->headers)->deleteJson(saUrl("catalog/taxonomy/vendors/{$google->id}"), ['reason' => 'Trying to remove it'])->assertStatus(409);
    $this->withHeaders($this->headers)->deleteJson(saUrl("catalog/taxonomy/functions/{$other->id}"), ['reason' => 'Trying to remove it'])->assertStatus(409);
    $this->withHeaders($this->headers)->putJson(saUrl("catalog/taxonomy/functions/{$other->id}"), ['label' => 'Renamed', 'reason' => 'Trying to rename'])->assertStatus(409);

    expect(CatalogFunction::query()->whereIn('slug', ['email', 'other'])->count())->toBe(2);
});

it('removes an unused function and records it', function () {
    $unused = CatalogFunction::factory()->create(['slug' => 'spare', 'label' => 'Spare']);

    $this->withHeaders($this->headers)->deleteJson(saUrl("catalog/taxonomy/functions/{$unused->id}"), ['reason' => 'Nothing uses it'])->assertOk();

    expect(CatalogFunction::query()->where('slug', 'spare')->exists())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->where('action', 'catalog.function.deleted')->firstOrFail()->before)->toMatchArray(['slug' => 'spare']);
});

it('features a template and orders the gallery, featured first', function () {
    $a = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'public', 'name' => 'A', 'sort_order' => 0]);
    $b = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'public', 'name' => 'B', 'sort_order' => 0]);
    $c = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'public', 'name' => 'C', 'sort_order' => 0]);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/templates/workflow/{$c->id}/curation"), ['is_featured' => true, 'sort_order' => 2, 'reason' => 'Our best starter'])->assertOk()->assertJsonPath('data.is_featured', true);
    $this->withHeaders($this->headers)->putJson(saUrl("catalog/templates/workflow/{$a->id}/curation"), ['is_featured' => true, 'sort_order' => 1, 'reason' => 'Our second best'])->assertOk();

    $names = collect($this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/workflow'))->json('data'))->pluck('name')->all();

    expect($names[0])->toBe('A')->and($names[1])->toBe('C')
        ->and(SuperAdminAuditLog::query()->where('action', 'catalog.template.curated')->count())->toBe(2);

    $this->withHeaders($this->headers)->putJson(saUrl("catalog/templates/workflow/{$a->id}/curation"), ['is_featured' => true, 'sort_order' => 1, 'reason' => 'No real change'])->assertOk();
    expect(SuperAdminAuditLog::query()->where('action', 'catalog.template.curated')->count())->toBe(2);
});

it('shows how often a template was used', function () {
    $template = AgentTemplate::factory()->create(['workspace_id' => null, 'usage_count' => 7]);

    expect(collect($this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/agent'))->json('data'))->firstWhere('id', $template->id)['usage_count'])->toBe(7);
});

it('publishes, unpublishes, features and unfeatures many templates at once', function () {
    $one = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);
    $two = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $theirs = WorkflowTemplate::factory()->create(['workspace_id' => $workspace->id, 'visibility' => 'private']);
    $ids = [$one->id, $two->id, $theirs->id];

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/workflow/bulk'), ['ids' => $ids, 'action' => 'publish', 'reason' => 'Reviewed together'])
        ->assertOk()->assertJsonPath('data.updated', 2)->assertJsonPath('data.skipped', 1);
    expect(WorkflowTemplate::query()->where('visibility', 'public')->count())->toBe(2);

    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/workflow/bulk'), ['ids' => $ids, 'action' => 'feature', 'reason' => 'Featuring them'])->assertOk()->assertJsonPath('data.updated', 3);
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/workflow/bulk'), ['ids' => $ids, 'action' => 'unfeature', 'reason' => 'Unfeaturing them'])->assertOk()->assertJsonPath('data.updated', 3);
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/workflow/bulk'), ['ids' => $ids, 'action' => 'unpublish', 'reason' => 'Pulling them'])->assertOk()->assertJsonPath('data.updated', 2);
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/workflow/bulk'), ['ids' => $ids, 'action' => 'teleport', 'reason' => 'Not an action'])->assertUnprocessable();
    $this->withHeaders($this->headers)->postJson(saUrl('catalog/templates/robot/bulk'), ['ids' => $ids, 'action' => 'publish', 'reason' => 'Unknown type'])->assertNotFound();
});

it('keeps taxonomy, health and curation away from roles without catalog.manage', function () {
    $viewer = superAdminHeaders(superAdmin(SuperAdminRole::Viewer));
    $template = WorkflowTemplate::factory()->create(['workspace_id' => null]);

    $this->withHeaders($viewer)->getJson(saUrl('catalog/health'))->assertForbidden();
    $this->withHeaders($viewer)->getJson(saUrl('catalog/taxonomy'))->assertForbidden();
    $this->withHeaders($viewer)->postJson(saUrl('catalog/taxonomy/functions'), ['slug' => 'x-y', 'label' => 'X', 'reason' => 'Not allowed to'])->assertForbidden();
    $this->withHeaders($viewer)->putJson(saUrl("catalog/templates/workflow/{$template->id}/curation"), ['is_featured' => true, 'reason' => 'Not allowed to'])->assertForbidden();
});
