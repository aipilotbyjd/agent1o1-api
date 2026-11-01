<?php

use App\Models\Ai\ModelUsageDaily;
use App\Models\User;
use App\Notifications\SuperAdmin\SuperAdminAlertNotification;
use App\Services\Ai\Operations\ModelHealthReport;
use App\Services\Ai\Operations\SpendGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function spend(string $workspaceId, float $cost, int $daysAgo = 0, string $model = 'gpt-x'): void
{
    // Inserted the way `RollUpModelUsage` writes it: a plain date, not one the model's cast formats.
    ModelUsageDaily::query()->insert([
        'id' => (string) Str::orderedUuid(),
        'date' => today()->subDays($daysAgo)->toDateString(),
        'workspace_id' => $workspaceId,
        'provider' => 'openai',
        'model' => $model,
        'own_key' => false,
        'calls' => 10,
        'prompt_tokens' => 1000,
        'completion_tokens' => 1000,
        'estimated_cost_usd' => $cost,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<int, string>
 */
function guardCodes(): array
{
    return array_column(app(SpendGuard::class)->findings(), 'code');
}

beforeEach(function () {
    config([
        'model_operations.budget' => ['daily_usd' => null, 'warn_ratio' => 0.8],
        'model_operations.workspace' => ['daily_usd' => null, 'spike_factor' => 5, 'spike_min_usd' => 5],
    ]);

    $this->acme = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $this->globex = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Globex']);
});

it('says nothing when nothing has been spent, or only a little', function () {
    expect(guardCodes())->toBe([]);

    spend($this->acme->id, 4.99);

    expect(guardCodes())->toBe([]);
});

it('sets no budget alarm unless a budget is set', function () {
    spend($this->acme->id, 1);
    spend($this->globex->id, 1);

    expect(guardCodes())->not->toContain('spend_near_budget', 'spend_over_budget');

    config(['model_operations.budget.daily_usd' => 0]);

    expect(guardCodes())->not->toContain('spend_near_budget', 'spend_over_budget');
});

it('warns as the day\'s spend nears the budget and again when it is passed', function () {
    config(['model_operations.budget.daily_usd' => 100.0]);

    spend($this->acme->id, 50);
    expect(guardCodes())->not->toContain('spend_near_budget', 'spend_over_budget');

    spend($this->globex->id, 35);
    $near = collect(app(SpendGuard::class)->findings())->firstWhere('code', 'spend_near_budget');

    expect($near['message'])->toContain('$85.00')->toContain('85%')->toContain('$100.00')
        ->and($near['workspace_id'])->toBeNull();

    spend($this->globex->id, 20, model: 'other');

    expect(guardCodes())->toContain('spend_over_budget')->not->toContain('spend_near_budget');
});

it('counts only today toward the budget, and calls on an own key cost nothing', function () {
    config(['model_operations.budget.daily_usd' => 100.0]);

    spend($this->acme->id, 500, daysAgo: 1);
    ModelUsageDaily::query()->insert([
        'id' => (string) Str::orderedUuid(), 'date' => today()->toDateString(), 'workspace_id' => $this->acme->id, 'provider' => 'own-key',
        'model' => 'gpt-x', 'own_key' => true, 'calls' => 99, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'estimated_cost_usd' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(guardCodes())->not->toContain('spend_near_budget', 'spend_over_budget');
});

it('flags a workspace over the most a workspace should cost in a day', function () {
    config(['model_operations.workspace.daily_usd' => 50.0]);

    spend($this->acme->id, 60);
    spend($this->globex->id, 49);

    $found = collect(app(SpendGuard::class)->findings())->where('code', 'workspace_over_daily_limit');

    expect($found)->toHaveCount(1)
        ->and($found->first()['workspace_id'])->toBe($this->acme->id)
        ->and($found->first()['message'])->toContain('"Acme"')->toContain('$60.00');
});

it('flags a workspace spending many times its usual', function () {
    foreach (range(1, 7) as $daysAgo) {
        spend($this->acme->id, 2, $daysAgo);
    }

    spend($this->acme->id, 12);

    $finding = collect(app(SpendGuard::class)->findings())->firstWhere('code', 'workspace_spend_spike');

    // 12 against an average of 2 a day is six times.
    expect($finding['workspace_id'])->toBe($this->acme->id)
        ->and($finding['message'])->toContain('6.0 times')->toContain('$2.00');
});

it('does not flag growth that is under the factor or under the floor', function () {
    foreach (range(1, 7) as $daysAgo) {
        spend($this->acme->id, 10, $daysAgo);
        spend($this->globex->id, 0.1, $daysAgo);
    }

    spend($this->acme->id, 40);
    spend($this->globex->id, 4);

    expect(guardCodes())->toBe([]);
});

it('flags a workspace that was quiet all week and is suddenly expensive', function () {
    spend($this->acme->id, 25);

    $finding = collect(app(SpendGuard::class)->findings())->firstWhere('code', 'workspace_spend_spike');

    expect($finding['message'])->toContain('no model spend this past week');
});

it('raises each alert once a day', function () {
    Notification::fake();
    config(['super_admin_alerts.recipients.mail' => ['security@example.test'], 'model_operations.budget.daily_usd' => 10.0]);

    spend($this->acme->id, 25);

    expect(app(SpendGuard::class)->alert())->toBe(2)
        ->and(app(SpendGuard::class)->alert())->toBe(0);

    Notification::assertSentOnDemandTimes(SuperAdminAlertNotification::class, 2);

    $this->travel(1)->day();
    spend($this->acme->id, 25);

    expect(app(SpendGuard::class)->alert())->toBe(2);
});

it('shows up in the health report as warnings, never as errors', function () {
    config(['model_operations.budget.daily_usd' => 10.0, 'ai.default_for_embeddings' => 'openai', 'ai.providers.openai.key' => 'k']);

    spend($this->acme->id, 25);

    $findings = collect(app(ModelHealthReport::class)->run()['findings'])->whereIn('code', ['spend_over_budget', 'workspace_spend_spike']);

    expect($findings->pluck('severity')->unique()->all())->toBe(['warning'])
        ->and($findings->firstWhere('code', 'workspace_spend_spike')['subject'])->toBe(['type' => 'workspace', 'slug' => null, 'id' => $this->acme->id]);
});

it('runs from the command line', function () {
    config(['model_operations.budget.daily_usd' => 10.0]);

    $this->artisan('model-spend:check')->expectsOutputToContain('looks normal')->assertSuccessful();

    spend($this->acme->id, 25);

    $this->artisan('model-spend:check')->expectsOutputToContain('2 new alert(s) raised')->assertSuccessful();
    $this->artisan('model-spend:check')->expectsOutputToContain('0 new alert(s) raised')->assertSuccessful();
});
