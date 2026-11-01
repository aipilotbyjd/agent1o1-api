<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Agents\GenerationSettings;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/agents";

    Passport::actingAs($this->owner);
});

it('rejects agent settings that would allow a runaway tool loop', function (array $settings, string $field) {
    $this->postJson($this->url, ['name' => 'Loopy', 'settings' => $settings])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $agent = Agent::factory()->forWorkspace($this->workspace)->create();

    $this->patchJson("{$this->url}/{$agent->id}", ['settings' => $settings])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'too many steps' => [['max_steps' => GenerationSettings::MAX_STEPS + 1], 'settings.max_steps'],
    'zero steps' => [['max_steps' => 0], 'settings.max_steps'],
    'too many tokens' => [['max_tokens' => GenerationSettings::MAX_TOKENS + 1], 'settings.max_tokens'],
    'top_p out of range' => [['top_p' => 1.5], 'settings.top_p'],
]);

it('accepts settings within the limits', function () {
    $this->postJson($this->url, ['name' => 'Careful', 'settings' => ['max_steps' => GenerationSettings::MAX_STEPS, 'max_tokens' => 2000, 'top_p' => 0.9]])
        ->assertCreated();
});

it('clamps settings already stored beyond the limits at run time', function () {
    $agent = Agent::factory()->forWorkspace($this->workspace)->create(['settings' => ['max_steps' => 100000, 'max_tokens' => 9999999]]);

    $settings = GenerationSettings::fromAgent($agent);

    expect($settings->maxSteps)->toBe(GenerationSettings::MAX_STEPS)
        ->and($settings->maxTokens)->toBe(GenerationSettings::MAX_TOKENS);
});

it('leaves unset settings unset', function () {
    $settings = GenerationSettings::fromAgent(Agent::factory()->forWorkspace($this->workspace)->create(['settings' => []]));

    expect($settings->maxSteps)->toBeNull()->and($settings->maxTokens)->toBeNull();
});
