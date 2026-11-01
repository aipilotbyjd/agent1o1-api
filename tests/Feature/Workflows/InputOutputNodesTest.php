<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\DryRunner;
use App\Services\Workflows\GraphValidator;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workflows\WorkflowInterface;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workflow, 1: User, 2: Workspace}
 */
function ioWorkflow(array $nodes, array $edges = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph(['nodes' => $nodes, 'edges' => $edges]);
    $workflow->publishVersion(publisher: $owner);

    return [$workflow->fresh(), $owner, $workspace];
}

function ioInput(string $key, array $config): array
{
    return ['key' => $key, 'type' => 'workflow_input', 'config' => $config];
}

function ioOutput(string $key, string $name, string $value): array
{
    return ['key' => $key, 'type' => 'workflow_output', 'config' => ['name' => $name, 'value' => $value]];
}

function ioCopy(string $key, string $value): array
{
    return ['key' => $key, 'type' => 'run_code', 'config' => ['operations' => [
        ['op' => 'set', 'output' => 'text', 'value' => $value],
    ]]];
}

it('passes input values to later steps and returns the output steps as the run result', function () {
    [$workflow] = ioWorkflow(
        [
            ioInput('topic', ['name' => 'topic', 'required' => true]),
            ioInput('tone', ['name' => 'tone', 'default' => 'calm']),
            ioCopy('write', 'A {{ nodes.tone.value }} note about {{ nodes.topic.value }}'),
            ioOutput('result', 'note', '{{ nodes.write.text }}'),
        ],
        [
            ['from' => 'topic', 'to' => 'write'],
            ['from' => 'tone', 'to' => 'write'],
            ['from' => 'write', 'to' => 'result'],
        ],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['topic' => 'focus'])->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->nodeRuns->firstWhere('key', 'topic')->output)->toBe(['value' => 'focus']);
    expect($run->nodeRuns->firstWhere('key', 'tone')->output)->toBe(['value' => 'calm']);
    expect($run->output)->toBe(['note' => 'A calm note about focus']);
});

it('lets a step read an input it is not connected to', function () {
    [$workflow] = ioWorkflow([
        ioInput('topic', ['name' => 'topic']),
        ioCopy('write', 'About {{ nodes.topic.value }}'),
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['topic' => 'rain'])->fresh(['nodeRuns']);

    expect($run->nodeRuns->firstWhere('key', 'write')->output)->toBe(['text' => 'About rain']);
});

it('converts an input to its declared type', function () {
    [$workflow] = ioWorkflow([
        ioInput('count', ['name' => 'count', 'input_type' => 'number']),
        ioInput('urgent', ['name' => 'urgent', 'input_type' => 'boolean', 'default' => 'true']),
        ioInput('meta', ['name' => 'meta', 'input_type' => 'json']),
    ]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['count' => '3', 'meta' => '{"a":1}'])->fresh(['nodeRuns']);

    expect($run->nodeRuns->firstWhere('key', 'count')->output)->toBe(['value' => 3]);
    expect($run->nodeRuns->firstWhere('key', 'urgent')->output)->toBe(['value' => true]);
    expect($run->nodeRuns->firstWhere('key', 'meta')->output)->toBe(['value' => ['a' => 1]]);
});

it('fails the input step when a required value is missing', function () {
    [$workflow] = ioWorkflow([ioInput('topic', ['name' => 'topic', 'required' => true])]);

    $run = app(StartWorkflowRunAction::class)->execute($workflow, [])->fresh(['nodeRuns']);

    expect($run->status)->toBe(RunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'topic')->status)->toBe(NodeRunStatus::Failed);
    expect($run->nodeRuns->firstWhere('key', 'topic')->error)->toContain('missing the required input "topic"');
});

it('leaves out an output step whose branch did not run', function () {
    [$workflow] = ioWorkflow(
        [
            ioInput('status', ['name' => 'status']),
            ['key' => 'route', 'type' => 'router', 'config' => ['conditions' => [
                ['path' => 'nodes.status.value', 'operator' => 'equals', 'value' => 'open', 'result' => 'is_open'],
            ]]],
            ioOutput('open_out', 'answer', 'opened'),
            ioOutput('closed_out', 'answer', 'closed'),
        ],
        [
            ['from' => 'status', 'to' => 'route'],
            ['from' => 'route', 'to' => 'open_out', 'condition' => 'is_open'],
            ['from' => 'route', 'to' => 'closed_out', 'condition' => 'default'],
        ],
    );

    $run = app(StartWorkflowRunAction::class)->execute($workflow, ['status' => 'closed'])->fresh();

    expect($run->output)->toBe(['answer' => 'closed']);
});

