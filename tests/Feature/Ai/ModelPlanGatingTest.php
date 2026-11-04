<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Ai\Agents\Workspace\AdHocPromptAgent;
use App\Enums\NodeRunStatus;
use App\Exceptions\ModelNotOnPlanException;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\ModelRoute;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Ai\WorkspaceModels;
use App\Services\Ai\WorkspaceModelView;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Route;
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

it('keeps a model of a gated cost tier away from a lower plan when the model has no minimum of its own', function () {
    config(['billing.model_access.min_plan_by_cost_tier' => [3 => 'pro']]);
    $frontier = ModelCatalog::factory()->create(['slug' => 'frontier', 'cost_tier' => 3]);
    $cheap = ModelCatalog::factory()->create(['slug' => 'cheap', 'cost_tier' => 1]);
    $models = app(WorkspaceModels::class);

    expect($models->planAllows($this->workspace->id, $frontier->id))->toBeFalse()
        ->and($models->planAllows($this->workspace->id, $cheap->id))->toBeTrue();

    givePlan($this->pro);

    expect(app(WorkspaceModels::class)->planAllows($this->workspace->id, $frontier->id))->toBeTrue();
});

it('lets a model\'s own minimum plan win over its cost tier\'s', function () {
    config(['billing.model_access.min_plan_by_cost_tier' => [3 => 'pro']]);
    $openFrontier = ModelCatalog::factory()->create(['slug' => 'open-frontier', 'cost_tier' => 3, 'min_plan_id' => $this->free->id]);

    expect(app(WorkspaceModels::class)->planAllows($this->workspace->id, $openFrontier->id))->toBeTrue();
});

it('leaves a cost tier open when its configured plan does not exist', function () {
    config(['billing.model_access.min_plan_by_cost_tier' => [2 => 'no-such-plan']]);
    $model = ModelCatalog::factory()->create(['slug' => 'mid', 'cost_tier' => 2]);

    expect(app(WorkspaceModels::class)->planAllows($this->workspace->id, $model->id))->toBeTrue();
});

it('describes a model gated only by its cost tier the same way it enforces it', function () {
    config(['billing.model_access.min_plan_by_cost_tier' => [3 => 'pro']]);
    $frontier = ModelCatalog::factory()->create(['slug' => 'frontier', 'cost_tier' => 3]);
    ModelRoute::factory()->forCatalog($frontier)->create(['execution_provider' => 'openai', 'execution_model_id' => 'frontier']);

    $view = WorkspaceModelView::forMember($this->workspace->id, $this->owner->id)->describe($frontier->load(['routes', 'minPlan']));

    expect($view['is_available'])->toBeFalse()
        ->and($view['unavailable_reason'])->toBe('plan_required')
        ->and($view['required_plan'])->toBe(['slug' => 'pro', 'name' => 'Pro'])
        ->and(app(WorkspaceModels::class)->allows($this->workspace->id, $frontier->id))->toBeFalse();
});

it('ranks plans by tier, so a lower plan given more credits does not unlock a higher plan\'s models', function () {
    $this->free->update(['tier' => 1]);
    $this->pro->update(['tier' => 3]);
    $promo = Plan::factory()->create(['slug' => 'promo', 'tier' => 2, 'credits_monthly' => 90000]);
    givePlan($promo);

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeFalse();

    $this->pro->update(['tier' => 2]);

    expect(app(WorkspaceModels::class)->allows($this->workspace->id, $this->gated->id))->toBeTrue();
});

it('falls back to monthly credits when a plan has no tier', function () {
    $this->pro->update(['tier' => 3]);
    $untiered = Plan::factory()->create(['slug' => 'draft', 'tier' => null, 'credits_monthly' => 5000]);

    expect($untiered->reaches($this->pro))->toBeTrue()
        ->and($this->free->reaches($this->pro))->toBeFalse();
});

it('stops running a model the plan no longer includes', function () {
    givePlan($this->pro);
    $models = app(WorkspaceModels::class);

    $models->ensurePlanAllows($this->workspace->id, 'gated');

    PlanGrant::query()->update(['status' => 'revoked']);

    expect(fn () => $models->ensurePlanAllows($this->workspace->id, 'gated'))
        ->toThrow(ModelNotOnPlanException::class, 'needs the Pro plan or higher');
});

it('never limits internal models or raw model ids at run time', function () {
    $internal = ModelCatalog::factory()->create(['slug' => 'helper', 'is_internal' => true, 'min_plan_id' => $this->pro->id]);
    $models = app(WorkspaceModels::class);

    expect(fn () => $models->ensurePlanAllows($this->workspace->id, $internal))->not->toThrow(ModelNotOnPlanException::class)
        ->and(fn () => $models->ensurePlanAllows($this->workspace->id, 'gpt-raw-model-id'))->not->toThrow(ModelNotOnPlanException::class)
        ->and(fn () => $models->ensurePlanAllows($this->workspace->id, null))->not->toThrow(ModelNotOnPlanException::class);
});

it('fails a workflow AI step saved before a downgrade instead of running the gated model', function () {
    AdHocPromptAgent::fake(['ok']);

    $workflow = Workflow::factory()->forWorkspace($this->workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'ask', 'type' => 'ask_ai', 'config' => ['prompt' => 'Say hi', 'model_catalog_slug' => 'gated']],
        ],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $this->owner);

    $run = app(StartWorkflowRunAction::class)->execute($workflow->fresh(), []);
    $step = $run->fresh(['nodeRuns'])->nodeRuns->firstWhere('key', 'ask');

    expect($step->status)->toBe(NodeRunStatus::Failed)
        ->and($step->error)->toContain('needs the Pro plan or higher');

    AdHocPromptAgent::assertNeverPrompted();
});

it('answers a gated model with a 402 that names the plan to upgrade to', function () {
    Route::middleware('api')->get('/api/v1/__test-model-plan', function () {
        app(WorkspaceModels::class)->ensurePlanAllows(test()->workspace->id, 'gated');
    });

    $this->getJson('/api/v1/__test-model-plan')
        ->assertStatus(402)
        ->assertJsonPath('errors.reason', 'plan_required')
        ->assertJsonPath('errors.model', 'gated')
        ->assertJsonPath('errors.required_plan.slug', 'pro');
});
