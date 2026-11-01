<?php

use App\Contracts\NodeContract;
use App\Models\Nodes\NodeCategory;
use App\Nodes\AiAutomation\AskAiNode;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\TransformNode;
use App\Nodes\FlowLogic\FilterNode;
use App\Nodes\FlowLogic\RouterNode;
use App\Services\Workflows\NodeRegistry;

it('registers every minimal built-in node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $expected = [
        'transform' => TransformNode::class,
        'call_api' => CallApiNode::class,
        'run_code' => RunCodeNode::class,
        'router' => RouterNode::class,
        'filter' => FilterNode::class,
        'ask_ai' => AskAiNode::class,
        'set_variable' => App\Nodes\DataTransform\SetVariableNode::class,
        'string' => App\Nodes\DataTransform\StringNode::class,
        'json' => App\Nodes\DataTransform\JsonNode::class,
        'array' => App\Nodes\DataTransform\ArrayNode::class,
        'math' => App\Nodes\DataTransform\MathNode::class,
        'date_time' => App\Nodes\DataTransform\DateTimeNode::class,
    ];

    foreach ($expected as $type => $class) {
        expect($registry->has($type))->toBeTrue();

        $node = $registry->resolve($type);

        expect($node)->toBeInstanceOf(NodeContract::class)
            ->and($node)->toBeInstanceOf($class)
            ->and($node->type())->toBe($type);
    }
});

it('throws for an unregistered node type', function () {
    app(NodeRegistry::class)->resolve('does-not-exist');
})->throws(InvalidArgumentException::class);

it('throws for a custom node type since the executor is not built yet', function () {
    app(NodeRegistry::class)->resolve('custom:1');
})->throws(InvalidArgumentException::class);

it('gives core nodes their own icon and app nodes their category icon', function () {
    NodeCategory::factory()->create(['slug' => 'data-transform', 'icon' => 'shuffle']);
    NodeCategory::factory()->create(['slug' => 'flow-logic', 'icon' => 'git-branch']);
    NodeCategory::factory()->create(['slug' => 'github', 'icon' => 'github']);

    $icons = collect(app(NodeRegistry::class)->catalog())->pluck('icon', 'type');

    expect($icons['call_api'])->toBe('api')
        ->and($icons['run_code'])->toBe('source-code')
        ->and($icons['loop'])->toBe('repeat')
        ->and($icons['github_list_repos'])->toBe('github');
});
