<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\RunStatus;
use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\Run;
use App\Models\Secrets\Secret;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Secrets\SecretRedactor;
use App\Services\Workflows\DryRunner;
use App\Services\Workspaces\WorkspaceService;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForSecretRuns(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

/**
 * A one-node workflow whose single `run_code` operation copies a template
 * straight into its output: the shortest way to observe what the engine
 * substituted into a node's config, and what it then persisted.
 */
function runWorkflowEchoing(Workspace $workspace, User $owner, string $template)
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [['op' => 'set', 'output' => 'echoed', 'value' => $template]],
            ]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    return app(StartWorkflowRunAction::class)->execute($workflow->fresh(), [])->fresh(['nodeRuns']);
}

it('resolves a non-secret variable into a node config at run time', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->variable()->create([
        'key' => 'API_BASE_URL',
        'value' => 'https://api.example.com',
    ]);

    $run = runWorkflowEchoing($workspace, $owner, 'Calling {{ vars.API_BASE_URL }}/v1');

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'Calling https://api.example.com/v1']);
});

it('resolves the same store under both the secrets and vars prefixes', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->variable()->create(['key' => 'REGION', 'value' => 'eu-west-1']);

    $viaSecrets = runWorkflowEchoing($workspace, $owner, '{{ secrets.REGION }}');
    $viaVars = runWorkflowEchoing($workspace, $owner, '{{ vars.REGION }}');

    expect($viaSecrets->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'eu-west-1']);
    expect($viaVars->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'eu-west-1']);
});

it('redacts a secret value out of the persisted node output', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $run = runWorkflowEchoing($workspace, $owner, 'Bearer {{ secrets.API_TOKEN }}');

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe(['echoed' => 'Bearer [redacted]']);
});

it('marks a resolved secret as used', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    $secret = Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'last_used_at' => null]);

    runWorkflowEchoing($workspace, $owner, '{{ secrets.API_TOKEN }}');

    expect($secret->fresh()->last_used_at)->not->toBeNull();
});

it('does not resolve a secret belonging to another workspace', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    $foreign = Secret::factory()->variable()->create(['key' => 'FOREIGN_KEY', 'value' => 'not-yours']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'run_code', 'config' => [
        'operations' => [['op' => 'set', 'output' => 'echoed', 'value' => '{{ secrets.FOREIGN_KEY }}']],
    ]]], 'edges' => []]);

    // Publishing checks the workflow's own workspace, where the key doesn't exist.
    expect(fn () => $workflow->publishVersion(publisher: $owner))->toThrow(WorkflowValidationException::class);
    expect($foreign->fresh()->last_used_at)->toBeNull();
});

it('refuses to publish a workflow that references a secret the workspace lacks', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'run_code', 'config' => [
        'operations' => [['op' => 'set', 'output' => 'echoed', 'value' => 'Bearer {{ secrets.NOPE_TOKEN }}']],
    ]]], 'edges' => []]);

    try {
        $workflow->publishVersion(publisher: $owner);
        $this->fail('Publishing should have been refused.');
    } catch (WorkflowValidationException $e) {
        expect($e->issues())->toContain([
            'node_key' => 'a',
            'field' => 'operations',
            'message' => 'No secret named NOPE_TOKEN. Add it or pick another.',
        ]);
    }
});

it('fails a step whose secret was deleted after publishing, naming it', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    $secret = Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => [['key' => 'a', 'type' => 'run_code', 'config' => [
        'operations' => [['op' => 'set', 'output' => 'echoed', 'value' => '{{ secrets.API_TOKEN }}']],
    ]]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $secret->delete();

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), [])->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->nodeRuns->firstWhere('key', 'a')->error)->toContain('No secret named API_TOKEN');
});

it('leaves secrets out of the context a node receives', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    // `copy` reads a dot-path out of the raw run context rather than going
    // through `{{ }}` resolution: the store must not be reachable that way.
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'copy', 'output' => 'sneaked', 'path' => 'secrets.API_TOKEN'],
                    ['op' => 'set', 'output' => 'referenced', 'value' => '{{ secrets.API_TOKEN }}'],
                ],
            ]],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), [])->fresh(['nodeRuns']);

    expect($run->nodeRuns->firstWhere('key', 'a')->output)->toBe([
        'sneaked' => null,
        'referenced' => '[redacted]',
    ]);
});

it('does not warn about secret references during a dry run', function () {
    $graph = [
        'nodes' => [
            ['key' => 'a', 'type' => 'run_code', 'config' => [
                'operations' => [
                    ['op' => 'set', 'output' => 'token', 'value' => '{{ secrets.API_TOKEN }}'],
                    ['op' => 'set', 'output' => 'region', 'value' => '{{ vars.REGION }}'],
                ],
            ]],
        ],
        'edges' => [],
    ];

    $result = app(DryRunner::class)->run($graph);

    expect($result['warnings'])->toBe([]);
    expect($result['ok'])->toBeTrue();
});

it('redacts a secret that comes back in a different case or URL-encoded', function () {
    $redactor = app(SecretRedactor::class);
    $values = ['Tok_Live+Abc/123'];

    expect($redactor->redactString('host tok_live+abc/123.invalid could not be resolved', $values))
        ->toBe('host [redacted].invalid could not be resolved')
        ->and($redactor->redactString('GET /x?key=Tok_Live%2BAbc%2F123', $values))
        ->toBe('GET /x?key=[redacted]')
        ->and($redactor->redact(['url' => 'https://api.test/?k=Tok_Live%2BAbc%2F123'], $values))
        ->toBe(['url' => 'https://api.test/?k=[redacted]']);
});

it('never hands a secret to a sub-workflow run', function () {
    [$workspace, $owner] = ownerWorkspaceForSecretRuns();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef123456']);

    $child = Workflow::factory()->forWorkspace($workspace)->create();
    $child->replaceGraph([
        'nodes' => [['key' => 'echo', 'type' => 'run_code', 'config' => [
            'operations' => [['op' => 'copy', 'output' => 'got', 'path' => 'input.token']],
        ]]],
        'edges' => [],
    ]);
    $child->publishVersion(publisher: $owner);

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => [
            'workflow_id' => $child->id,
            'input' => ['token' => '{{ secrets.API_TOKEN }}'],
        ]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($parent->fresh(), [])->fresh(['nodeRuns']);
    $childRun = Run::query()->where('workflow_id', $child->id)->firstOrFail();

    expect(json_encode($childRun->input))->not->toContain('tok_live_abcdef123456')
        ->and(json_encode($childRun->nodeRuns->toArray()))->not->toContain('tok_live_abcdef123456')
        ->and(json_encode($run->nodeRuns->toArray()))->not->toContain('tok_live_abcdef123456');
});
