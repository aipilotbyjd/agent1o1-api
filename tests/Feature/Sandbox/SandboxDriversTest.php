<?php

use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\Drivers\E2BSandboxDriver;
use App\Services\Sandbox\Drivers\PistonSandboxDriver;
use App\Services\Sandbox\Drivers\RunnerSandboxDriver;
use App\Services\Sandbox\SandboxGoneException;
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
