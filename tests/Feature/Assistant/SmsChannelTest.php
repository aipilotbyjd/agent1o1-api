<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\ThreadRouterAgent;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Models\Assistant\AssistantPhoneNumber;
use App\Models\Assistant\AssistantPhoneVerification;
use App\Models\Assistant\AssistantSession;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Services\Assistant\Channels\SmsChannel;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    config([
        'assistant.channels.sms.account_sid' => 'AC1',
        'assistant.channels.sms.auth_token' => 'twilio-secret',
        'assistant.channels.sms.from' => '+15550001111',
        'app.frontend_url' => 'https://app.example.test',
    ]);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'])]);

    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    PlanGrant::factory()->forWorkspace($this->workspace)
        ->forPlan(Plan::factory()->create(['features' => ['assistant_sms' => true]]))
        ->active()
        ->create();
    $this->owner->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant/channels";

    $this->verified = fn () => AssistantPhoneNumber::query()->create(['user_id' => $this->owner->id, 'phone' => '+14155550123', 'verified_at' => now()]);

    $this->twilioPost = function (array $fields, ?string $signature = null) {
        $url = url('/api/hooks/assistant/sms');
        ksort($fields);
        $payload = $url.collect($fields)->map(fn ($value, $key) => $key.$value)->implode('');
        $signature ??= base64_encode(hash_hmac('sha1', $payload, 'twilio-secret', true));

        return $this->post('/api/hooks/assistant/sms', $fields, ['X-Twilio-Signature' => $signature]);
    };

    $this->texts = fn (): array => collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'twilio'))
        ->map(fn ($pair) => $pair[0]['Body'])
        ->values()
        ->all();
});

it('verifies a number with a texted code', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sms/verify-start", ['phone' => '+1 (415) 555-0123'])->assertOk();

    $sent = ($this->texts)()[0];
    preg_match('/\d{6}/', $sent, $code);

    $this->postJson("{$this->base}/sms/verify-confirm", ['code' => '000000'])->assertUnprocessable();
    $this->postJson("{$this->base}/sms/verify-confirm", ['code' => $code[0]])->assertOk()->assertJsonPath('data.phone', '+14155550123');

    expect(AssistantPhoneNumber::query()->sole())->phone->toBe('+14155550123')->verified_at->not->toBeNull();
    $this->getJson($this->base)->assertJsonPath('data.sms.phone', '+14155550123');
});

it('limits resends, guesses and numbers already taken', function () {
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sms/verify-start", ['phone' => '+14155550123'])->assertOk();
    $this->postJson("{$this->base}/sms/verify-start", ['phone' => '+14155550123'])->assertUnprocessable()->assertJsonValidationErrors('phone');

    AssistantPhoneVerification::query()->update(['attempts' => 5]);
    $this->postJson("{$this->base}/sms/verify-confirm", ['code' => '123456'])->assertUnprocessable()->assertJsonValidationErrors('code');

    $other = User::factory()->create();
    AssistantPhoneNumber::query()->create(['user_id' => $other->id, 'phone' => '+14155550999', 'verified_at' => now()]);
    $this->travel(2)->minutes();
    $this->postJson("{$this->base}/sms/verify-start", ['phone' => '+14155550999'])->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('answers a verified number by text, and rejects unsigned webhooks', function () {
    ($this->verified)();
    AssistantAgent::fake(['**Two** meetings today.']);

    ($this->twilioPost)(['From' => '+14155550123', 'Body' => 'What is on today?'], 'forged')->assertForbidden();
    ($this->twilioPost)(['From' => '+14155550123', 'Body' => 'What is on today?'])->assertOk();

    expect(AssistantSession::query()->sole())->origin->toBe(AssistantSessionOrigin::Sms)
        ->and(($this->texts)())->toBe(['Two meetings today.']);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'twilio') && $request['To'] === '+14155550123' && $request['From'] === '+15550001111');
});

it('ignores unknown numbers', function () {
    ($this->twilioPost)(['From' => '+19990000000', 'Body' => 'hi'])->assertOk();

    expect(AssistantSession::query()->count())->toBe(0);
});

it('continues within 20 minutes, starts fresh after a week, and asks the model in between', function () {
    ($this->verified)();
    AssistantAgent::fake(['One.', 'Two.', 'Three.', 'Four.']);
    ThreadRouterAgent::fake([['continues' => false]]);
    $sms = app(SmsChannel::class);

    $sms->handle('+14155550123', 'Plan my trip to Lisbon');
    $this->travel(10)->minutes();
    $sms->handle('+14155550123', 'And hotels?');
    expect(AssistantSession::query()->count())->toBe(1);

    $this->travel(2)->hours();
    $sms->handle('+14155550123', 'Unrelated: what is 2+2?');
    expect(AssistantSession::query()->count())->toBe(2);
    ThreadRouterAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'what is 2+2'));

    $this->travel(8)->days();
    $sms->handle('+14155550123', 'Hello again');
    expect(AssistantSession::query()->count())->toBe(3);
});

it('splits long replies and links to the app past six texts', function () {
    ($this->verified)();
    AssistantAgent::fake([str_repeat('a', 1500 * 7)]);

    app(SmsChannel::class)->handle('+14155550123', 'Write a lot');

    $texts = ($this->texts)();
    expect($texts)->toHaveCount(6)
        ->and(mb_strlen($texts[5]))->toBe(1500)
        ->and($texts[5])->toContain('Read the rest: https://app.example.test/');
});

it('rate limits a number', function () {
    ($this->verified)();
    config(['assistant.channels.sms.per_minute' => 2]);
    AssistantAgent::fake(['ok', 'ok']);
    $sms = app(SmsChannel::class);

    expect($sms->handle('+14155550123', 'one'))->toBe('accepted')
        ->and($sms->handle('+14155550123', 'two'))->toBe('accepted')
        ->and($sms->handle('+14155550123', 'three'))->toBe('ignored: rate limited');
});
