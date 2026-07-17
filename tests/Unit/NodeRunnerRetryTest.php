<?php

use App\Contracts\NodeHandler;
use App\Engine\Execution\NodeRunner;
use App\Engine\Execution\OutputBuffer;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Core\TransformNode;
use App\Engine\WorkflowContext;
use App\Engine\WorkflowGraph;
use App\Enums\ExecutionNodeStatus;

/**
 * Build a one-node graph whose single `transform` node carries the given retry
 * config. NodeCatalog resolves the `transform` handler through the container, so
 * tests bind a programmable fake in its place via bindHandler().
 */
function retryGraph(array $retry = [], array $extraConfig = []): WorkflowGraph
{
    return WorkflowGraph::compile(
        [['id' => 'a', 'type' => 'transform', 'name' => 'A', 'config' => ['retry' => $retry] + $extraConfig]],
        [],
    );
}

function retryContext(WorkflowGraph $graph): WorkflowContext
{
    $ctx = new WorkflowContext(
        graph: $graph,
        outputs: new OutputBuffer('exec-1', $graph->downstreamConsumers),
        executionId: 'exec-1',
        workspaceId: 'ws-1',
    );
    $ctx->popReadyNodes(); // drain the start-node seed

    return $ctx;
}

/**
 * Replace the transform handler with a fake driven by $handle($callNumber, $input).
 * Returns the handler so tests can assert its call count.
 */
function bindHandler(Closure $handle): object
{
    $handler = new class($handle) implements NodeHandler
    {
        public int $calls = 0;

        public function __construct(private Closure $handle) {}

        public function handle(NodeInput $input): NodeResult
        {
            $this->calls++;

            return ($this->handle)($this->calls, $input);
        }
    };

    app()->instance(TransformNode::class, $handler);

    return $handler;
}

test('a node that succeeds first time runs once and records attempt 1', function () {
    $handler = bindHandler(fn () => NodeResult::completed(['ok' => true]));
    $graph = retryGraph(['max_attempts' => 3, 'backoff' => 0]);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->attempt)->toBe(1)
        ->and($handler->calls)->toBe(1);
});

test('a flaky node retries then succeeds within max attempts', function () {
    $handler = bindHandler(
        fn (int $call) => $call < 3 ? NodeResult::failed('transient') : NodeResult::completed(['ok' => true]),
    );
    $graph = retryGraph(['max_attempts' => 3, 'backoff' => 0]);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($result->attempt)->toBe(3)
        ->and($handler->calls)->toBe(3);
});

test('a node that always fails is retried up to max attempts then fails', function () {
    $handler = bindHandler(fn () => NodeResult::failed('endpoint is down'));
    $graph = retryGraph(['max_attempts' => 3, 'backoff' => 0]);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->isFailed())->toBeTrue()
        ->and($result->attempt)->toBe(3)
        ->and($handler->calls)->toBe(3)
        ->and($result->error['message'])->toBe('endpoint is down');
});

test('default policy (max_attempts 1) does not retry', function () {
    $handler = bindHandler(fn () => NodeResult::failed('down'));
    $graph = retryGraph(['max_attempts' => 1, 'backoff' => 0]);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->isFailed())->toBeTrue()
        ->and($result->attempt)->toBe(1)
        ->and($handler->calls)->toBe(1);
});

test('an exception thrown by a handler is retried and surfaced as a failure', function () {
    $handler = bindHandler(function (int $call) {
        throw new RuntimeException("boom {$call}");
    });
    $graph = retryGraph(['max_attempts' => 2, 'backoff' => 0]);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->isFailed())->toBeTrue()
        ->and($result->attempt)->toBe(2)
        ->and($handler->calls)->toBe(2)
        ->and($result->error['message'])->toContain('boom');
});

test('resolved config is captured on the result for persistence as input_data', function () {
    bindHandler(fn () => NodeResult::completed(['ok' => true]));
    $graph = retryGraph(['max_attempts' => 1, 'backoff' => 0], ['endpoint' => 'https://api.test/x']);

    $result = (new NodeRunner)->runSync('a', $graph, retryContext($graph));

    expect($result->input)->toHaveKey('config')
        ->and($result->input['config']['endpoint'])->toBe('https://api.test/x');
});

/**
 * Two-node graph: a `src` node whose output is seeded directly into the buffer,
 * feeding a loop-mode `transform` node `a` that carries the given config.
 */
function loopGraph(array $config = []): WorkflowGraph
{
    return WorkflowGraph::compile(
        [
            ['id' => 'src', 'type' => 'transform', 'name' => 'Src', 'config' => []],
            ['id' => 'a', 'type' => 'transform', 'name' => 'A', 'config' => ['retry' => ['max_attempts' => 1, 'backoff' => 0]] + $config, 'data' => ['loopMode' => true]],
        ],
        [['source' => 'src', 'target' => 'a']],
    );
}

test('loop mode runs the handler once per list item and aggregates outputs', function () {
    // Handler echoes the per-item resolved config, proving {{ item.* }} resolves.
    $handler = bindHandler(fn ($call, $input) => NodeResult::completed(['label' => $input->config['label']]));
    $graph = loopGraph(['label' => '{{ item.id }}']);

    $ctx = retryContext($graph);
    $ctx->outputs->store('src', ['body' => [['id' => 1], ['id' => 2], ['id' => 3]]]);

    $result = (new NodeRunner)->runSync('a', $graph, $ctx);

    expect($result->status)->toBe(ExecutionNodeStatus::Completed)
        ->and($handler->calls)->toBe(3)
        ->and($result->output['count'])->toBe(3)
        ->and($result->output['items'])->toBe([
            ['label' => 1],
            ['label' => 2],
            ['label' => 3],
        ]);
});

test('loop mode over a bare list output iterates each element', function () {
    $handler = bindHandler(fn ($call, $input) => NodeResult::completed(['v' => $input->config['v']]));
    $graph = loopGraph(['v' => '{{ item }}']);

    $ctx = retryContext($graph);
    $ctx->outputs->store('src', ['x', 'y']);

    $result = (new NodeRunner)->runSync('a', $graph, $ctx);

    expect($handler->calls)->toBe(2)
        ->and($result->output['items'])->toBe([['v' => 'x'], ['v' => 'y']]);
});

test('loop mode falls back to a single run when no list is available upstream', function () {
    $handler = bindHandler(fn () => NodeResult::completed(['ok' => true]));
    $graph = loopGraph();

    $ctx = retryContext($graph);
    $ctx->outputs->store('src', ['id' => 5, 'name' => 'solo']); // object, not a list

    $result = (new NodeRunner)->runSync('a', $graph, $ctx);

    expect($handler->calls)->toBe(1)
        ->and($result->output)->toBe(['ok' => true]); // not wrapped in { items }
});

test('loop mode fails fast on a failing iteration and reports the loop index', function () {
    $handler = bindHandler(
        fn (int $call) => $call === 2 ? NodeResult::failed('bad item') : NodeResult::completed(['ok' => $call]),
    );
    $graph = loopGraph();

    $ctx = retryContext($graph);
    $ctx->outputs->store('src', ['items' => [10, 20, 30]]);

    $result = (new NodeRunner)->runSync('a', $graph, $ctx);

    expect($result->isFailed())->toBeTrue()
        ->and($handler->calls)->toBe(2) // stopped at the failing item
        ->and($result->error['message'])->toBe('bad item')
        ->and($result->error['loop_index'])->toBe(1);
});
