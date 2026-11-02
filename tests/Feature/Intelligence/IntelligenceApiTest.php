<?php

use App\Ai\Agents\Intelligence\AdminAnalystAgent;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Ai\AiCallEvent;
use App\Models\Intelligence\AdminRecommendation;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Services\Billing\SpendControls;
use App\Services\Workspaces\WorkspaceService;

const INTEL_REASON = 'Testing the intelligence API';

it('serves the finance views to finance and refuses support', function (string $path) {
    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Finance)))->getJson(saUrl($path))->assertOk();
    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Support)))->getJson(saUrl($path))->assertForbidden();
})->with([
    'intelligence/economics/overview',
    'intelligence/economics/timeseries?from=2026-10-01&to=2026-10-31',
    'intelligence/economics/plans',
    'intelligence/economics/models',
    'intelligence/economics/features',
    'intelligence/economics/customers',
    'intelligence/economics/credit-utilization',
    'intelligence/forecasts/spend',
    'intelligence/forecasts/revenue',
    'intelligence/forecasts/margin',
    'intelligence/forecasts/credit-run-out',
    'intelligence/forecasts/accuracy',
    'intelligence/risks/margin-at-risk',
    'intelligence/risks/churn',
    'intelligence/risks/upgrade',
    'intelligence/risks/abuse',
    'intelligence/recommendations',
]);

it('rejects a range that runs backwards', function () {
    $this->withHeaders(superAdminHeaders(superAdmin()))->getJson(saUrl('intelligence/economics/overview?from=2026-10-10&to=2026-10-01'))
        ->assertUnprocessable();
});

it('shows the live board and lets any role search the call ledger', function () {
    $event = AiCallEvent::factory()->create(['trace_id' => 'trace-1', 'provider' => 'openai', 'model' => 'gpt-x']);
    AiCallEvent::factory()->create(['provider' => 'anthropic']);
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Viewer));

    $this->withHeaders($headers)->getJson(saUrl('intelligence/live'))
        ->assertOk()
        ->assertJsonCount(60, 'data.per_minute');

    $this->withHeaders($headers)->getJson(saUrl('intelligence/calls?provider=openai'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $event->id);

    $this->withHeaders($headers)->getJson(saUrl('intelligence/traces/trace-1'))
        ->assertOk()
        ->assertJsonCount(1, 'data.calls');
});

it('changes the spend controls with a reason, audited, for an operator only', function () {
    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Finance)))->putJson(saUrl('intelligence/spend-controls'), ['reason' => INTEL_REASON, 'kill_switch' => 'everyone'])
        ->assertForbidden();

    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Operator)))->putJson(saUrl('intelligence/spend-controls'), [
        'reason' => INTEL_REASON,
        'kill_switch' => 'free_and_trial',
        'platform_daily_budget_usd' => 250,
        'price_enforcement' => 'enforce',
    ])
        ->assertOk()
        ->assertJsonPath('data.kill_switch', 'free_and_trial')
        ->assertJsonPath('data.platform_daily_budget_usd', 250)
        ->assertJsonPath('data.price_enforcement', 'enforce');

    expect(SuperAdminAuditLog::query()->where('action', 'intelligence.spend_controls.updated')->exists())->toBeTrue();
});

it('pauses and resumes one workspace', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Operator));

    $this->withHeaders($headers)->postJson(saUrl("intelligence/workspaces/{$workspace->id}/throttle"), ['reason' => INTEL_REASON, 'hours' => 2])->assertOk();
    expect(app(SpendControls::class)->throttledUntil($workspace->id))->not->toBeNull();

    $this->withHeaders($headers)->deleteJson(saUrl("intelligence/workspaces/{$workspace->id}/throttle"), ['reason' => INTEL_REASON])->assertOk();
    expect(app(SpendControls::class)->throttledUntil($workspace->id))->toBeNull();
});

it('applies and dismisses recommendations with a reason', function () {
    $recommendation = AdminRecommendation::query()->create([
        'rule' => 'x', 'fingerprint' => 'x:2', 'severity' => 'info', 'title' => 'Look at this', 'body' => 'Because',
        'status' => 'open', 'last_seen_at' => now(),
    ]);
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Finance));

    $this->withHeaders($headers)->postJson(saUrl("intelligence/recommendations/{$recommendation->id}/apply"))->assertUnprocessable();
    $this->withHeaders($headers)->postJson(saUrl("intelligence/recommendations/{$recommendation->id}/apply"), ['reason' => INTEL_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'applied');
    $this->withHeaders($headers)->postJson(saUrl("intelligence/recommendations/{$recommendation->id}/apply"), ['reason' => INTEL_REASON])->assertStatus(409);
});

it('simulates pricing and routing', function () {
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Finance));

    $this->withHeaders($headers)->postJson(saUrl('intelligence/simulate/pricing'), ['plans' => ['pro' => ['price_monthly' => 7900]]])
        ->assertOk()
        ->assertJsonStructure(['data' => ['current', 'proposed', 'customers_paying_more', 'by_plan', 'margin_floor']]);

    $this->withHeaders($headers)->postJson(saUrl('intelligence/simulate/routing'), ['from_provider' => 'a', 'from_model' => 'b', 'to_provider' => 'c', 'to_model' => 'd'])
        ->assertUnprocessable();
});

it('answers a question through the analyst, audited', function () {
    AdminAnalystAgent::fake(['Pro lost $12 last month because of Opus usage.']);

    $this->withHeaders(superAdminHeaders(superAdmin(SuperAdminRole::Finance)))->postJson(saUrl('intelligence/analyst'), ['question' => 'Which plan lost money last month?'])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Pro lost $12 last month because of Opus usage.');

    expect(SuperAdminAuditLog::query()->where('action', 'intelligence.analyst.asked')->exists())->toBeTrue();
});

it('creates, starts and reports on an experiment', function () {
    $headers = superAdminHeaders(superAdmin(SuperAdminRole::Finance));

    $id = $this->withHeaders($headers)->postJson(saUrl('intelligence/experiments'), [
        'reason' => INTEL_REASON,
        'key' => 'trial-credits',
        'name' => 'Trial credits',
        'variants' => [['key' => 'a', 'weight' => 50], ['key' => 'b', 'weight' => 50]],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)->postJson(saUrl("intelligence/experiments/{$id}/start"), ['reason' => INTEL_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'running');

    $this->withHeaders($headers)->getJson(saUrl("intelligence/experiments/{$id}"))->assertOk()->assertJsonPath('data.experiment.key', 'trial-credits');
});
