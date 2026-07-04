<?php

use App\Models\CredentialType;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->user = User::factory()->create();
});

test('active credential types are listed', function () {
    CredentialType::factory()->count(2)->create();
    CredentialType::factory()->create(['is_active' => false]);

    $this->actingAs($this->user, 'api')
        ->getJson('/api/v1/credential-types')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('oauth types only expose public oauth metadata', function () {
    $type = CredentialType::factory()->oauth()->create();

    $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/credential-types/{$type->id}")
        ->assertOk()
        ->assertJsonPath('data.auth_type', 'oauth2')
        ->assertJsonPath('data.oauth.scopes', ['read', 'write'])
        ->assertJsonMissingPath('data.oauth.token_url');
});
