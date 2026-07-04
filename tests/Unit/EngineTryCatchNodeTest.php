<?php

use App\Engine\NodeInput;
use App\Engine\Nodes\Flow\TryCatchNode;

function makeTryCatchInput(array $inputData): NodeInput
{
    return new NodeInput(
        nodeId: 'tc',
        nodeType: 'try_catch',
        nodeName: 'TryCatch',
        config: [],
        inputData: $inputData,
        credentials: null,
        variables: [],
        executionMeta: [],
    );
}

// ── Routing to 'catch' branch ─────────────────────────────────────────────────

test('routes to catch branch when a predecessor has __failed payload', function () {
    $input = makeTryCatchInput([
        'pred-a' => ['__failed' => true, 'error' => ['message' => 'Something broke']],
    ]);

    $result = (new TryCatchNode)->handle($input);

    expect($result->activeBranches)->toBe(['catch'])
        ->and($result->output['has_error'])->toBeTrue()
        ->and($result->output['error']['message'])->toBe('Something broke');
});

test('routes to try branch when all predecessors succeeded', function () {
    $input = makeTryCatchInput([
        'pred-a' => ['value' => 42],
    ]);

    $result = (new TryCatchNode)->handle($input);

    expect($result->activeBranches)->toBe(['try'])
        ->and($result->output['has_error'])->toBeFalse()
        ->and($result->output['error'])->toBeNull();
});

test('routes to try branch with empty input data', function () {
    $input = makeTryCatchInput([]);

    $result = (new TryCatchNode)->handle($input);

    expect($result->activeBranches)->toBe(['try'])
        ->and($result->output['has_error'])->toBeFalse();
});

test('routes to catch on first failing predecessor even when others succeeded', function () {
    $input = makeTryCatchInput([
        'pred-ok' => ['value' => 'good'],
        'pred-fail' => ['__failed' => true, 'error' => ['message' => 'bad']],
    ]);

    $result = (new TryCatchNode)->handle($input);

    expect($result->activeBranches)->toBe(['catch'])
        ->and($result->output['has_error'])->toBeTrue();
});

test('does not treat a plain error key as a failure signal', function () {
    // Old behaviour checked isset($inputData['error']) — this must no longer trigger catch
    $input = makeTryCatchInput([
        'pred-a' => ['error' => 'some value', 'other' => 'data'],
    ]);

    $result = (new TryCatchNode)->handle($input);

    expect($result->activeBranches)->toBe(['try'])
        ->and($result->output['has_error'])->toBeFalse();
});
