<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Jobs\Ai\CheckAiProviderCredentialJob;
use App\Jobs\Ai\RefreshAiProviderTokenJob;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Notifications\Ai\AiProviderCredentialInvalidNotification;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'byok.providers.huggingface.connect.client_id' => 'hf-client-id',
        'byok.providers.huggingface.connect.client_secret' => 'hf-client-secret',
        'ai.providers.huggingface.key' => null,
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('queues a renewal only for one-click connections that expire soon', function () {
    Bus::fake();
    $expiring = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')->oauth(expiresAt: now()->addMinutes(5))->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')->oauth(expiresAt: now()->addHours(3))->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')->oauth(expiresAt: now()->subMinute())->invalid()->create();
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create();

    $this->artisan('ai-credentials:refresh-tokens')->assertSuccessful();

    Bus::assertDispatchedTimes(RefreshAiProviderTokenJob::class, 1);
    Bus::assertDispatched(RefreshAiProviderTokenJob::class, fn ($job) => $job->credential->id === $expiring->id);
});

it('renews an expiring token and keeps the refresh token when none is re-issued', function () {
    Http::fake(['huggingface.co/oauth/token' => Http::response(['access_token' => 'hf_oauth_renewed5678', 'expires_in' => 3600])]);
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')
        ->oauth('hf_refresh_token', now()->addMinutes(5))->create();

    RefreshAiProviderTokenJob::dispatchSync($credential);

    Http::assertSent(fn (HttpRequest $request): bool => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'hf_refresh_token'
        && $request['client_secret'] === 'hf-client-secret');

    $credential->refresh();
    expect($credential->apiKey())->toBe('hf_oauth_renewed5678')
        ->and($credential->refreshToken())->toBe('hf_refresh_token')
        ->and($credential->validation_status)->toBe(AiProviderCredentialStatus::Valid)
        ->and($credential->key_hint)->toBe('hf_o…5678')
        ->and($credential->token_expires_at->isAfter(now()->addMinutes(50)))->toBeTrue();
});

it('takes a connection that cannot be renewed out of rotation and asks for a reconnect', function () {
    Notification::fake();
    Http::fake(['huggingface.co/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')
        ->oauth(expiresAt: now()->addMinutes(5))->create(['created_by' => $this->owner->id]);

    RefreshAiProviderTokenJob::dispatchSync($credential);

    expect($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Invalid);
    Notification::assertSentTo($this->owner, AiProviderCredentialInvalidNotification::class, fn ($notification): bool => $notification->title === 'Hugging Face connection "'.$credential->name.'" stopped working'
        && str_contains((string) $notification->body, 'Reconnect it'));
});

it('renews an expired token before the background check, instead of calling it revoked', function () {
    Http::fake([
        'huggingface.co/oauth/token' => Http::response(['access_token' => 'hf_oauth_fresh0000', 'refresh_token' => 'hf_rotated', 'expires_in' => 3600]),
        'huggingface.co/api/whoami-v2' => Http::response(['name' => 'jay']),
    ]);
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')
        ->oauth(expiresAt: now()->subMinute())->create();

    CheckAiProviderCredentialJob::dispatchSync($credential);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://huggingface.co/api/whoami-v2'
        && $request->hasHeader('Authorization', 'Bearer hf_oauth_fresh0000'));
    expect($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Valid)
        ->and($credential->fresh()->refreshToken())->toBe('hf_rotated');
});

it('renews a token about to expire at call time, and leaves out one that cannot be renewed', function () {
    $registrar = app(ByokProviderRegistrar::class);
    $credential = AiProviderCredential::factory()->forWorkspace($this->workspace)->provider('huggingface')
        ->oauth(expiresAt: now()->addSeconds(30))->create();

    Http::fake(['huggingface.co/oauth/token' => Http::sequence()
        ->push(['access_token' => 'hf_oauth_calltime1111', 'expires_in' => 3600])
        ->push(['error' => 'invalid_grant'], 400)]);
    [$provider] = $registrar->apply(['huggingface' => 'meta-llama/Llama-3.1-405B-Instruct'], null, $this->workspace->id, null);

    expect($provider)->toBe(["byok-{$credential->id}" => 'meta-llama/Llama-3.1-405B-Instruct'])
        ->and($credential->fresh()->apiKey())->toBe('hf_oauth_calltime1111');

    $credential->forceFill(['token_expires_at' => now()->subMinute()])->save();

    [$fallback] = $registrar->apply(['huggingface' => 'm', 'openai' => 'gpt-4o'], null, $this->workspace->id, null);

    expect($fallback)->not->toHaveKey("byok-{$credential->id}")
        ->and($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Invalid);
});
