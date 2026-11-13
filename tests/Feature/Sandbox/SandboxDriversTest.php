<?php

use App\Enums\Sandbox\SandboxMode;
use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\Drivers\DaytonaSandboxDriver;
use App\Services\Sandbox\Drivers\E2BSandboxDriver;
use App\Services\Sandbox\Drivers\PistonSandboxDriver;
use App\Services\Sandbox\Drivers\RunnerSandboxDriver;
use App\Services\Sandbox\SandboxGoneException;
use App\Services\Sandbox\SandboxRouter;
use App\Services\Sandbox\SandboxStartException;
use App\Services\Sandbox\SandboxUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['assistant.sandbox.api_key' => null]);
});

it('runs a one-off program on Piston and reports a failing exit', function () {
    Http::fake(['https://93.184.216.36/api/v2/execute' => Http::sequence()
        ->push(['run' => ['stdout' => "42\n", 'stderr' => '', 'code' => 0, 'signal' => null]])
        ->push(['run' => ['stdout' => '', 'stderr' => 'boom', 'code' => 1, 'signal' => null]])]);

    $driver = new PistonSandboxDriver(SandboxProvider::factory()->piston()->make());

    expect($driver->runOnce('python', 'print(42)', 30))->stdout->toBe("42\n")->error->toBeNull()
        ->and($driver->runOnce('js', 'throw 1', 30)->error)->toContain('Exited with code 1');

    Http::assertSent(fn (HttpRequest $request) => $request['language'] === 'python' && $request['run_timeout'] === 30000);
});

it('says Piston could not be reached as a start failure, so another provider may be tried', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('refused')]);

    expect(fn () => (new PistonSandboxDriver(SandboxProvider::factory()->piston()->make()))->runOnce('python', 'print(1)', 10))
        ->toThrow(SandboxStartException::class);
});

it('never asks a one-shot-only provider for a computer', function () {
    expect(fn () => (new PistonSandboxDriver(SandboxProvider::factory()->piston()->make()))->create(60))->toThrow(LogicException::class);
});

it('drives a runner through its whole life', function () {
    Http::fake([
        'https://93.184.216.35/v1/sandboxes' => Http::response(['id' => 'abc', 'access_token' => 'tok']),
        'https://93.184.216.35/v1/sandboxes/abc/run' => Http::response(['stdout' => "hi\n", 'stderr' => '', 'results' => ['1'], 'error' => null, 'seconds' => 2]),
        'https://93.184.216.35/v1/sandboxes/abc/files*' => Http::response('file-bytes'),
        'https://93.184.216.35/v1/sandboxes/abc/keepalive' => Http::response([]),
        'https://93.184.216.35/v1/sandboxes/abc' => Http::response([]),
    ]);
    $driver = new RunnerSandboxDriver(SandboxProvider::factory()->runner()->make(['credentials' => ['token' => 'secret-token']]));

    $created = $driver->create(900);
    $result = $driver->run($created['id'], $created['access_token'], 'python', 'print("hi")', 30);

    expect($created)->toBe(['id' => 'abc', 'access_token' => 'tok'])
        ->and($result)->stdout->toBe("hi\n")->results->toBe(['1'])->seconds->toBe(2)
        ->and($driver->readFile('abc', 'tok', '/home/user/a.txt', 1000))->toBe('file-bytes');

    $driver->keepAlive('abc', 900);
    $driver->destroy('abc', 'tok');

    Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer secret-token') && $request->method() === 'DELETE');
});

it('treats a runner that lost the sandbox as gone', function () {
    Http::fake(['*' => Http::response(['message' => 'no such sandbox'], 404)]);
    $driver = new RunnerSandboxDriver(SandboxProvider::factory()->runner()->make());

    expect(fn () => $driver->run('abc', null, 'python', 'x', 5))->toThrow(SandboxGoneException::class)
        ->and(fn () => $driver->keepAlive('abc', 60))->toThrow(SandboxGoneException::class);
});

