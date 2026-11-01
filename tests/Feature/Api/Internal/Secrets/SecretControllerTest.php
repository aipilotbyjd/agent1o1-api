<?php

use App\Enums\Workspaces\Role;
use App\Models\Secrets\Secret;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

/**
 * @return array{0: Workspace, 1: User}
 */
function ownerWorkspaceForSecrets(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$workspace, $owner];
}

it('creates a secret and never exposes its value', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'STRIPE_API_KEY',
        'value' => 'sk_live_supersecret',
        'description' => 'Billing',
    ]);

    $response->assertCreated();
    expect($response->json('data.secret.key'))->toBe('STRIPE_API_KEY');
    expect($response->json('data.secret.is_secret'))->toBeTrue();
    expect($response->json('data.secret.reference'))->toBe('{{ secrets.STRIPE_API_KEY }}');
    expect($response->json('data.secret'))->not->toHaveKey('value');
    $response->assertJsonMissing(['sk_live_supersecret']);

    $secret = Secret::query()->firstWhere('key', 'STRIPE_API_KEY');
    expect($secret->value)->toBe('sk_live_supersecret');
    expect($secret->created_by)->toBe($owner->id);
    expect($secret->getRawOriginal('value'))->not->toContain('sk_live_supersecret');
});

it('reads back the value of a non-secret variable', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();

    Passport::actingAs($owner);

    $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'API_BASE_URL',
        'value' => 'https://api.example.com',
        'is_secret' => false,
    ]);

    $response->assertCreated();
    expect($response->json('data.secret.value'))->toBe('https://api.example.com');

    $index = $this->getJson("/api/v1/workspaces/{$workspace->id}/secrets");

    $index->assertOk();
    expect($index->json('data.secrets.0.value'))->toBe('https://api.example.com');
});

it('lists secrets without their values', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'TOKEN_A', 'value' => 'value-a']);
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'TOKEN_B', 'value' => 'value-b']);

    Passport::actingAs($owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/secrets");

    $response->assertOk();
    expect(collect($response->json('data.secrets'))->pluck('key')->all())->toBe(['TOKEN_A', 'TOKEN_B']);
    $response->assertJsonMissing(['value-a']);
    $response->assertJsonMissing(['value-b']);
});

it('rejects a key that could not be referenced from a template', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'my key!',
        'value' => 'x',
    ])->assertJsonValidationErrorFor('key');
});

it('rejects a duplicate key in the same workspace but allows it in another', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'SHARED_KEY']);

    $otherWorkspace = app(WorkspaceService::class)->create($owner, ['name' => 'Other']);

    Passport::actingAs($owner);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'SHARED_KEY',
        'value' => 'x',
    ])->assertJsonValidationErrorFor('key');

    $this->postJson("/api/v1/workspaces/{$otherWorkspace->id}/secrets", [
        'key' => 'SHARED_KEY',
        'value' => 'x',
    ])->assertCreated();
});

it('rotates a secret value in place', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    $secret = Secret::factory()->forWorkspace($workspace)->create(['key' => 'ROTATE_ME', 'value' => 'old-value']);

    Passport::actingAs($owner);

    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/secrets/{$secret->id}", [
        'value' => 'new-value',
    ]);

    $response->assertOk();
    $response->assertJsonMissing(['new-value']);
    expect($secret->fresh()->value)->toBe('new-value');
});

it('refuses to reveal an existing secret by flipping it to a variable', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    $secret = Secret::factory()->forWorkspace($workspace)->create(['key' => 'API_TOKEN', 'value' => 'tok_live_abcdef']);

    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}/secrets/{$secret->id}", [
        'is_secret' => false,
    ])->assertJsonValidationErrorFor('value');

    expect($secret->fresh()->is_secret)->toBeTrue();

    // Replacing the value in the same request is fine: nothing previously
    // hidden is revealed.
    $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/secrets/{$secret->id}", [
        'is_secret' => false,
        'value' => 'https://api.example.com',
    ]);

    $response->assertOk();
    expect($response->json('data.secret.value'))->toBe('https://api.example.com');
});

it('deletes a secret', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    $secret = Secret::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $this->deleteJson("/api/v1/workspaces/{$workspace->id}/secrets/{$secret->id}")->assertNoContent();

    expect(Secret::query()->find($secret->id))->toBeNull();
});

it('hides another workspace secret behind a 404', function () {
    [$workspace, $owner] = ownerWorkspaceForSecrets();
    $foreign = Secret::factory()->create();

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/secrets/{$foreign->id}")->assertNotFound();
});

it('lets a viewer list secrets but not manage them', function () {
    [$workspace] = ownerWorkspaceForSecrets();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Secret::factory()->forWorkspace($workspace)->create(['key' => 'TOKEN_A']);

    Passport::actingAs($viewer);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/secrets")->assertOk();
    $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'NEW_TOKEN',
        'value' => 'x',
    ])->assertForbidden();
});

it('does not let an editor manage secrets', function () {
    [$workspace] = ownerWorkspaceForSecrets();
    $editor = User::factory()->create();
    $workspace->members()->create(['user_id' => $editor->id, 'role' => Role::Editor, 'joined_at' => now()]);

    Passport::actingAs($editor);

    $this->postJson("/api/v1/workspaces/{$workspace->id}/secrets", [
        'key' => 'NEW_TOKEN',
        'value' => 'x',
    ])->assertForbidden();
});
