<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Enums\NodeRunStatus;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ApprovalForm;
use App\Services\Workflows\GraphValidator;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * A run paused on an approval step with the given settings, with a step after
 * it that reads what was answered.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 * @return array{0: Workspace, 1: User, 2: Run, 3: WorkflowApproval}
 */
function pausedApproval(array $config, array $input = []): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'gate', 'type' => 'human_approval', 'config' => $config],
            ['key' => 'after', 'type' => 'set_variable', 'config' => ['variables' => ['budget' => '{{ nodes.gate.inputs.budget }}', 'ok' => '{{ nodes.gate.approved }}']]],
        ],
        'edges' => [['from' => 'gate', 'to' => 'after', 'condition' => null]],
    ]);
    $workflow->publishVersion(publisher: $owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), $input)->fresh(['nodeRuns']);

    return [$workspace, $owner, $run, $run->nodeRuns->firstWhere('key', 'gate')->approval];
}

function decideUrl(Workspace $workspace, Run $run, WorkflowApproval $approval): string
{
    return "/api/v1/workspaces/{$workspace->id}/runs/{$run->id}/approvals/{$approval->id}/decide";
}

$questions = [
    'title' => 'Refund of {{ input.amount }} for {{ input.customer }}',
    'instructions' => 'Check the order history first.',
    'fields' => [
        ['key' => 'budget', 'label' => 'Approved amount', 'type' => 'number', 'required' => true],
        ['key' => 'reason', 'label' => 'Reason', 'type' => 'select', 'options' => ['goodwill', 'defect', 'fraud'], 'required' => true],
        ['key' => 'notify', 'label' => 'Tell the customer', 'type' => 'boolean'],
        ['key' => 'note', 'label' => 'Internal note', 'type' => 'textarea'],
    ],
];

it('fixes the question, with its details filled in, when the run pauses', function () use ($questions) {
    [, , $run, $approval] = pausedApproval($questions, ['amount' => '$120', 'customer' => 'Initech']);

    $form = $run->nodeRuns->firstWhere('key', 'gate')->output['approval_form'];

    expect($form['title'])->toBe('Refund of $120 for Initech')
        ->and($form['instructions'])->toBe('Check the order history first.')
        ->and(array_column($form['fields'], 'key'))->toBe(['budget', 'reason', 'notify', 'note'])
        ->and($form['fields'][1]['options'])->toBe(['goodwill', 'defect', 'fraud'])
        ->and($form['fields'][0])->toMatchArray(['label' => 'Approved amount', 'type' => 'number', 'required' => true])
        ->and($approval->isDecided())->toBeFalse();
});

it('takes the answers with an approval, types them and hands them to the next step', function () use ($questions) {
    [$workspace, $owner, $run, $approval] = pausedApproval($questions);
    Passport::actingAs($owner);

    $response = $this->postJson(decideUrl($workspace, $run, $approval), [
        'decision' => 'approve',
        'message' => 'Fine by me',
        'inputs' => ['budget' => '150.5', 'reason' => 'defect', 'notify' => 'true', 'note' => '  checked  ', 'sneaky' => 'dropped'],
    ])->assertOk();

    $gate = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'gate');

    expect($gate->output)->toBe(['approved' => true, 'inputs' => ['budget' => 150.5, 'reason' => 'defect', 'notify' => true, 'note' => 'checked'], 'message' => 'Fine by me'])
        ->and($response->json('data.approval.inputs'))->toBe(['budget' => 150.5, 'reason' => 'defect', 'notify' => true, 'note' => 'checked'])
        ->and($run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'after')->output)->toMatchArray(['budget' => 150.5, 'ok' => true]);
});

it('reads a yes or no answer in the ways people send it', function (mixed $sent, bool $expected) {
    $form = ApprovalForm::fields(['fields' => [['key' => 'ok', 'type' => 'boolean']]]);

    expect(ApprovalForm::check($form, ['ok' => $sent])['values'])->toBe(['ok' => $expected]);
})->with([
    'true' => [true, true], 'false' => [false, false], 'one' => [1, true], 'zero' => ['0', false],
    'yes' => ['yes', true], 'no' => ['no', false], 'on' => ['on', true], 'text true' => ['true', true], 'blank' => ['', false], 'missing as null' => [null, false],
]);

it('refuses a yes or no answer that is neither', function () {
    $form = ApprovalForm::fields(['fields' => [['key' => 'ok', 'type' => 'boolean']]]);

    expect(ApprovalForm::check($form, ['ok' => 'perhaps'])['errors'])->toHaveKey('ok');
});

