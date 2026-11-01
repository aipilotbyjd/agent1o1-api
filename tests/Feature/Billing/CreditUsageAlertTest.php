<?php

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Notifications\AlertSeverity;
use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Notifications\Admin\AdminAlertNotification;
use App\Notifications\Billing\CreditsExhaustedNotification;
use App\Notifications\Billing\CreditsLowNotification;
use App\Services\Billing\CreditGate;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('admin_alerts.enabled', true);
    config()->set('admin_alerts.recipients.discord', 'https://discord.test/webhook');
    config()->set('admin_alerts.recipients.mail', ['ops@example.com']);
    config()->set('admin_alerts.usage.threshold_percent', 80);
    config()->set('admin_alerts.throttle_seconds', 3600);

    // A workspace now picks its own customer-facing thresholds (75% and 90%
    // by default). Pinning them to the operator's single line keeps these
    // cases about one threshold; the per-workspace behaviour has its own
    // test in `CreditNotificationPreferencesTest`.
    config()->set('billing.credit_notifications.default_thresholds', [80]);
});

function workspaceWithCreditLimit(?int $limit): Workspace
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workspace->currentUsagePeriod()->update(['credits_limit' => $limit]);

    return $workspace;
}

function spendPlanAllowance(Workspace $workspace): void
{
    $period = $workspace->currentUsagePeriod();
    $period->forceFill(['credits_used' => $period->credits_limit])->save();
}

it('alerts admins when a charge crosses the usage threshold', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification) use ($workspace): bool {
            return $notification->key === 'usage.threshold_crossed'
                && $notification->severity === AlertSeverity::Warning
                && $notification->context === [
                    'workspace_id' => $workspace->id,
                    'credits_used' => 80,
                    'credits_limit' => 100,
                    'percent_used' => 80,
                ];
        },
    );
});

it('also notifies the workspace itself when a charge crosses the usage threshold', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);

    Notification::assertSentTo(
        $workspace->owner,
        CreditsLowNotification::class,
        fn (CreditsLowNotification $n): bool => $n->data['percent_used'] === 80,
    );
});

it('stays quiet while usage is below the threshold', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 79);

    Notification::assertNothingSent();
});

it('alerts only on the charge that crosses the threshold, not on later ones', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 85);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 2, 5, allowOverdraft: true);

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});

it('does not re-alert when an already-charged source is billed again', function () {
    $workspace = workspaceWithCreditLimit(100);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);
    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 80);

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});

it('stays quiet for workspaces on an unlimited plan', function () {
    $workspace = workspaceWithCreditLimit(null);
    Notification::fake();

    app(DeductCreditsAction::class)->execute($workspace, CreditTransactionType::NodeRun, 1, 10_000);

    Notification::assertNothingSent();
});

it('raises a critical alert when the gate refuses a workspace that is out of credits', function () {
    $workspace = workspaceWithCreditLimit(10);
    spendPlanAllowance($workspace);

    Notification::fake();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentOnDemand(
        AdminAlertNotification::class,
        function (AdminAlertNotification $notification) use ($workspace): bool {
            return $notification->key === 'usage.limit_exhausted'
                && $notification->severity === AlertSeverity::Critical
                && $notification->context === [
                    'workspace_id' => $workspace->id,
                    'credits_available' => 0,
                    'overage_enabled' => false,
                ];
        },
    );
});

it('throttles the blocked-workspace alert across repeated refusals', function () {
    $workspace = workspaceWithCreditLimit(10);
    spendPlanAllowance($workspace);

    Notification::fake();

    foreach (range(1, 3) as $ignored) {
        expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
            ->toThrow(InsufficientCreditsException::class);
    }

    Notification::assertSentOnDemandTimes(AdminAlertNotification::class, 1);
});

it('stays quiet at the gate while the workspace can still afford to start', function () {
    $workspace = workspaceWithCreditLimit(10);
    Notification::fake();

    app(CreditGate::class)->assertCanStartRun($workspace);

    Notification::assertNothingSent();
});

it('also notifies the workspace when the gate refuses it for lack of credits', function () {
    $workspace = workspaceWithCreditLimit(10);
    spendPlanAllowance($workspace);

    Notification::fake();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentTo($workspace->owner, CreditsExhaustedNotification::class);
});

it('notifies the workspace of exhaustion even when admin alerting is disabled', function () {
    config()->set('admin_alerts.enabled', false);
    $workspace = workspaceWithCreditLimit(10);
    spendPlanAllowance($workspace);

    Notification::fake();

    expect(fn () => app(CreditGate::class)->assertCanStartRun($workspace->fresh()))
        ->toThrow(InsufficientCreditsException::class);

    Notification::assertSentTo($workspace->owner, CreditsExhaustedNotification::class);
});
