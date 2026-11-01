<?php

use App\Ai\Tools\NodeTool;
use App\Models\Agents\AgentToolBinding;
use App\Models\Runs\Run;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\FlowLogic\RouterNode;
use App\Services\Http\SsrfGuard;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * The one test that must never regress, per docs/AGENTS_PLAN.md's
 * verification bullet: a bound config value can never be overridden by a
 * tool-call argument, no matter what the model sends.
 */
it('ignores a tool-call argument that tries to override a bound config value', function () {
    $run = Run::factory()->create();

    $binding = new AgentToolBinding([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com/webhook', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);

    $tool = new NodeTool(new CallApiNode(new SsrfGuard(fn () => ['203.0.113.10'])), $binding, $run);

    Http::fake(['internal.example.com/*' => Http::response(['ok' => true])]);

    $tool->handle(new Request([
        'url' => 'https://evil.example.com/exfiltrate',
        'method' => 'DELETE',
        'body' => ['message' => 'hello'],
    ]));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://internal.example.com/webhook'
            && $request->method() === 'POST';
    });
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example.com'));
});

it('never exposes a bound field in the tool schema, even if also listed in exposed_fields by mistake', function () {
    $binding = new AgentToolBinding([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com/webhook', 'method' => 'POST'],
        // Mistake: 'url' listed as exposed despite already being bound.
        'exposed_fields' => ['url', 'body'],
    ]);

    $tool = new NodeTool(new CallApiNode, $binding, Run::factory()->create());

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['body']);
});

it('exposes every non-bound field by default when exposed_fields is null', function () {
    $binding = new AgentToolBinding([
        'node_type' => 'router',
        'config' => [],
        'exposed_fields' => null,
    ]);

    $tool = new NodeTool(new RouterNode, $binding, Run::factory()->create());

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['conditions']);
});

it('marks an exposed field required only when the underlying config schema requires it', function () {
    $binding = new AgentToolBinding([
        'node_type' => 'call_api',
        'config' => [],
        'exposed_fields' => ['method', 'timeout_seconds'],
    ]);

    $tool = new NodeTool(new CallApiNode, $binding, Run::factory()->create());

    // 'required' is only surfaced on the *parent* object's serialized
    // 'required' array, not on a standalone property's own toArray(), so
    // wrap the returned properties in an object type to inspect it.
    $jsonSchema = new JsonSchemaTypeFactory;
    $serialized = $jsonSchema->object(fn () => $tool->schema($jsonSchema))->toArray();

    expect($serialized['required'] ?? [])->toBe(['method']);
});

it('describes the properties of nested objects so the model knows their keys', function () {
    $binding = new AgentToolBinding(['node_type' => 'run_code', 'config' => [], 'exposed_fields' => null]);

    $tool = new NodeTool(new RunCodeNode, $binding, Run::factory()->create());

    $jsonSchema = new JsonSchemaTypeFactory;
    $item = $jsonSchema->object(fn () => $tool->schema($jsonSchema))->toArray()['properties']['operations']['items'];

    expect(array_keys($item['properties']))->toBe(['op', 'output', 'path', 'value', 'paths'])
        ->and($item['properties']['op']['enum'])->toBe(['set', 'copy', 'uppercase', 'lowercase', 'concat'])
        ->and($item['required'])->toBe(['op', 'output']);
});

it('drops a tool-call argument for a field that is neither bound nor exposed', function () {
    $binding = new AgentToolBinding([
        'node_type' => 'call_api',
        'config' => ['url' => 'https://internal.example.com/webhook', 'method' => 'POST'],
        'exposed_fields' => ['body'],
    ]);

    $tool = new NodeTool(new CallApiNode(new SsrfGuard(fn () => ['203.0.113.10'])), $binding, Run::factory()->create());

    Http::fake(['internal.example.com/*' => Http::response(['ok' => true])]);

    $tool->handle(new Request([
        'body' => ['message' => 'hello'],
        'headers' => ['X-Injected' => 'yes'],
    ]));

    Http::assertSent(fn ($request) => $request['message'] === 'hello' && ! $request->hasHeader('X-Injected'));
});
