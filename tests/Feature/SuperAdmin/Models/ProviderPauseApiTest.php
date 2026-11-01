<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\AiEndpoint;
use App\Models\Ai\AiProviderPause;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use App\Services\Ai\ModelCatalogResolver;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key']);

    $this->operator = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->operator);
});

it('pauses a provider at once, with a reason and a fresh code', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/pause'), stepUp($this->operator, ['reason' => 'Our OpenAI key leaked on GitHub']))
        ->assertOk()
        ->assertJsonPath('data.provider', 'openai')
        ->assertJsonPath('data.reason', 'Our OpenAI key leaked on GitHub');

    expect(ModelCatalogResolver::providerIsConfigured('openai'))->toBeFalse();

    $log = SuperAdminAuditLog::query()->where('action', 'models.provider.paused')->firstOrFail();

    expect($log->reason)->toBe('Our OpenAI key leaked on GitHub')->and($log->after)->toBe(['provider' => 'openai']);
});

it('refuses without a fresh code, for an unknown provider, and when already paused', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/pause'), ['reason' => 'Our OpenAI key leaked'])
        ->assertUnprocessable()->assertJsonValidationErrors('totp_code');

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/made-up/pause'), stepUp($this->operator))->assertNotFound();

    expect(AiProviderPause::query()->count())->toBe(0);

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/pause'), stepUp($this->operator))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/pause'), stepUp($this->operator))->assertStatus(409);
});

it('can pause one of the operator\'s own endpoints too', function () {
    AiEndpoint::factory()->create(['slug' => 'my-vllm']);

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/my-vllm/pause'), stepUp($this->operator))->assertOk();

    expect(ModelCatalogResolver::providerIsConfigured('my-vllm'))->toBeFalse();
});

it('resumes a provider with just a reason', function () {
    AiProviderPause::query()->create(['provider' => 'openai', 'reason' => 'Outage', 'paused_at' => now()]);

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/resume'), ['reason' => 'The outage is over'])->assertOk();

    expect(ModelCatalogResolver::providerIsConfigured('openai'))->toBeTrue()
        ->and(SuperAdminAuditLog::query()->where('action', 'models.provider.resumed')->firstOrFail()->before)->toMatchArray(['provider' => 'openai', 'paused_for' => 'Outage']);

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/resume'), ['reason' => 'The outage is over'])->assertStatus(409);
    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/resume'))->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('tells the people who watch for alerts, both ways', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test']]);

    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/pause'), stepUp($this->operator, ['reason' => 'Our OpenAI key leaked']))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl('models/providers/openai/resume'), ['reason' => 'New key is in place'])->assertOk();

    Notification::assertSentOnDemandTimes(SuperAdminAlertNotification::class, 2);
});

it('shows the pause on the providers list', function () {
    AiProviderPause::query()->create(['provider' => 'openai', 'reason' => 'Outage', 'paused_at' => now()]);

    $openai = collect($this->withHeaders($this->headers)->getJson(saUrl('models/providers'))->json('data'))->firstWhere('key', 'openai');

    expect($openai)->toMatchArray(['paused' => true, 'paused_reason' => 'Outage', 'configured' => true]);
});

it('keeps the kill switch to operators and owners', function (SuperAdminRole $role) {
    $admin = superAdmin($role);

    $this->withHeaders(superAdminHeaders($admin))->postJson(saUrl('models/providers/openai/pause'), stepUp($admin))->assertForbidden();
    $this->withHeaders(superAdminHeaders($admin))->postJson(saUrl('models/providers/openai/resume'), ['reason' => 'Trying it out'])->assertForbidden();
})->with([[SuperAdminRole::Finance], [SuperAdminRole::Support], [SuperAdminRole::Viewer]]);
