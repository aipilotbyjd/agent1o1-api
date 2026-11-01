<?php

use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workflow, 1: User, 2: Workspace}
 */
function interfaceWorkflow(array $nodes, bool $publish = true): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => []]);

    if ($publish) {
        $workflow->publishVersion(publisher: $owner);
    }

    return [$workflow->fresh(), $owner, $workspace];
}

it('derives an interface from the graphs input references', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => [
            'name' => 'input.customer_name',
        ]]],
        ['key' => 'b', 'type' => 'call_api', 'config' => [
            'method' => 'GET',
            'url' => '{{ input.endpoint }}',
            'headers' => ['X-Trace' => '{{ input.trace.id }}'],
        ]],
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface");

    $response->assertOk()->assertJsonPath('data.interface.source', 'derived');

    $fields = collect($response->json('data.interface.fields'))->keyBy('key');

    // `mapping` values are dot-paths, not templates, so only templated
    // references are derived: endpoint and trace, not customer_name.
    expect($fields->keys()->sort()->values()->all())->toBe(['endpoint', 'trace']);
    expect($fields['endpoint']['type'])->toBe('string');
    // `{{ input.trace.id }}` means the run input needs a `trace` object.
    expect($fields['trace']['type'])->toBe('json');
    expect($fields['endpoint']['required'])->toBeFalse();
});

it('prefers a declared interface over the derived one and can clear it again', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['x' => 'input.x']]],
    ]);

    Passport::actingAs($owner);
    $url = "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface";

    $this->putJson($url, ['fields' => [
        ['key' => 'email', 'label' => 'Email address', 'type' => 'string', 'required' => true],
        ['key' => 'tier', 'type' => 'select', 'options' => [['value' => 'free'], ['value' => 'pro']]],
    ]])
        ->assertOk()
        ->assertJsonPath('data.interface.source', 'declared')
        ->assertJsonPath('data.interface.fields.0.label', 'Email address');

    $this->putJson($url, ['fields' => []])->assertOk()->assertJsonPath('data.interface.source', 'derived');
    expect($workflow->fresh()->input_schema)->toBeNull();
});

it('rejects a field key that would not be a top-level input entry', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]],
    ]);

    Passport::actingAs($owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface", [
        'fields' => [['key' => 'customer.email', 'type' => 'string']],
    ])->assertStatus(422);
});

it('runs a workflow from a submitted form and applies declared defaults', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => [
            'greeting' => 'input.name',
            'tier' => 'input.tier',
        ]]],
    ]);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface";

    $this->putJson($base, ['fields' => [
        ['key' => 'name', 'type' => 'string', 'required' => true],
        ['key' => 'tier', 'type' => 'string', 'default' => 'free'],
    ]])->assertOk();

    $response = $this->postJson("{$base}/runs", ['input' => ['name' => 'Ada']]);

    $response->assertStatus(202);
    expect($response->json('data.run.output'))->toBe(['a' => ['greeting' => 'Ada', 'tier' => 'free']]);
    expect(Run::sole()->input)->toBe(['name' => 'Ada', 'tier' => 'free']);
});

it('422s a form submission that omits a required field', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['greeting' => 'input.name']]],
    ]);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface";

    $this->putJson($base, ['fields' => [['key' => 'name', 'type' => 'string', 'required' => true]]])->assertOk();

    $response = $this->postJson("{$base}/runs", ['input' => []]);

    $response->assertStatus(422);
    expect(array_keys($response->json('errors')))->toBe(['input.name']);
});

it('422s a select value outside the declared options', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => ['t' => 'input.tier']]],
    ]);

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface";

    $this->putJson($base, ['fields' => [
        ['key' => 'tier', 'type' => 'select', 'required' => true, 'options' => [['value' => 'free'], ['value' => 'pro']]],
    ]])->assertOk();

    $this->postJson("{$base}/runs", ['input' => ['tier' => 'enterprise']])->assertStatus(422);
    $this->postJson("{$base}/runs", ['input' => ['tier' => 'pro']])->assertStatus(202);
});

it('describes the published graph rather than an edited draft', function () {
    [$workflow, $owner, $workspace] = interfaceWorkflow([
        ['key' => 'a', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => '{{ input.published_field }}']],
    ]);

    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'call_api', 'config' => ['method' => 'GET', 'url' => '{{ input.draft_field }}']]],
        'edges' => [],
    ]);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface");

    expect(array_column($response->json('data.interface.fields'), 'key'))->toBe(['published_field']);
});