it('treats a busy or down runner as a start failure', function (int $status) {
    Http::fake(['*' => Http::response('busy', $status)]);
    $driver = new RunnerSandboxDriver(SandboxProvider::factory()->runner()->make());

    expect(fn () => $driver->create(60))->toThrow(SandboxStartException::class)
        ->and(fn () => $driver->runOnce('python', 'x', 5))->toThrow(SandboxStartException::class);
})->with([429, 503]);

it('refuses to call a runner at an address that is not allowed', function () {
    Http::fake();
    $driver = new RunnerSandboxDriver(SandboxProvider::factory()->runner('https://10.0.0.5')->make());

    expect(fn () => $driver->runOnce('python', 'x', 5))->toThrow(RuntimeException::class, 'not allowed');

    Http::assertNothingSent();
});

it('creates, runs and deletes an E2B sandbox for a one-off run', function () {
    Http::fake([
        'https://api.e2b.test/sandboxes' => Http::response(['sandboxID' => 'sbx9', 'envdAccessToken' => 'envd']),
        'https://49999-sbx9.e2b.test/execute' => Http::response(json_encode(['type' => 'stdout', 'text' => "2\n"])),
        'https://api.e2b.test/sandboxes/sbx9' => Http::response([]),
    ]);

    $result = (new E2BSandboxDriver(SandboxProvider::factory()->make()))->runOnce('python', 'print(1+1)', 30);

    expect($result->stdout)->toBe("2\n");

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/sandboxes/sbx9'));
});

it('proves a provider works with a test program, and says what failed', function () {
    Http::fake(['https://93.184.216.36/*' => Http::sequence()
        ->push(['run' => ['stdout' => "2\n", 'stderr' => '', 'code' => 0]])
        ->push(['run' => ['stdout' => "5\n", 'stderr' => '', 'code' => 0]])]);
    $driver = new PistonSandboxDriver(SandboxProvider::factory()->piston()->make());

    $driver->check();

    expect(fn () => $driver->check())->toThrow(RuntimeException::class, 'expected answer');
});

it('only accepts a sandbox address on a separate public machine', function (string $url, string $fragment) {
    config(['app.url' => 'https://93.184.216.40', 'app.frontend_url' => 'https://93.184.216.41']);

    expect(app(SandboxUrlGuard::class)->problem($url))->toContain($fragment);
})->with([
    'this app server' => ['https://93.184.216.40/runner', 'separate machine'],
    'the frontend host' => ['https://93.184.216.41', 'separate machine'],
    'localhost' => ['https://localhost:9000', 'private or reserved'],
    'a private address' => ['https://10.0.0.8', 'private or reserved'],
    'the metadata address' => ['https://169.254.169.254', 'private or reserved'],
    'a key in the URL' => ['https://user:pw@93.184.216.50', 'API key field'],
]);

it('accepts a separate public machine', function () {
    expect(app(SandboxUrlGuard::class)->problem('https://93.184.216.50'))->toBeNull();
});

it('drives a Daytona sandbox through its whole life', function () {
    Http::fake([
        'https://93.184.216.60/api/sandbox' => Http::response(['id' => 'dtn-1', 'state' => 'started']),
        'https://93.184.216.61/toolbox/dtn-1/process/execute' => Http::response(['exitCode' => 0, 'result' => "hi\n"]),
        'https://93.184.216.61/toolbox/dtn-1/files/download*' => Http::response('file-bytes'),
        'https://93.184.216.60/api/sandbox/dtn-1' => Http::sequence()
            ->push(['id' => 'dtn-1', 'state' => 'started'])
            ->push([]),
    ]);
    $driver = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make(['credentials' => ['api_key' => 'dtn-secret']]));

    $created = $driver->create(900);
    $result = $driver->run($created['id'], $created['access_token'], 'python', 'print("hi")', 30);

    expect($created)->toBe(['id' => 'dtn-1', 'access_token' => null])
        ->and($result)->stdout->toBe("hi\n")->error->toBeNull()
        ->and($driver->readFile('dtn-1', null, '/tmp/a.txt', 1000))->toBe('file-bytes');

    $driver->keepAlive('dtn-1', 900);
    $driver->destroy('dtn-1', null);

    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/api/sandbox')
        && $request->hasHeader('Authorization', 'Bearer dtn-secret')
        && $request['autoStopInterval'] === 15
        && $request['autoDeleteInterval'] === 0);
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/process/execute')
        && str_contains($request['command'], base64_encode('print("hi")'))
        && str_contains($request['command'], 'timeout 30 python3'));
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/api/sandbox/dtn-1'));
});