it('refuses an approval that leaves a required question unanswered or answers it wrongly', function (array $inputs, string $errorKey) use ($questions) {
    [$workspace, $owner, $run, $approval] = pausedApproval($questions);
    Passport::actingAs($owner);

    $this->postJson(decideUrl($workspace, $run, $approval), ['decision' => 'approve', 'inputs' => $inputs])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect($approval->fresh()->isDecided())->toBeFalse()
        ->and($run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'gate')->status)->toBe(NodeRunStatus::AwaitingApproval);
})->with([
    'nothing' => [[], 'inputs.budget'],
    'a number that is not' => [['budget' => 'lots', 'reason' => 'defect'], 'inputs.budget'],
    'an option not offered' => [['budget' => 5, 'reason' => 'whim'], 'inputs.reason'],
    'a missing required choice' => [['budget' => 5], 'inputs.reason'],
    'a note too long' => [['budget' => 5, 'reason' => 'defect', 'note' => str_repeat('a', 5001)], 'inputs.note'],
]);

it('lets a rejection through without answers', function () use ($questions) {
    [$workspace, $owner, $run, $approval] = pausedApproval($questions);
    Passport::actingAs($owner);

    $this->postJson(decideUrl($workspace, $run, $approval), ['decision' => 'reject', 'message' => 'No'])->assertOk();

    expect($approval->fresh()->decision)->toBe('rejected')
        ->and($run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'gate')->status)->toBe(NodeRunStatus::Failed);
});

it('lists the question on the dashboard, and keeps it when the answers are refused', function () use ($questions) {
    [$workspace, $owner, $run, $approval] = pausedApproval($questions, ['amount' => '$5', 'customer' => 'Acme']);
    Passport::actingAs($owner);

    $card = collect($this->getJson("/api/v1/workspaces/{$workspace->id}/dashboard/pending-approvals")->assertOk()->json('data'))->firstWhere('id', $approval->id);

    expect($card['form']['title'])->toBe('Refund of $5 for Acme')
        ->and(array_column($card['form']['fields'], 'key'))->toBe(['budget', 'reason', 'notify', 'note']);

    $this->postJson(decideUrl($workspace, $run, $approval), ['decision' => 'approve', 'inputs' => []])->assertUnprocessable();

    expect($approval->fresh()->nodeRun->output['approval_form']['title'])->toBe('Refund of $5 for Acme');
});

it('keeps an approval with no questions exactly as it was', function () {
    [$workspace, $owner, $run, $approval] = pausedApproval([]);
    Passport::actingAs($owner);

    expect($run->nodeRuns->firstWhere('key', 'gate')->output)->toBeNull();

    $this->postJson(decideUrl($workspace, $run, $approval), ['decision' => 'approve', 'inputs' => ['ignored' => 'x']])->assertOk()
        ->assertJsonPath('data.approval.fields', [])
        ->assertJsonPath('data.approval.inputs', null);

    expect($run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'gate')->output)->toBe(['approved' => true]);
});

it('checks an approval form when the workflow is published', function (array $fields, string $expected) {
    $problems = implode(' | ', ApprovalForm::problems(['fields' => $fields]));

    expect($problems)->toContain($expected);
})->with([
    'a repeated name' => [[['key' => 'a'], ['key' => 'a']], 'Two fields are both called “a”'],
    'an unusable name' => [[['key' => '1 bad']], 'needs a name made of letters'],
    'an unknown type' => [[['key' => 'a', 'type' => 'file']], 'unknown type “file”'],
    'a dropdown with no options' => [[['key' => 'a', 'type' => 'select']], 'needs at least one option'],
    'not a field' => [['text'], 'is not a field definition'],
    'too many' => [array_map(fn (int $i): array => ['key' => "f{$i}"], range(1, 21)), 'at most 20 fields'],
]);

it('accepts a good form, no form, and a form that is filled in at run time', function () use ($questions) {
    expect(ApprovalForm::problems($questions))->toBe([])
        ->and(ApprovalForm::problems([]))->toBe([])
        ->and(ApprovalForm::problems(['fields' => '{{ input.questions }}']))->toBe([])
        ->and(ApprovalForm::problems(['fields' => 'plain text']))->toContain('The form’s fields must be a list.');
});

it('refuses to publish a workflow whose approval form is broken, naming the step', function () {
    $issues = app(GraphValidator::class)->validate(
        [['key' => 'gate', 'type' => 'human_approval', 'config' => ['fields' => [['key' => 'a', 'type' => 'select']]]]],
        [],
    );

    expect(implode(' ', $issues))->toContain('gate')->toContain('needs at least one option');
});
