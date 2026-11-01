<?php

use App\Exceptions\Http\BlockedUrlException;
use App\Models\Runs\Run;
use App\Nodes\DataTransform\CallApiNode;
use App\Nodes\DataTransform\RunCodeNode;
use App\Nodes\DataTransform\TransformNode;
use App\Services\Http\SsrfGuard;
use Illuminate\Support\Facades\Http;

it('maps output keys to dot paths into the context', function () {
    $node = new TransformNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'mapping' => ['name' => 'input.user.name'],
    ], [
        'input' => ['user' => ['name' => 'Ada']],
    ]);

    expect($output)->toBe(['name' => 'Ada']);
});

it('runs only whitelisted RunCodeNode operations', function () {
    $node = new RunCodeNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'operations' => [
            ['op' => 'set', 'output' => 'greeting', 'value' => 'hi'],
            ['op' => 'copy', 'output' => 'name', 'path' => 'input.name'],
            ['op' => 'uppercase', 'output' => 'shout', 'path' => 'input.name'],
            ['op' => 'concat', 'output' => 'full', 'paths' => ['input.first', 'input.last']],
        ],
    ], [
        'input' => ['name' => 'ada', 'first' => 'Ada', 'last' => 'Lovelace'],
    ]);

    expect($output)->toBe([
        'greeting' => 'hi',
        'name' => 'ada',
        'shout' => 'ADA',
        'full' => 'AdaLovelace',
    ]);
});

it('rejects a RunCodeNode operation outside the whitelist', function () {
    $node = new RunCodeNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'operations' => [['op' => 'eval', 'output' => 'x']],
    ], []);
})->throws(InvalidArgumentException::class);

it('makes the request when a Call API URL resolves to a public address', function () {
    Http::fake(['https://api.example.com/*' => Http::response(['ok' => true], 200)]);

    $node = new CallApiNode(new SsrfGuard(fn () => ['93.184.216.34']));
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'method' => 'GET',
        'url' => 'https://api.example.com/status',
    ], []);

    expect($output['status'])->toBe(200)
        ->and($output['body'])->toBe(['ok' => true]);
});

it('blocks a Call API request to a loopback address', function () {
    $node = new CallApiNode(new SsrfGuard);
    $run = Run::factory()->create();

    $node->execute($run, [
        'method' => 'GET',
        'url' => 'http://127.0.0.1/admin',
    ], []);
})->throws(BlockedUrlException::class);

it('blocks a Call API request to the cloud metadata address', function () {
    $node = new CallApiNode(new SsrfGuard);
    $run = Run::factory()->create();

    $node->execute($run, [
        'method' => 'GET',
        'url' => 'http://169.254.169.254/latest/meta-data/',
    ], []);
})->throws(BlockedUrlException::class);

it('blocks a Call API request to a private-network address', function () {
    $node = new CallApiNode(new SsrfGuard);
    $run = Run::factory()->create();

    $node->execute($run, [
        'method' => 'GET',
        'url' => 'http://10.0.0.5/internal',
    ], []);
})->throws(BlockedUrlException::class);

it('blocks a Call API request using a disallowed scheme', function () {
    $node = new CallApiNode(new SsrfGuard);
    $run = Run::factory()->create();

    $node->execute($run, [
        'method' => 'GET',
        'url' => 'file:///etc/passwd',
    ], []);
})->throws(BlockedUrlException::class);

it('blocks a Call API redirect that points at a private address', function () {
    Http::fake([
        'https://api.example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);

    $node = new CallApiNode(new SsrfGuard(fn (string $host) => $host === 'api.example.com' ? ['93.184.216.34'] : []));
    $run = Run::factory()->create();

    $node->execute($run, [
        'method' => 'GET',
        'url' => 'https://api.example.com/redirect',
    ], []);
})->throws(BlockedUrlException::class);
