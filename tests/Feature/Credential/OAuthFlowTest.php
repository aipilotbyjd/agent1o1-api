<?php

use App\Enums\Role;
use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\OAuthCredentialState;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->type = CredentialType::factory()->oauth()->create(['key' => 'demo_provider']);

    config(['services.demo_provider' => ['client_id' => 'cid', 'client_secret' => 'secret']]);
});

test('initiating oauth returns an authorize url and stores state', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/v1/workspaces/{$this->workspace->id}/credentials/oauth/initiate", [
            'credential_type_key' => 'demo_provider',
            'name' => 'My Connection',
        ])
        ->assertOk();

    expect($response->json('data.authorize_url'))->toContain('https://provider.test/oauth/authorize')
        ->and(OAuthCredentialState::where('state', $response->json('data.state'))->exists())->toBeTrue();
});

test('the callback exchanges the code and stores a credential', function () {
    Http::fake([
        'provider.test/oauth/token' => Http::response([
            'access_token' => 'tok-123',
            'refresh_token' => 'ref-456',
            'expires_in' => 3600,
        ]),
    ]);

    $state = OAuthCredentialState::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'credential_type_key' => 'demo_provider',
        'state' => 'state-token',
        'name' => 'My Connection',
        'scopes' => ['read'],
        'redirect_uri' => 'http://localhost/api/v1/oauth-credentials/callback',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/oauth-credentials/callback', [
        'state' => 'state-token',
        'code' => 'auth-code',
    ])->assertCreated();

    expect(Credential::where('workspace_id', $this->workspace->id)->where('type', 'demo_provider')->exists())->toBeTrue()
        ->and(OAuthCredentialState::find($state->id))->toBeNull();
});

test('an invalid oauth state is rejected', function () {
    $this->postJson('/api/v1/oauth-credentials/callback', [
        'state' => 'nope',
        'code' => 'auth-code',
    ])->assertStatus(422);
});
