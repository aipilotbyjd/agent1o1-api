<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Admin\AdminAlertNotification;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Notifications\Billing\CreditsLowNotification;
use App\Services\Billing\CreditGate;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

/**
 * Gumloop's Credit Notification Preferences: usage thresholds the workspace
 * picks for itself (75% and 90% by default), and an out-of-credits switch.
 * Distinct from `admin_alerts.usage.threshold_percent`, which is the one
 * estate-wide line the operator watches.
 */
beforeEach(function () {
    config()->set('billing.credit_notifications.default_thresholds', [75, 90]);

    // The operator alert needs a destination before `AdminAlerts` will raise
    // anything, and these cases assert on both sides of that split.
    config()->set('admin_alerts.enabled', true);
    config()->set('admin_alerts.recipients.mail', ['ops@example.com']);
    config()->set('admin_alerts.usage.threshold_percent', 80);
});

function workspaceWithThresholds(?array $thresholds, int $creditsLimit = 100): Workspace
{
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => $creditsLimit]);
    $workspace->forceFill(['credit_usage_notification_thresholds' => $thresholds])->save();

    return $workspace->fresh();
}

it('warns at the default thresholds when the workspace picked none', function () {
    $workspace = workspaceWithThresholds(null);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 75);

    Notification::assertSentTo(
        $workspace->owner,
        CreditsLowNotification::class,
        fn (CreditsLowNotification $n): bool => $n->data['threshold_percent'] === 75,
    );
});

it('warns at a threshold the workspace chose for itself', function () {
    $workspace = workspaceWithThresholds([40]);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 40);

    Notification::assertSentTo(
        $workspace->owner,
        CreditsLowNotification::class,
        fn (CreditsLowNotification $n): bool => $n->data['threshold_percent'] === 40,
    );
});

it('stays quiet at a threshold the workspace removed', function () {
    $workspace = workspaceWithThresholds([90]);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 75);

    Notification::assertNotSentTo($workspace->owner, CreditsLowNotification::class);
});

it('sends no usage warning at all when the workspace cleared every threshold', function () {
    $workspace = workspaceWithThresholds([]);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 95);

    Notification::assertNotSentTo($workspace->owner, CreditsLowNotification::class);
});

it('sends one warning for the highest line a single charge jumps', function () {
    $workspace = workspaceWithThresholds([75, 90]);
    Notification::fake();

    // 0% straight to 95% crosses both thresholds, but it is one piece of news.
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 95);

    Notification::assertSentToTimes($workspace->owner, CreditsLowNotification::class, 1);
    Notification::assertSentTo(
        $workspace->owner,
        CreditsLowNotification::class,
        fn (CreditsLowNotification $n): bool => $n->data['threshold_percent'] === 90,
    );
});

it('warns again at each further threshold as usage climbs', function () {
    $workspace = workspaceWithThresholds([75, 90]);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 15);

    Notification::assertSentToTimes($workspace->owner, CreditsLowNotification::class, 2);
});

it('keeps the operator alert on its own estate-wide threshold', function () {
    $workspace = workspaceWithThresholds([40]);
    Notification::fake();

    // Crosses the workspace's 40% line but not the operator's 80% one.
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 45);

    Notification::assertSentTo($workspace->owner, CreditsLowNotification::class);
    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 0);
});

it('silences the out-of-credits notification when the workspace turns it off', function () {
    $workspace = workspaceWithThresholds(null, creditsLimit: 10);
    $workspace->forceFill(['out_of_credits_notification_enabled' => false])->save();
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => $period->credits_limit])->save();

    Notification::fake();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertNotSentTo($workspace->owner, CreditsExhaustedNotification::class);

    // The operator still hears about a blocked tenant: that switch is the
    // customer's, not theirs.
    Notification::assertSentOnDemand(AdminAlertNotification::class);
});

it('reads the credit notification preferences back over the API', function () {
    $workspace = workspaceWithThresholds(null);

    Passport::actingAs($workspace->owner);

    $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications");

    $response->assertOk();
    expect($response->json('data.credit_notifications'))->toMatchArray([
        'out_of_credits_enabled' => true,
        'thresholds' => [75, 90],
        'is_default' => true,
        'default_thresholds' => [75, 90],
    ]);
});

it('replaces the whole threshold set, sorted and de-duplicated', function () {
    $workspace = workspaceWithThresholds([75, 90]);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'thresholds' => [95, 50, 50],
    ])->assertOk()->assertJsonPath('data.credit_notifications.thresholds', [50, 95]);

    expect($workspace->fresh()->credit_usage_notification_thresholds)->toBe([50, 95]);
});

it('resets to the defaults when thresholds are cleared with null', function () {
    $workspace = workspaceWithThresholds([50]);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'thresholds' => null,
    ])->assertOk()->assertJsonPath('data.credit_notifications.is_default', true);

    expect($workspace->fresh()->credit_usage_notification_thresholds)->toBeNull();
});

it('keeps an empty threshold set distinct from a reset', function () {
    $workspace = workspaceWithThresholds([50]);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'thresholds' => [],
    ])->assertOk()->assertJsonPath('data.credit_notifications.is_default', false);

    expect($workspace->fresh()->credit_usage_notification_thresholds)->toBe([]);
});

it('leaves a setting alone when its key is omitted', function () {
    $workspace = workspaceWithThresholds([50]);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'out_of_credits_enabled' => false,
    ])->assertOk();

    expect($workspace->fresh()->credit_usage_notification_thresholds)->toBe([50])
        ->and($workspace->fresh()->out_of_credits_notification_enabled)->toBeFalse();
});

it('rejects a threshold that is not a percentage', function () {
    $workspace = workspaceWithThresholds(null);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'thresholds' => [150],
    ])->assertStatus(422)->assertJsonValidationErrors('thresholds.0');
});

it('rejects more thresholds than the configured maximum', function () {
    config()->set('billing.credit_notifications.maximum_thresholds', 2);
    $workspace = workspaceWithThresholds(null);

    Passport::actingAs($workspace->owner);

    $this->putJson("/api/v1/workspaces/{$workspace->id}/billing/credit-notifications", [
        'thresholds' => [10, 20, 30],
    ])->assertStatus(422)->assertJsonValidationErrors('thresholds');
});
