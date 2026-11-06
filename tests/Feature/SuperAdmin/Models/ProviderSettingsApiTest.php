<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Enums\Workspaces\Role;
use App\Jobs\Ai\NotifyAiProviderAvailabilityJob;
use App\Models\Agents\Agent;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\AiProviderSetting;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Notifications\Ai\AiProviderAvailabilityNotification;
use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use App\Services\Ai\AiProviderSettings;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['ai.providers.openai.key' => null, 'byok.providers.huggingface.connect.client_id' => null]);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
});

function patchProvider(string $provider, array $body)
{
    return test()->withHeaders(test()->headers)->patchJson(saUrl("models/providers/{$provider}"), $body);
}

it('lists each provider\'s customer-key switches and key counts', function () {
    AiProviderCredential::factory()->create();
    AiProviderCredential::factory()->invalid()->create();

    $providers = collect($this->withHeaders($this->headers)->getJson(saUrl('models/providers'))->assertOk()->json('data'))->keyBy('key');

    expect($providers['openai']['byok'])->toMatchArray(['enabled' => true, 'keys' => ['total' => 2, 'valid' => 1]])
        // Sign in with ChatGPT: off until OpenAI approves the app.
        ->and($providers['openai']['byok']['connect'])->toMatchArray(['enabled' => false, 'needs_login_app' => true])
        ->and($providers['anthropic']['byok']['connect'])->toBeNull()
        ->and($providers['openrouter']['byok']['connect'])->toMatchArray(['configured' => true, 'needs_login_app' => false])
        ->and($providers['huggingface']['byok']['connect'])->toMatchArray(['enabled' => true, 'configured' => false, 'needs_login_app' => true]);
});

