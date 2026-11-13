<?php

use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Runs\Run;
use App\Models\Sandbox\SandboxProvider;
use App\Models\User;
use App\Nodes\DataTransform\CodeNode;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);

    $this->workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->run = Run::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->node = fn () => app(CodeNode::class);
    $this->givePlan = fn () => PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['code_execution' => true]]))
        ->active()
        ->create();
});

it('is registered next to the safe run_code node, which is unchanged', function () {
    $registry = app(NodeRegistry::class);

    expect($registry->resolve('code'))->toBeInstanceOf(CodeNode::class)
        ->and($registry->resolve('run_code')->description())->toContain('not arbitrary code execution');
});

it('only runs on plans that include code', function () {
    SandboxProvider::factory()->runner()->create();
    Http::fake();

    expect(fn () => ($this->node)()->execute($this->run, ['language' => 'python', 'code' => 'print(1)'], []))
        ->toThrow(RuntimeException::class, 'not part of this workspace');

    Http::assertNothingSent();
});

it('runs the code remotely, returns its JSON answer and passes the data in', function () {
    ($this->givePlan)();
    SandboxProvider::factory()->runner()->create();
    Http::fake(['*' => Http::response(['stdout' => "working…\n{\"total\": 6}\n", 'stderr' => '', 'error' => null, 'seconds' => 2])]);

    $output = ($this->node)()->execute($this->run, ['language' => 'python', 'code' => 'print(sum(INPUT["n"]))'], ['n' => [1, 2, 3]]);

    expect($output['result'])->toBe(['total' => 6])
        ->and($output['stdout'])->toContain('working…')
        ->and($output)->not->toHaveKey('provider');
});

it('returns a null answer when the script printed no JSON', function () {
    ($this->givePlan)();
    SandboxProvider::factory()->runner()->create();
    Http::fake(['*' => Http::response(['stdout' => "just text\n", 'stderr' => '', 'error' => null, 'seconds' => 1])]);

    expect(($this->node)()->execute($this->run, ['language' => 'js', 'code' => 'console.log("just text")'], [])['result'])->toBeNull();
});

it('fails the node with the script\'s own error', function () {
    ($this->givePlan)();
    SandboxProvider::factory()->runner()->create();
    Http::fake(['*' => Http::response(['stdout' => '', 'stderr' => '', 'error' => 'NameError: x is not defined', 'seconds' => 1])]);

    expect(fn () => ($this->node)()->execute($this->run, ['language' => 'python', 'code' => 'x'], []))
        ->toThrow(RuntimeException::class, 'NameError');
});

it('rejects bad config before touching a provider', function (array $config, string $message) {
    ($this->givePlan)();
    SandboxProvider::factory()->runner()->create();
    Http::fake();

    expect(fn () => ($this->node)()->execute($this->run, $config, []))->toThrow(InvalidArgumentException::class, $message);

    Http::assertNothingSent();
})->with([
    'an unsupported language' => [['language' => 'ruby', 'code' => 'puts 1'], 'Unsupported language'],
    'no code' => [['language' => 'python', 'code' => '  '], 'no code'],
    'code that is too long' => [['language' => 'python', 'code' => str_repeat('a', 70 * 1024)], 'too long'],
]);

it('refuses data that is too large to send', function () {
    ($this->givePlan)();
    SandboxProvider::factory()->runner()->create();
    config(['sandbox.code.max_input_kilobytes' => 1]);

    expect(fn () => ($this->node)()->execute($this->run, ['language' => 'python', 'code' => 'x'], ['blob' => str_repeat('a', 2000)]))
        ->toThrow(InvalidArgumentException::class, 'too large');
});

it('describes itself with a language and code config', function () {
    $schema = ($this->node)()->configSchema();

    expect($schema['required'])->toBe(['language', 'code'])
        ->and(array_keys($schema['properties']))->toBe(['language', 'code', 'timeout_seconds']);
});
