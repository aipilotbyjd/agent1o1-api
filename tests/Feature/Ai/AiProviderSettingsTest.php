<?php

use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\AiProviderSetting;
use App\Models\User;
use App\Services\Ai\AiProviderSettings;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'ai.providers.openai.key' => 'platform-openai-key',
        'byok.providers.openrouter.connect.enabled' => true,
        'byok.providers.huggingface.connect.client_id' => null,
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

function providerSettingsList(): array
{
    return test()->getJson('/api/v1/workspaces/'.test()->workspace->id.'/ai-providers')->assertOk()->json('data.providers');
}

it('keeps every provider on with its config defaults when nothing has been switched', function () {
    $settings = app(AiProviderSettings::class);

    expect($settings->enabledProviders())->toBe(array_keys(config('byok.providers')))
        ->and($settings->connectEnabled('openrouter'))->toBeTrue()
        ->and($settings->freeTierNote('gemini'))->toBe(config('byok.providers.gemini.free_tier'))
        ->and($settings->byokEnabled('not-a-provider'))->toBeFalse();
});

it('hides a switched-off provider from customers and lists featured providers first', function () {
    AiProviderSetting::query()->create(['provider' => 'anthropic', 'byok_enabled' => false]);
    AiProviderSetting::query()->create(['provider' => 'groq', 'featured' => true, 'free_tier_note' => 'Fast and free to start.']);
    Passport::actingAs($this->owner);

    $providers = collect(providerSettingsList());

    expect($providers->pluck('key'))->not->toContain('anthropic')
        ->and($providers->first())->toMatchArray(['key' => 'groq', 'featured' => true, 'free_tier' => 'Fast and free to start.']);
});

it('refuses a new key for a switched-off provider with the message the super admin wrote', function () {
    AiProviderSetting::query()->create(['provider' => 'anthropic', 'byok_enabled' => false, 'disabled_message' => 'Anthropic is paused for maintenance.']);
    Http::fake();
    Passport::actingAs($this->owner);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-provider-credentials", [
        'execution_provider' => 'anthropic',
        'api_key' => 'sk-ant-api03-valid-looking-key',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['execution_provider' => 'Anthropic is paused for maintenance.']);

    Http::assertNothingSent();
    expect(AiProviderCredential::count())->toBe(0);
});

it('keeps existing keys of a switched-off provider unused, and uses them again once it is back on', function () {
    $key = AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    $registrar = app(ByokProviderRegistrar::class);
    $setting = AiProviderSetting::query()->create(['provider' => 'openai', 'byok_enabled' => false]);

    [$off] = $registrar->apply(['openai' => 'gpt-4o'], null, $this->workspace->id, null);

    $setting->update(['byok_enabled' => true]);
    [$on] = app(ByokProviderRegistrar::class)->apply(['openai' => 'gpt-4o'], null, $this->workspace->id, null);

    expect($off)->toBe(['openai' => 'gpt-4o'])
        ->and($on)->toBe(["byok-{$key->id}" => 'gpt-4o'])
        ->and($key->fresh())->not->toBeNull();
});

it('marks a customer key not in use while its provider is switched off', function () {
    AiProviderCredential::factory()->forWorkspace($this->workspace)->create();
    AiProviderSetting::query()->create(['provider' => 'openai', 'byok_enabled' => false, 'disabled_message' => 'Back soon.']);
    Passport::actingAs($this->owner);

    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/ai-provider-credentials")
        ->assertOk()
        ->assertJsonPath('data.ai_provider_credentials.0.provider_disabled', true)
        ->assertJsonPath('data.ai_provider_credentials.0.provider_disabled_message', 'Back soon.');
});

it('turns one-click connect off on its own, and never offers it while customer keys are off', function () {
    Passport::actingAs($this->owner);

    AiProviderSetting::query()->create(['provider' => 'openrouter', 'connect_enabled' => false]);
    $connectOff = collect(providerSettingsList())->firstWhere('key', 'openrouter');

    AiProviderSetting::query()->where('provider', 'openrouter')->first()->update(['connect_enabled' => true, 'byok_enabled' => false]);

    expect($connectOff['connect'])->toBeNull()
        ->and(app(AiProviderSettings::class)->connectEnabled('openrouter'))->toBeFalse();

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/openrouter/connect")->assertUnprocessable();
});

it('offers Hugging Face one-click once a super admin stores its login app, without any env setting', function () {
    Passport::actingAs($this->owner);

    expect(collect(providerSettingsList())->firstWhere('key', 'huggingface')['connect'])->toBeNull();

    AiProviderSetting::query()->create(['provider' => 'huggingface', 'connect_client_id' => 'hf-stored-client', 'connect_client_secret' => 'hf-stored-secret']);

    $start = $this->postJson("/api/v1/workspaces/{$this->workspace->id}/ai-providers/huggingface/connect")->assertOk();

    expect(collect(providerSettingsList())->firstWhere('key', 'huggingface')['connect'])->toBe(['label' => 'Connect with Hugging Face'])
        ->and($start->json('data.authorize_url'))->toContain('client_id=hf-stored-client')
        ->and(AiProviderSetting::query()->first()->getRawOriginal('connect_client_secret'))->not->toBe('hf-stored-secret');
});