it('turns customer keys off only with a fresh code, audits it and raises an alert', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test']]);

    patchProvider('anthropic', ['byok_enabled' => false, 'reason' => 'Anthropic outage, pausing customer keys'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    patchProvider('anthropic', stepUp($this->operator, ['byok_enabled' => false, 'disabled_message' => 'Back after the outage.', 'reason' => 'Anthropic outage, pausing customer keys']))
        ->assertOk();

    $settings = app(AiProviderSettings::class);
    $log = SuperAdminAuditLog::query()->where('action', 'models.provider.updated')->sole();

    expect($settings->byokEnabled('anthropic'))->toBeFalse()
        ->and($settings->disabledMessage('anthropic'))->toBe('Back after the outage.')
        ->and($log->before['byok_enabled'])->toBeTrue()
        ->and($log->after['byok_enabled'])->toBeFalse()
        ->and($log->reason)->toBe('Anthropic outage, pausing customer keys');
    Notification::assertSentOnDemandTimes(SuperAdminAlertNotification::class, 1);
});

it('turns customer keys back on, and changes display settings, with just a reason', function () {
    AiProviderSetting::query()->create(['provider' => 'anthropic', 'byok_enabled' => false]);

    patchProvider('anthropic', ['byok_enabled' => true, 'featured' => true, 'sort_order' => 2, 'free_tier_note' => 'Try it', 'reason' => 'The outage is over now'])
        ->assertOk();

    $settings = app(AiProviderSettings::class);
    expect($settings->byokEnabled('anthropic'))->toBeTrue()
        ->and($settings->featured('anthropic'))->toBeTrue()
        ->and($settings->sortOrder('anthropic'))->toBe(2)
        ->and($settings->freeTierNote('anthropic'))->toBe('Try it');

    patchProvider('anthropic', ['featured' => false])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('refuses one-click for a provider that has none, and a provider customers cannot bring a key for', function () {
    patchProvider('anthropic', ['connect_enabled' => true, 'reason' => 'Try one-click for it'])->assertStatus(422);
    patchProvider('xkiro', ['featured' => true, 'reason' => 'Not a BYOK provider'])->assertNotFound();
    patchProvider('openrouter', ['connect_enabled' => false, 'reason' => 'One-click is off for now'])->assertOk();

    expect(app(AiProviderSettings::class)->connectEnabled('openrouter'))->toBeFalse();
});

it('tells the workspaces with a key only when asked, by email only when asked', function (string $notify, bool $queued, bool $email) {
    Bus::fake([NotifyAiProviderAvailabilityJob::class]);

    patchProvider('openai', stepUp($this->operator, ['byok_enabled' => false, 'notify' => $notify, 'reason' => 'Cost review for OpenAI keys']))->assertOk();

    if ($queued) {
        Bus::assertDispatched(NotifyAiProviderAvailabilityJob::class, fn ($job): bool => $job->provider === 'openai' && ! $job->available && $job->sendEmail === $email);
    } else {
        Bus::assertNotDispatched(NotifyAiProviderAvailabilityJob::class);
    }
})->with([
    'nobody' => ['none', false, false],
    'in-app only' => ['in_app', true, false],
    'in-app and email' => ['email', true, true],
]);

it('notifies the admins of a workspace with a team key and the owner of a personal key, once each', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    AiProviderCredential::factory()->forWorkspace($workspace)->create(['created_by' => $owner->id]);
    AiProviderCredential::factory()->forWorkspace($workspace)->personal($member)->create();
    AiProviderCredential::factory()->provider('anthropic')->create();

    NotifyAiProviderAvailabilityJob::dispatchSync('openai', false, true, 'Back soon.');

    Notification::assertSentToTimes($owner, AiProviderAvailabilityNotification::class, 1);
    Notification::assertSentTo($member, AiProviderAvailabilityNotification::class, fn ($notification, array $channels): bool => in_array('mail', $channels, true)
        && $notification->title === 'OpenAI is turned off for now'
        && str_starts_with((string) $notification->body, 'Back soon.'));
    Notification::assertCount(2);
});

it('sends the notice in-app only when email is not asked for', function () {
    Notification::fake();
    $credential = AiProviderCredential::factory()->create();

    NotifyAiProviderAvailabilityJob::dispatchSync('openai', true, false);

    Notification::assertSentTo($credential->workspace->owner, AiProviderAvailabilityNotification::class, fn ($notification, array $channels): bool => ! in_array('mail', $channels, true)
        && $notification->title === 'OpenAI is available again');
});

it('shows what turning customer keys off would touch', function () {
    $credential = AiProviderCredential::factory()->provider('huggingface')->create();
    $onlyHf = ModelCatalog::factory()->create(['display_name' => 'Llama on HF']);
    ModelRoute::factory()->forCatalog($onlyHf)->create(['execution_provider' => 'huggingface', 'execution_model_id' => 'meta/llama', 'is_enabled' => true]);
    $elsewhere = ModelCatalog::factory()->create(['display_name' => 'Also on OpenRouter']);
    ModelRoute::factory()->forCatalog($elsewhere)->create(['execution_provider' => 'huggingface', 'execution_model_id' => 'm', 'is_enabled' => true]);
    ModelRoute::factory()->forCatalog($elsewhere)->create(['execution_provider' => 'openrouter', 'execution_model_id' => 'm', 'is_enabled' => true]);
    $agent = Agent::factory()->create(['workspace_id' => $credential->workspace_id, 'name' => 'Recap bot', 'model_catalog_id' => $onlyHf->id]);

    $this->withHeaders($this->headers)->getJson(saUrl('models/providers/huggingface/impact'))
        ->assertOk()
        ->assertJsonPath('data.workspaces', 1)
        ->assertJsonPath('data.keys', ['total' => 1, 'valid' => 1])
        ->assertJsonPath('data.models_only_on_provider', [['id' => $onlyHf->id, 'display_name' => 'Llama on HF']])
        ->assertJsonPath('data.agents.total', 1)
        ->assertJsonPath('data.agents.sample.0.id', $agent->id);
});

it('stores a login app with a fresh code, never shows it, and can forget it again', function () {
    $this->withHeaders($this->headers)->putJson(saUrl('models/providers/huggingface/connect-credentials'), ['client_id' => 'hf-client-123456', 'reason' => 'Our Hugging Face app'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)->putJson(saUrl('models/providers/huggingface/connect-credentials'), stepUp($this->operator, ['client_id' => 'hf-client-123456', 'client_secret' => 'hf-secret', 'reason' => 'Our Hugging Face app']))
        ->assertOk()
        ->assertJsonMissing(['hf-client-123456'])
        ->assertJsonMissing(['hf-secret']);

    $listing = $this->withHeaders($this->headers)->getJson(saUrl('models/providers'))->assertOk();
    $listing->assertJsonMissing(['hf-secret']);

    expect(app(AiProviderSettings::class)->connectClient('huggingface'))->toBe(['client_id' => 'hf-client-123456', 'client_secret' => 'hf-secret'])
        ->and(collect($listing->json('data'))->firstWhere('key', 'huggingface')['byok']['connect'])->toMatchArray(['configured' => true, 'login_app_stored' => true])
        ->and(json_encode(SuperAdminAuditLog::query()->where('action', 'models.provider.connect_credentials_set')->sole()->after))->not->toContain('hf-secret');

    $this->withHeaders($this->headers)->deleteJson(saUrl('models/providers/huggingface/connect-credentials'), stepUp($this->operator, ['reason' => 'Rotating the app']))->assertOk();
    expect(app(AiProviderSettings::class)->connectClient('huggingface')['client_id'])->toBeNull();

    $this->withHeaders($this->headers)->putJson(saUrl('models/providers/openrouter/connect-credentials'), stepUp($this->operator, ['client_id' => 'x', 'reason' => 'OpenRouter needs none']))->assertStatus(422);
});

it('lets a read-only super admin see the impact but not change anything', function () {
    $viewer = superAdmin(SuperAdminRole::Support);
    $headers = superAdminHeaders($viewer);

    $this->withHeaders($headers)->getJson(saUrl('models/providers/openai/impact'))->assertOk();
    $this->withHeaders($headers)->patchJson(saUrl('models/providers/openai'), ['featured' => true, 'reason' => 'Should not be allowed'])->assertForbidden();
});
