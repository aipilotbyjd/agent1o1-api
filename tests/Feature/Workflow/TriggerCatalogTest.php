<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TriggerCategorySeeder;
use Database\Seeders\TriggerTypeFieldSeeder;
use Database\Seeders\TriggerTypeSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([
        PlanSeeder::class,
        TriggerCategorySeeder::class,
        TriggerTypeSeeder::class,
        TriggerTypeFieldSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

test('the trigger catalog exposes app-specific categories with their types and fields', function () {
    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/v1/workspaces/{$this->workspace->id}/trigger-catalog")
        ->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug');

    expect($slugs)->toContain('github', 'slack', 'stripe', 'gmail', 'webhook', 'manual');

    $github = collect($response->json('data'))->firstWhere('slug', 'github');

    expect($github['category_type'])->toBe('app_specific');

    $push = collect($github['trigger_types'])->firstWhere('slug', 'github_push');

    expect($push)->not->toBeNull()
        ->and($push['execution_mode'])->toBe('webhook')
        ->and($push['webhook_events'])->toBe(['push'])
        ->and(collect($push['fields'])->pluck('field_name'))->toContain('owner', 'repo');
});

test('the trigger catalog requires authentication', function () {
    $this->getJson("/api/v1/workspaces/{$this->workspace->id}/trigger-catalog")
        ->assertUnauthorized();
});
