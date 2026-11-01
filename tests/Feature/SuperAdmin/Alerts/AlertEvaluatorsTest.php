<?php

use App\Enums\RunStatus;
use App\Models\Ai\AiProviderPause;
use App\Models\Billing\StripeWebhookFailure;
use App\Models\Billing\Subscription;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Models\SuperAdmin\SuperAdminLoginEvent;
use App\Models\User;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use App\Services\SuperAdmin\Audit\AuditChainVerifier;
use App\Services\SuperAdmin\System\HealthChecker;
use App\Services\SuperAdmin\System\SchedulerMonitor;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->headers = superAdminHeaders(superAdmin());
    app(AlertRuleCatalog::class)->sync();
});

/**
 * What the rule would report right now, via the dry-run endpoint.
 *
 * @return array<string, mixed>
 */
function dryRun(string $key, array $config = []): array
{
    $rule = AlertRule::query()->where('key', $key)->firstOrFail();

    if ($config !== []) {
        $rule->forceFill(['config' => $config])->save();
    }

    return test()->withHeaders(test()->headers)->postJson(saUrl("alerts/rules/{$rule->id}/dry-run"))->assertOk()->json('data');
}

it('flags a failed-run spike against the same hour of previous weeks', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    expect(dryRun('failed_runs_spike')['would_fire'])->toBe(0);

    Run::factory()->forWorkspace($workspace)->count(6)->create(['status' => RunStatus::Failed]);

    $result = dryRun('failed_runs_spike');

    expect($result['would_fire'])->toBe(1)->and($result['signals'][0]['context']['failed_runs'])->toBe(6);

    // The same hour a week ago was just as bad: not an anomaly.
    Run::factory()->forWorkspace($workspace)->count(8)->create(['status' => RunStatus::Failed, 'updated_at' => now()->subWeek()->subMinutes(5)]);

    expect(dryRun('failed_runs_spike', ['baseline_weeks' => 1])['would_fire'])->toBe(0);
});

it('reports each failing Stripe webhook event, and critical once it keeps failing', function () {
    StripeWebhookFailure::record('evt_a', 'invoice.paid', 'boom');
    StripeWebhookFailure::record('evt_b', 'invoice.paid', 'boom')->forceFill(['attempts' => 4])->save();

    $signals = collect(dryRun('webhook_failure')['signals'])->keyBy('fingerprint');

    expect($signals)->toHaveCount(2)
        ->and($signals['evt_a']['severity'])->toBe('warning')
        ->and($signals['evt_b']['severity'])->toBe('critical');
});

it('flags a backed-up queue', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'ai-agent', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(30)->getTimestamp(), 'created_at' => now()->getTimestamp()]);

    $signals = dryRun('queue_backlog')['signals'];

    expect($signals)->toHaveCount(1)->and($signals[0])->toMatchArray(['fingerprint' => 'ai-agent', 'severity' => 'critical']);
});

it('flags a scheduler whose heartbeat went stale', function () {
    expect(dryRun('scheduler_dead')['would_fire'])->toBe(0);

    Cache::forever(SchedulerMonitor::HEARTBEAT_KEY, now()->subMinutes(10)->getTimestamp());

    expect(dryRun('scheduler_dead')['would_fire'])->toBe(1);
});

it('flags failed jobs piling up', function () {
    foreach (range(1, 3) as $i) {
        DB::table('failed_jobs')->insert(['uuid' => (string) str()->uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
    }

    expect(dryRun('failed_jobs_growth')['would_fire'])->toBe(0)
        ->and(dryRun('failed_jobs_growth', ['window_minutes' => 60, 'threshold' => 3])['would_fire'])->toBe(1);
});

it('flags a burst of payment failures', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);

    foreach (range(1, 3) as $i) {
        Subscription::query()->create(['workspace_id' => app(WorkspaceService::class)->create(User::factory()->create(), ['name' => "W{$i}"])->id, 'type' => 'default', 'stripe_id' => "sub_{$i}", 'stripe_status' => 'past_due', 'stripe_price' => 'price_x', 'dunning_started_at' => now()->subHour()]);
    }

    expect(dryRun('payment_failures')['would_fire'])->toBe(1);
});

it('flags a signup wave', function () {
    expect(dryRun('signup_anomaly')['would_fire'])->toBe(0);

    User::factory()->count(25)->create();

    expect(dryRun('signup_anomaly')['would_fire'])->toBe(1);
});

it('flags sign-in trouble on the admin panel', function () {
    foreach (range(1, 5) as $i) {
        SuperAdminLoginEvent::query()->create(['event' => 'login_failed', 'email_attempted' => 'x@example.test', 'created_at' => now()]);
    }

    expect(dryRun('admin_security')['signals'])->toHaveCount(1);

    SuperAdminLoginEvent::query()->create(['event' => 'refresh_reuse_detected', 'created_at' => now()]);

    $fingerprints = array_column(dryRun('admin_security')['signals'], 'fingerprint');

    expect($fingerprints)->toEqualCanonicalizing(['failed-logins', 'token-reuse']);
});

it('keeps a paused provider open until it is resumed', function () {
    AiProviderPause::query()->create(['provider' => 'openai', 'reason' => 'Outage', 'paused_at' => now()]);

    expect(dryRun('provider_paused')['signals'][0])->toMatchArray(['fingerprint' => 'openai']);
});

it('reports a broken audit chain as critical', function () {
    $this->mock(AuditChainVerifier::class)->shouldReceive('verify')->andReturn([
        'intact' => false, 'checked' => 3, 'head_sequence' => 2, 'head_hash' => 'x',
        'break' => ['sequence' => 3, 'id' => 'abc', 'reason' => 'hash_mismatch', 'message' => 'Entry 3 was changed.'],
    ]);

    expect(dryRun('audit_chain_broken')['signals'][0])->toMatchArray(['fingerprint' => 'audit-chain', 'severity' => 'critical']);
});

it('reports a service that is down', function () {
    $this->mock(HealthChecker::class)->shouldReceive('run')->andReturn([
        'status' => 'down', 'checked_at' => now()->toIso8601String(),
        'checks' => ['database' => ['status' => 'down', 'message' => 'refused', 'details' => [], 'latency_ms' => 1], 'mail' => ['status' => 'degraded', 'message' => 'log', 'details' => [], 'latency_ms' => 1]],
    ]);

    expect(array_column(dryRun('service_down')['signals'], 'fingerprint'))->toBe(['database']);
});

it('does not record anything on a dry run', function () {
    StripeWebhookFailure::record('evt_a', 'invoice.paid', 'boom');

    $result = dryRun('webhook_failure');

    expect($result['would_notify'])->toBe(1)->and(AlertEvent::query()->count())->toBe(0);
});
