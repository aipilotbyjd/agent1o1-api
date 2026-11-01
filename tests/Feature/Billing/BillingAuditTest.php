<?php

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\CheckoutSubscriptionAction;
use App\Actions\Billing\ResumeSubscriptionAction;
use App\Enums\Workspaces\AuditAction;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\User;
use App\Models\Workspaces\AuditLog;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/billing";
    Passport::actingAs($this->owner);
});

it('records subscription cancel and resume against the actor', function () {
    $this->mock(CancelSubscriptionAction::class)->shouldReceive('execute')->andReturn(new Subscription);
    $this->mock(ResumeSubscriptionAction::class)->shouldReceive('execute')->andReturn(new Subscription);

    $this->postJson("{$this->url}/subscription/cancel")->assertOk();
    $this->postJson("{$this->url}/subscription/resume")->assertOk();

    $actions = AuditLog::query()->where('actor_id', $this->owner->id)->pluck('action')->map->value;

    expect($actions)->toContain(AuditAction::SubscriptionCanceled->value, AuditAction::SubscriptionResumed->value);
});

it('records a checkout being started with the plan and interval', function () {
    $plan = Plan::factory()->create();
    $this->mock(CheckoutSubscriptionAction::class)->shouldReceive('execute')->andReturn('https://checkout.example/session');

    $this->postJson("{$this->url}/subscription/checkout", ['plan_id' => $plan->id, 'interval' => 'monthly'])->assertOk();

    $entry = AuditLog::query()->where('action', AuditAction::SubscriptionCheckoutStarted->value)->sole();
    expect($entry->metadata)->toBe(['plan' => $plan->slug, 'interval' => 'monthly']);
});
