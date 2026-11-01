<?php

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Services\Ai\WorkspaceModels;
use App\Services\Ai\WorkspaceModelView;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['ai.providers.openai.key' => 'platform-key', 'billing.default_plan' => 'free']);

    $this->free = Plan::factory()->create(['slug' => 'free', 'credits_monthly' => 500]);
    $this->pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'credits_monthly' => 5000]);
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    $this->open = ModelCatalog::factory()->create(['slug' => 'open']);
    $this->gated = ModelCatalog::factory()->create(['slug' => 'gated', 'min_plan_id' => $this->pro->id]);

    foreach ([$this->open, $this->gated] as $model) {
        ModelRoute::factory()->forCatalog($model)->create(['execution_provider' => 'openai', 'execution_model_id' => $model->slug]);
    }
});

function givePlan(Plan $plan): void
{
    PlanGrant::factory()->forWorkspace(test()->workspace)->forPlan($plan)->active()->create();
}

it('keeps a model limited to a plan away from a workspace on a lower one', function () {
    $models = app(WorkspaceModels::class);

    expect($models->allows($this->workspace->id, $this->open->id))->toBeTrue()
        ->and($models->allows($this->workspace->id, $this->gated->id))->toBeFalse()
        ->and($models->allowsSlug($this->workspace->id, 'gated'))->toBeFalse()
        ->and($models->planAllows($this->workspace->id, $this->gated->id))->toBeFalse();
});

it('lets in a workspace whose plan reaches the minimum, or goes beyond it', function () {
    givePlan($this->pro);

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeTrue();

    $max = Plan::factory()->create(['slug' => 'max', 'credits_monthly' => 50000]);
    PlanGrant::factory()->forWorkspace($this->workspace)->forPlan($max)->active()->create();

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeTrue();
});

it('loses the model again when the plan lapses', function () {
    givePlan($this->pro);
    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeTrue();

    PlanGrant::query()->update(['status' => 'revoked']);

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeFalse();
});

it('does not make a gated model the default for a workspace that cannot use it', function () {
    WorkspaceModelSettings::query()->create(['workspace_id' => $this->workspace->id, 'default_model_catalog_id' => $this->gated->id]);

    expect(app(WorkspaceModels::class)->default($this->workspace->id))->toBeNull();

    givePlan($this->pro);

    expect(app(WorkspaceModels::class)->default($this->workspace->id)?->id)->toBe($this->gated->id);
});

it('still applies a workspace\'s own allow-list on top of the plan', function () {
    givePlan($this->pro);
    WorkspaceModelSettings::query()->create(['workspace_id' => $this->workspace->id, 'allowed_model_catalog_ids' => [$this->open->id]]);

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeFalse();
});

it('tells the picker why a model is unavailable and what would unlock it', function () {
    $view = WorkspaceModelView::forMember($this->workspace->id, $this->owner->id);

    $gated = $view->describe($this->gated->load(['routes', 'minPlan']));
    $open = $view->describe($this->open->load(['routes', 'minPlan']));

    expect($gated['is_available'])->toBeFalse()
        ->and($gated['unavailable_reason'])->toBe('plan_required')
        ->and($gated['required_plan'])->toBe(['slug' => 'pro', 'name' => 'Pro'])
        ->and($open['is_available'])->toBeTrue()
        ->and($open['required_plan'])->toBeNull();

    givePlan($this->pro);

    $unlocked = WorkspaceModelView::forMember($this->workspace->id, $this->owner->id)->describe($this->gated->load(['routes', 'minPlan']));

    expect($unlocked['is_available'])->toBeTrue()
        ->and($unlocked['required_plan'])->toBe(['slug' => 'pro', 'name' => 'Pro']);
});

it('does not limit anything outside a workspace', function () {
    $view = WorkspaceModelView::platform()->describe($this->gated->load(['routes', 'minPlan']));

    expect($view['is_available'])->toBeTrue()->and($view['required_plan'])->not->toBeNull();
});

it('shows the plan on the model picker API for a member', function () {
    Passport::actingAs($this->owner);

    $models = collect($this->getJson("/api/v1/model-catalog?workspace_id={$this->workspace->id}")->assertOk()->json('data.model_catalog'))->keyBy('slug');

    expect($models['gated']['unavailable_reason'])->toBe('plan_required')
        ->and($models['gated']['required_plan']['slug'])->toBe('pro')
        ->and($models['open']['is_available'])->toBeTrue();
});

it('forgets the limit when its plan is deleted', function () {
    $this->pro->delete();

    expect($this->gated->fresh()->min_plan_id)->toBeNull()
        ->and(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeTrue();
});
