<?php

use App\Ai\Tools\ForgetTool;
use App\Ai\Tools\RecallMemoriesTool;
use App\Ai\Tools\RememberTool;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create();
});

it('records the conversation a memory was saved in', function () {
    $session = AgentSession::factory()->forAgent($this->agent)->create();

    (new RememberTool($this->agent, $this->owner->id, $session->id))->handle(new Request(['key' => 'role', 'value' => 'CTO']));

    expect($this->agent->memories()->sole()->agent_session_id)->toBe($session->id);
});

it('forgets a memory by key', function () {
    $this->agent->memories()->create(['key' => 'role', 'value' => 'CTO', 'user_id' => $this->owner->id]);

    $result = (new ForgetTool($this->agent, $this->owner->id))->handle(new Request(['key' => 'role']));

    expect($result)->toBe('Forgot role.');
    expect($this->agent->memories()->count())->toBe(0);
});

it('says so when there is nothing to forget', function () {
    expect((new ForgetTool($this->agent, $this->owner->id))->handle(new Request(['key' => 'role'])))
        ->toBe('Nothing is remembered under role.');
});

it('cannot forget what the agent remembers for another user or the whole workspace', function () {
    $other = User::factory()->create();
    $this->agent->memories()->create(['key' => 'role', 'value' => 'CTO', 'user_id' => $other->id]);
    $this->agent->memories()->create(['key' => 'role', 'value' => 'Shared', 'user_id' => null]);

    (new ForgetTool($this->agent, $this->owner->id))->handle(new Request(['key' => 'role']));

    expect($this->agent->memories()->count())->toBe(2);
});

it('injects only the most recent memories and points the agent at recall for the rest', function () {
    foreach (range(1, SkillInjector::MAX_INJECTED_MEMORIES + 5) as $i) {
        $memory = $this->agent->memories()->create(['key' => "fact_{$i}", 'value' => "value {$i}", 'user_id' => $this->owner->id]);
        $memory->forceFill(['updated_at' => now()->subMinutes(100 - $i)])->save();
    }

    $instructions = app(SkillInjector::class)->instructionsFor($this->agent, $this->owner->id);

    expect($instructions)->toContain('- fact_45: value 45');
    expect($instructions)->not->toContain('- fact_1: value 1'.PHP_EOL);
    expect(substr_count($instructions, '- fact_'))->toBe(SkillInjector::MAX_INJECTED_MEMORIES);
    expect($instructions)->toContain(RecallMemoriesTool::NAME);
});

it('attaches the recall tool only when some memories did not fit in the prompt', function () {
    $run = Run::factory()->create(['triggered_by' => $this->owner->id]);
    $hasRecall = fn (): bool => collect(app(ToolRegistry::class)->toolsFor($this->agent, $run))
        ->contains(fn ($tool) => $tool instanceof RecallMemoriesTool);

    $this->agent->memories()->create(['key' => 'role', 'value' => 'CTO', 'user_id' => $this->owner->id]);
    expect($hasRecall())->toBeFalse();

    foreach (range(1, SkillInjector::MAX_INJECTED_MEMORIES) as $i) {
        $this->agent->memories()->create(['key' => "fact_{$i}", 'value' => 'x', 'user_id' => $this->owner->id]);
    }
    expect($hasRecall())->toBeTrue();
});

it('recalls memories matching any word of the query, within the user\'s scope', function () {
    $other = User::factory()->create();
    $this->agent->memories()->create(['key' => 'billing_contact', 'value' => 'Dana', 'user_id' => $this->owner->id]);
    $this->agent->memories()->create(['key' => 'manager', 'value' => 'Reports to Sam in billing', 'user_id' => null]);
    $this->agent->memories()->create(['key' => 'billing_contact', 'value' => 'Lee', 'user_id' => $other->id]);
    $this->agent->memories()->create(['key' => 'timezone', 'value' => 'UTC', 'user_id' => $this->owner->id]);

    $result = (new RecallMemoriesTool($this->agent, $this->owner->id))->handle(new Request(['query' => 'Billing']));

    expect($result)->toContain('billing_contact: Dana');
    expect($result)->toContain('manager: Reports to Sam in billing');
    expect($result)->not->toContain('Lee');
    expect($result)->not->toContain('timezone');
});
