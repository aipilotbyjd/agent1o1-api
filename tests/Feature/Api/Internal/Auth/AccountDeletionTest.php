<?php

use App\Models\Account\DataExport;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Credentials\OAuthConnection;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function deleteAccount(User $user, array $payload = []): TestResponse
{
    return test()->actingAs($user, 'api')->deleteJson('/api/v1/user', [
        'confirm_email' => $user->email,
        'current_password' => 'password',
        ...$payload,
    ]);
}

it('refuses deletion without the account email typed back', function () {
    $user = User::factory()->create();

    deleteAccount($user, ['confirm_email' => 'someone-else@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_email');

    expect($user->fresh())->not->toBeNull();
});

it('refuses deletion with a wrong password', function () {
    $user = User::factory()->create();

    deleteAccount($user, ['current_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($user->fresh())->not->toBeNull();
});

it('lets a social sign-in account confirm with its email alone', function () {
    $user = User::factory()->create();
    OAuthConnection::query()->create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => '123']);

    deleteAccount($user, ['current_password' => null])->assertNoContent();

    expect($user->fresh())->toBeNull();
});

it('deletes a workspace the account owns alone along with the account', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($user, ['name' => 'Solo']);

    deleteAccount($user)->assertNoContent();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('keeps the account while it owns a workspace other people belong to', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Team']);
    $workspace->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'member', 'joined_at' => now()]);

    deleteAccount($owner)
        ->assertConflict()
        ->assertJsonPath('errors.workspaces.0.id', $workspace->id)
        ->assertJsonPath('errors.workspaces.0.reasons', ['has_other_members']);

    expect($owner->fresh())->not->toBeNull()
        ->and($workspace->fresh())->not->toBeNull();
});

it('keeps the account while an owned workspace still has a subscription that will renew', function () {
    $workspace = subscribeWorkspaceTo(Plan::factory()->create());
    $owner = $workspace->owner;

    deleteAccount($owner)
        ->assertConflict()
        ->assertJsonPath('errors.workspaces.0.reasons', ['active_subscription']);

    expect($owner->fresh())->not->toBeNull();
});

it('allows deletion once the subscription is canceled and only running out its period', function () {
    $workspace = subscribeWorkspaceTo(Plan::factory()->create());
    Subscription::query()->where('workspace_id', $workspace->id)->update(['ends_at' => now()->addWeek()]);

    deleteAccount($workspace->owner)->assertNoContent();
});

it('lists the blocking workspaces for the settings screen', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Team']);
    app(WorkspaceService::class)->create($owner, ['name' => 'Solo']);
    $workspace->members()->create(['user_id' => User::factory()->create()->id, 'role' => 'member', 'joined_at' => now()]);

    $this->actingAs($owner, 'api')
        ->getJson('/api/v1/user/deletion-blockers')
        ->assertOk()
        ->assertJsonCount(1, 'data.workspaces')
        ->assertJsonPath('data.workspaces.0.name', 'Team')
        ->assertJsonPath('data.requires_password', true);
});

it('removes the account data export archives', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $export = DataExport::factory()->completed()->for($user)->create();
    Storage::disk('local')->put($export->path, 'zip');

    deleteAccount($user)->assertNoContent();

    Storage::disk('local')->assertMissing($export->path);
    expect(DataExport::query()->count())->toBe(0);
});
