<?php

use App\Enums\Ai\PriceEnforcement;
use App\Enums\Intelligence\RecommendationStatus;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelPrice;
use App\Models\Ai\ModelRoute;
use App\Models\Billing\Plan;
use App\Models\Intelligence\AdminRecommendation;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\Billing\SpendControls;
use App\Services\Intelligence\RecommendationEngine;

beforeEach(function () {
    config(['billing.model_prices' => [], 'ai.providers.openai.key' => 'platform-key', 'billing.default_plan' => 'free']);
    Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 100, 'price_monthly' => 0]);
});

it('proposes fixing a plan that sells credits below the floor, once, and resolves it when fixed', function () {
    $plan = Plan::factory()->create(['slug' => 'leaky', 'credits_monthly' => 25_000, 'price_monthly' => 9900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);
    $engine = app(RecommendationEngine::class);

    $engine->evaluate();
    $engine->evaluate();

    $recommendation = AdminRecommendation::query()->where('rule', 'margin_floor')->sole();

    expect($recommendation->severity)->toBe('critical')
        ->and($recommendation->status)->toBe(RecommendationStatus::Open)
        ->and($recommendation->evidence['usd_per_credit'])->toBe(0.00396);

    $plan->update(['credits_monthly' => 15_000]);
    $engine->evaluate();

    expect($recommendation->fresh()->status)->toBe(RecommendationStatus::Resolved);
});

it('proposes switching price enforcement on once every paid route is verified, and applies it with an audit entry', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'trusted']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'trusted-id']);
    ModelPrice::factory()->create(['provider' => 'openai', 'model_id' => 'trusted-id']);
    $engine = app(RecommendationEngine::class);
    $engine->evaluate();

    $recommendation = AdminRecommendation::query()->where('rule', 'price_enforcement')->sole();
    $engine->apply($recommendation, superAdmin(), 'Prices checked');

    expect(PriceEnforcement::current())->toBe(PriceEnforcement::Enforce)
        ->and($recommendation->fresh()->status)->toBe(RecommendationStatus::Applied)
        ->and(SuperAdminAuditLog::query()->where('action', 'intelligence.recommendation.applied')->exists())->toBeTrue();
});

it('flags unverified paid routes instead while any remain', function () {
    $model = ModelCatalog::factory()->create(['slug' => 'guessed']);
    ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => 'guessed-id']);

    app(RecommendationEngine::class)->evaluate();

    expect(AdminRecommendation::query()->pluck('rule')->all())->toContain('price_book')
        ->not->toContain('price_enforcement');
});

it('keeps a dismissed recommendation quiet for two weeks', function () {
    Plan::factory()->create(['slug' => 'leaky', 'credits_monthly' => 25_000, 'price_monthly' => 9900, 'price_quarterly' => 0, 'price_yearly' => 0, 'price_lifetime' => 0, 'enabled_intervals' => ['monthly'], 'is_active' => true]);
    $engine = app(RecommendationEngine::class);
    $engine->evaluate();
    $recommendation = AdminRecommendation::query()->where('rule', 'margin_floor')->sole();

    $engine->dismiss($recommendation, superAdmin(), 'Intentional launch offer');
    $engine->evaluate();
    expect($recommendation->fresh()->status)->toBe(RecommendationStatus::Dismissed);

    $this->travel(15)->days();
    $engine->evaluate();
    expect($recommendation->fresh()->status)->toBe(RecommendationStatus::Open);
});

it('pauses a workspace when a throttle recommendation is applied', function () {
    $recommendation = AdminRecommendation::query()->create([
        'rule' => 'abuse', 'fingerprint' => 'abuse:x', 'severity' => 'critical', 'title' => 'Abuse', 'body' => 'Abuse',
        'action' => ['type' => 'throttle_workspace', 'workspace_id' => '00000000-0000-0000-0000-0000000000aa', 'hours' => 3],
        'status' => RecommendationStatus::Open, 'last_seen_at' => now(),
    ]);

    app(RecommendationEngine::class)->apply($recommendation, superAdmin());

    expect(app(SpendControls::class)->throttledUntil('00000000-0000-0000-0000-0000000000aa'))->not->toBeNull();
});

it('refuses to apply a recommendation that is no longer open', function () {
    $recommendation = AdminRecommendation::query()->create([
        'rule' => 'x', 'fingerprint' => 'x:1', 'severity' => 'info', 'title' => 'X', 'body' => 'X',
        'status' => RecommendationStatus::Dismissed, 'last_seen_at' => now(),
    ]);

    expect(fn () => app(RecommendationEngine::class)->apply($recommendation, superAdmin()))->toThrow(InvalidArgumentException::class);
});
