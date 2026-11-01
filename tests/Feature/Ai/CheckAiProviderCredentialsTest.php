<?php

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Workspaces\Role;
use App\Jobs\Ai\CheckAiProviderCredentialJob;
use App\Models\Ai\AiProviderCredential;
use App\Models\User;
use App\Notifications\Ai\AiProviderCredentialInvalidNotification;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('queues a check for every key not checked recently, skipping known-bad keys', function () {
    Bus::fake();
    $stale = AiProviderCredential::factory()->create(['last_validated_at' => now()->subHours(7)]);
    $never = AiProviderCredential::factory()->create(['last_validated_at' => null, 'validation_status' => 'unvalidated']);
    AiProviderCredential::factory()->create(['last_validated_at' => now()->subHour()]);
    AiProviderCredential::factory()->invalid()->create(['last_validated_at' => now()->subDays(3)]);

    $this->artisan('ai-credentials:check')->assertSuccessful();

    Bus::assertDispatchedTimes(CheckAiProviderCredentialJob::class, 2);
    Bus::assertDispatched(CheckAiProviderCredentialJob::class, fn ($job) => in_array($job->credential->id, [$stale->id, $never->id], true));
});

it('takes a revoked team key out of rotation and tells the admins and whoever added it', function () {
    Notification::fake();
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'revoked'], 401)]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $editor = User::factory()->create();
    $workspace->members()->create(['user_id' => $editor->id, 'role' => Role::Editor, 'joined_at' => now()]);
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->create(['created_by' => $editor->id]);

    CheckAiProviderCredentialJob::dispatchSync($credential);

    expect($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Invalid);
    Notification::assertSentTo([$owner, $editor], AiProviderCredentialInvalidNotification::class);
});

it('tells only the owner when their personal key stops working', function () {
    Notification::fake();
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'revoked'], 401)]);
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $member = User::factory()->create();
    $workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $credential = AiProviderCredential::factory()->forWorkspace($workspace)->personal($member)->create();

    CheckAiProviderCredentialJob::dispatchSync($credential);

    Notification::assertSentTo($member, AiProviderCredentialInvalidNotification::class);
    Notification::assertNotSentTo($owner, AiProviderCredentialInvalidNotification::class);
});

it('keeps a working key in rotation when the provider is unreachable', function () {
    Notification::fake();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $credential = AiProviderCredential::factory()->create();

    CheckAiProviderCredentialJob::dispatchSync($credential);

    expect($credential->fresh()->validation_status)->toBe(AiProviderCredentialStatus::Valid);
    Notification::assertNothingSent();
});

it('promotes a key the provider now accepts', function () {
    Http::fake(['api.openai.com/*' => Http::response(['data' => []])]);
    $credential = AiProviderCredential::factory()->create(['validation_status' => 'unvalidated']);

    CheckAiProviderCredentialJob::dispatchSync($credential);

    expect($credential->fresh()->isValid())->toBeTrue();
});
