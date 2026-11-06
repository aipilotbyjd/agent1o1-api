<?php

use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Secrets\PlatformSecrets;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\AiManager;

function platformSecretsWorkspace(): Workspace
{
    return app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
}

it('scrubs the platform\'s provider keys out of an error message', function () {
    config(['ai.providers.anthropic.key' => 'platform-anthropic-secret-value']);

    $message = app(PlatformSecrets::class)->redact('Request failed with key platform-anthropic-secret-value (platform-anthropic-secret-value)');

    expect($message)->not->toContain('platform-anthropic-secret-value')
        ->and($message)->toContain('[redacted]');
});

it('scrubs a platform key that comes back URL-encoded or in a different case', function () {
    config(['ai.providers.gemini.key' => 'Plat/Form+Gemini=Key']);

    $message = app(PlatformSecrets::class)->redact('cURL error 28 for https://example.test/v1?x='.rawurlencode('Plat/Form+Gemini=Key').' and PLAT/FORM+GEMINI=KEY');

    expect($message)->not->toContain(rawurlencode('Plat/Form+Gemini=Key'))
        ->and(strtolower($message))->not->toContain('plat/form+gemini=key');
});

it('scrubs cloud credentials, OAuth client secrets and the app key', function () {
    config([
        'ai.providers.bedrock.secret_access_key' => 'aws-secret-access-key-value',
        'byok.providers.openai.connect.client_secret' => 'chatgpt-client-secret-value',
    ]);
    $appKey = (string) config('app.key');

    $message = app(PlatformSecrets::class)->redact("aws-secret-access-key-value chatgpt-client-secret-value {$appKey}");

    expect($message)->not->toContain('aws-secret-access-key-value')
        ->and($message)->not->toContain('chatgpt-client-secret-value')
        ->and($message)->not->toContain($appKey);
});

it('scrubs credentials from any config file, such as the assistant sandbox key', function () {
    config(['assistant.sandbox.api_key' => 'e2b-sandbox-key-value']);

    expect(app(PlatformSecrets::class)->redact('Sandbox refused e2b-sandbox-key-value'))->not->toContain('e2b-sandbox-key-value');
});

it('scrubs the key of an operator endpoint', function () {
    AiEndpoint::factory()->create(['api_key' => 'operator-endpoint-key-value']);

    expect(app(PlatformSecrets::class)->redact('Unauthorized: operator-endpoint-key-value'))->not->toContain('operator-endpoint-key-value');
});

it('scrubs key-shaped strings and credentials in a query string', function (string $leak) {
    $message = app(PlatformSecrets::class)->redact("Provider said: {$leak} end");

    expect($message)->not->toContain($leak)
        ->and($message)->toContain('[redacted]');
})->with([
    'openai project key' => 'sk-proj-abcdefghijklmnopqrstuvwxyz0123456789',
    'masked openai key' => 'sk-proj-************************wxyz',
    'anthropic key' => 'sk-ant-api03-abcdefghijklmnopqrstuvwxyz',
    'openrouter key' => 'sk-or-v1-0123456789abcdef0123456789abcdef',
    'gemini key' => 'AIzaSyA1234567890abcdefghijklmnopqrstu',
    'groq key' => 'gsk_abcdefghijklmnopqrstuvwxyz0123',
    'xai key' => 'xai-abcdefghijklmnopqrstuvwxyz0123',
    'hugging face token' => 'hf_abcdefghijklmnopqrstuvwxyz0123',
    'stripe secret key' => 'sk_live_abcdefghijklmnop',
    'bearer token' => 'Bearer test-token-not-a-real-one-0000',
    'query string key' => '?key=AnyValueAtAll123',
]);

it('leaves ordinary text alone', function () {
    $message = 'The model is busy right now. Try again in 30 seconds or pick another model.';

    expect(app(PlatformSecrets::class)->redact($message))->toBe($message);
});

it('builds a workspace key without any of the platform provider\'s credentials', function () {
    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => 'platform-openai-key',
        'url' => 'https://api.openai.com/v1',
        'store' => true,
        'headers' => ['X-Platform-Secret' => 'platform-header-secret'],
        'access_key_id' => 'platform-access-key-id',
    ]]);
    $workspace = platformSecretsWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->default()->create(['data' => ['api_key' => 'sk-workspace']]);

    app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    $built = app(AiManager::class)->getInstanceConfig("byok-{$credential->id}");

    expect($built)->toMatchArray(['driver' => 'openai', 'key' => 'sk-workspace', 'url' => 'https://api.openai.com/v1', 'store' => false])
        ->and($built)->not->toHaveKey('headers')
        ->and($built)->not->toHaveKey('access_key_id')
        ->and(json_encode($built))->not->toContain('platform-');
});

it('never lets the provider store a call made on a workspace key', function () {
    config(['ai.providers.openai.store' => true]);
    $workspace = platformSecretsWorkspace();
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->default()->create();

    app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $workspace->id, null);

    expect(app(AiManager::class)->getInstanceConfig("byok-{$credential->id}")['store'])->toBeFalse();
});