it('returns a child workflows output values to a sub-workflow step', function () {
    [$child, $owner, $workspace] = ioWorkflow(
        [
            ioInput('n', ['name' => 'n', 'input_type' => 'number']),
            ioOutput('out', 'echoed', '{{ nodes.n.value }}'),
        ],
        [['from' => 'n', 'to' => 'out']],
    );

    $parent = Workflow::factory()->forWorkspace($workspace)->create();
    $parent->replaceGraph([
        'nodes' => [['key' => 'sub', 'type' => 'subflow', 'config' => ['workflow_id' => $child->id, 'input' => ['n' => 21]]]],
        'edges' => [],
    ]);
    $parent->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($parent->fresh())->fresh(['nodeRuns']);

    expect($run->nodeRuns->firstWhere('key', 'sub')->output)->toBe(['echoed' => 21]);
});

it('builds the run form from the input steps in canvas order', function () {
    [$workflow] = ioWorkflow([
        [...ioInput('topic', ['name' => 'topic', 'label' => 'Topic', 'required' => true, 'description' => 'What to write about']), 'position' => ['x' => 300, 'y' => 0]],
        [...ioInput('size', ['name' => 'size', 'input_type' => 'select', 'options' => ['short', 'long'], 'default' => 'short']), 'position' => ['x' => 0, 'y' => 200]],
        ioCopy('write', '{{ input.legacy }}'),
    ]);

    $interface = app(WorkflowInterface::class)->describe($workflow);

    expect($interface['source'])->toBe('nodes');
    expect($interface['fields'])->toBe([
        ['key' => 'topic', 'label' => 'Topic', 'type' => 'string', 'required' => true, 'help' => 'What to write about', 'default' => null, 'options' => []],
        ['key' => 'size', 'label' => 'size', 'type' => 'select', 'required' => false, 'help' => null, 'default' => 'short', 'options' => [
            ['value' => 'short', 'label' => 'short'],
            ['value' => 'long', 'label' => 'long'],
        ]],
    ]);
});

it('validates a form submission against the input steps', function () {
    [$workflow, $owner, $workspace] = ioWorkflow([
        ioInput('topic', ['name' => 'topic', 'required' => true]),
        ioOutput('out', 'topic', '{{ nodes.topic.value }}'),
    ], [['from' => 'topic', 'to' => 'out']]);

    Passport::actingAs($owner);
    $url = "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/interface/runs";

    $this->postJson($url, ['input' => []])->assertUnprocessable()->assertJsonValidationErrors(['input.topic']);

    $runId = $this->postJson($url, ['input' => ['topic' => 'rain']])->assertStatus(202)->json('data.run.id');

    expect(Run::find($runId)->output)->toBe(['topic' => 'rain']);
});

it('refuses to publish input and output steps that cannot work', function () {
    $issues = app(GraphValidator::class)->issues([
        ioInput('a', ['name' => 'topic']),
        ioInput('b', ['name' => 'topic']),
        ioInput('c', ['name' => 'bad name']),
        ioInput('d', ['name' => 'size', 'input_type' => 'select']),
        ['key' => 'e', 'type' => 'workflow_output', 'config' => ['name' => 'result', 'value' => '']],
    ], []);

    $byNode = collect($issues)->mapToGroups(fn ($issue) => [$issue->nodeKey => $issue->field])->map->all()->all();

    expect($byNode)->toBe([
        'b' => ['name'],
        'c' => ['name'],
        'd' => ['options'],
        'e' => ['value'],
    ]);
});

it('blocks publishing a workflow with two inputs of the same name', function () {
    ioWorkflow([ioInput('a', ['name' => 'topic']), ioInput('b', ['name' => 'topic'])]);
})->throws(WorkflowValidationException::class);

it('resolves input references in a dry run without warnings', function () {
    $result = app(DryRunner::class)->run([
        'nodes' => [
            ioInput('topic', ['name' => 'topic']),
            ioCopy('write', 'About {{ nodes.topic.value }}'),
            ioOutput('out', 'note', '{{ nodes.write.text }}'),
        ],
        'edges' => [
            ['from' => 'topic', 'to' => 'write', 'condition' => null],
            ['from' => 'write', 'to' => 'out', 'condition' => null],
        ],
    ], ['topic' => 'rain']);

    $steps = collect($result['steps'])->keyBy('key');

    expect($result['warnings'])->toBe([]);
    expect($steps['topic']['sample_output'])->toBe(['value' => 'rain']);
    expect($steps['write']['resolved_config']['operations'][0]['value'])->toBe('About rain');
});

it('publishes the output shape of input and output steps in the catalog', function () {
    $catalog = collect(app(NodeRegistry::class)->catalog())->keyBy('type');

    expect($catalog['workflow_input']['category'])->toBe('inputs-outputs');
    expect(array_keys($catalog['workflow_input']['output_schema']['properties']))->toBe(['value']);
    expect(array_keys($catalog['workflow_output']['output_schema']['properties']))->toBe(['value']);
    expect($catalog['call_api']['output_schema'])->toBeNull();
});