it('reports a Daytona program that failed or ran out of time', function () {
    Http::fake(['*/process/execute' => Http::sequence()
        ->push(['exitCode' => 1, 'result' => 'Traceback: boom'])
        ->push(['exitCode' => 124, 'result' => ''])]);
    $driver = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make());

    expect($driver->run('dtn-1', null, 'python', 'x', 5)->error)->toContain('Exited with code 1')->toContain('boom')
        ->and($driver->run('dtn-1', null, 'js', 'x', 5)->error)->toBe('The code ran out of time.');
});

it('treats a missing or stopped Daytona sandbox as gone', function () {
    Http::fake([
        '*/process/execute' => Http::response(['message' => 'sandbox not found'], 404),
        '*/api/sandbox/stopped' => Http::response(['id' => 'stopped', 'state' => 'stopped']),
    ]);
    $driver = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make());

    expect(fn () => $driver->run('dtn-1', null, 'python', 'x', 5))->toThrow(SandboxGoneException::class)
        ->and(fn () => $driver->keepAlive('stopped', 60))->toThrow(SandboxGoneException::class);
});

it('treats a busy, down or failed-to-start Daytona as a start failure', function () {
    $driver = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make());
    Http::fake(['*' => Http::sequence()
        ->push('busy', 429)
        ->push(['id' => 'bad', 'state' => 'error'])
        ->whenEmpty(Http::response([]))]);

    expect(fn () => $driver->create(60))->toThrow(SandboxStartException::class, 'could not start')
        ->and(fn () => $driver->create(60))->toThrow(SandboxStartException::class, 'did not start');
});

it('deletes the Daytona sandbox after a one-off run, even if it failed', function () {
    Http::fake([
        'https://93.184.216.60/api/sandbox' => Http::response(['id' => 'once', 'state' => 'started']),
        'https://93.184.216.61/toolbox/once/process/execute' => Http::response(['message' => 'crashed'], 500),
        'https://93.184.216.60/api/sandbox/once' => Http::response([]),
    ]);

    expect(fn () => (new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make()))->runOnce('python', 'x', 10))
        ->toThrow(RuntimeException::class);

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/api/sandbox/once'));
});

it('uses Daytona Cloud addresses when none are set, and refuses a private override', function () {
    Http::fake(['*' => Http::response(['id' => 'c1', 'state' => 'started'])]);

    (new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make(['config' => []])))->create(60);

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://app.daytona.io/api/sandbox');

    $private = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make(['config' => ['toolbox_url' => 'https://10.0.0.5/toolbox']]));

    expect(fn () => $private->create(60))->toThrow(SandboxStartException::class, 'not allowed');
});

it('refuses code too long to send to Daytona', function () {
    Http::fake();
    $driver = new DaytonaSandboxDriver(SandboxProvider::factory()->daytona()->make());

    expect(fn () => $driver->run('dtn-1', null, 'python', str_repeat('a', 80000), 5))->toThrow(RuntimeException::class, 'too long');

    Http::assertNothingSent();
});

it('is chosen for conversation computers like E2B', function () {
    config(['assistant.sandbox.api_key' => null]);
    SandboxProvider::factory()->daytona()->create(['key' => 'daytona']);

    expect(app(SandboxRouter::class)->candidates(SandboxMode::Persistent)->pluck('key')->all())->toBe(['daytona']);
});
