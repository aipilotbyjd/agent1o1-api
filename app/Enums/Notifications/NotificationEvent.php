<?php

namespace App\Enums\Notifications;

use App\Notifications\Definitions\NotificationDefinition;
use App\Notifications\Definitions\NotificationGrouping;

/**
 * The single source of truth for every member-facing notification: its
 * label, its `definition()` (category, severity, scope, defaults, grouping)
 * and where following it leads (`actionPath()`).
 *
 * It drives preference validation, channel selection
 * (`AppNotification::via()`), integration routing, the inbox columns and the
 * `/notifications/catalog` endpoint the frontend renders its settings and
 * bell from, so adding an event needs no frontend change.
 *
 * Operator alerts are not here: they live in `AdminAlerts` and
 * `AlertRuleCatalog` and must never reach a member.
 */
enum NotificationEvent: string
{
    case MemberInvited = 'workspace.member_invited';
    case MemberJoined = 'workspace.member_joined';
    case MemberRemoved = 'workspace.member_removed';
    case MemberRoleChanged = 'workspace.member_role_changed';
    case RunApprovalRequested = 'run.approval_requested';
    case RunFailed = 'run.failed';
    case PublishReviewRequested = 'workflow.publish_review_requested';
    case PublishRequestDecided = 'workflow.publish_request_decided';
    case TriggerDisabled = 'trigger.disabled';
    case ConnectorCredentialExpired = 'connector.credential_expired';
    case AiProviderCredentialInvalid = 'ai.provider_credential_invalid';
    case AiProviderAvailabilityChanged = 'ai.provider_availability_changed';
    case IntegrationChannelDisabled = 'integration.channel_disabled';
    case AgentActionApprovalRequested = 'agent.action_approval_requested';
    case SessionEvaluationNotify = 'agent.session_evaluation_notify';
    case EvalRegressed = 'agent.eval_regressed';
    case ReflectionRunCompleted = 'agent.reflection_run_completed';
    case PaymentFailed = 'billing.payment_failed';
    case PaymentRecovered = 'billing.payment_recovered';
    case SubscriptionCanceled = 'billing.subscription_canceled';
    case TrialEnding = 'billing.trial_ending';
    case SubscriptionRenewed = 'billing.subscription_renewed';
    case CreditsLow = 'billing.credits_low';
    case CreditsExhausted = 'billing.credits_exhausted';
    case OverageCapReached = 'billing.overage_cap_reached';
    case ReferralSignedUp = 'referral.signed_up';
    case ReferralRewardGranted = 'referral.reward_granted';
    case ReferralMilestoneReached = 'referral.milestone_reached';
    case ReferralPlanTimeEnding = 'referral.plan_time_ending';
    case AssistantDailyReport = 'assistant.daily_report';
    case AssistantMeetingBrief = 'assistant.meeting_brief';
    case AssistantFollowUp = 'assistant.follow_up';
    case AssistantTriggerDisabled = 'assistant.trigger_disabled';
    case SecurityAlert = 'auth.security_alert';
    case PlatformMessage = 'admin.message';

    public function definition(): NotificationDefinition
    {
        return match ($this) {
            self::MemberInvited, self::MemberRemoved, self::MemberRoleChanged => new NotificationDefinition(
                category: NotificationCategory::Workspace,
                severity: NotificationSeverity::Info,
                actionLabel: 'Open members',
            ),
            self::MemberJoined => new NotificationDefinition(
                category: NotificationCategory::Workspace,
                severity: NotificationSeverity::Success,
                actionLabel: 'Open members',
            ),
            self::RunApprovalRequested => new NotificationDefinition(
                category: NotificationCategory::Runs,
                severity: NotificationSeverity::Warning,
                needsAction: true,
                actionLabel: 'Review approval',
            ),
            self::RunFailed => new NotificationDefinition(
                category: NotificationCategory::Runs,
                severity: NotificationSeverity::Critical,
                grouping: new NotificationGrouping(by: 'workflow_id', windowMinutes: 60),
                actionLabel: 'View run',
            ),
            self::PublishReviewRequested => new NotificationDefinition(
                category: NotificationCategory::Runs,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Review changes',
            ),
            self::PublishRequestDecided => new NotificationDefinition(
                category: NotificationCategory::Runs,
                severity: NotificationSeverity::Info,
                actionLabel: 'Open workflow',
            ),
            self::TriggerDisabled => new NotificationDefinition(
                category: NotificationCategory::Triggers,
                severity: NotificationSeverity::Critical,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Open triggers',
            ),
            self::ConnectorCredentialExpired => new NotificationDefinition(
                category: NotificationCategory::Connections,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                needsAction: true,
                grouping: new NotificationGrouping(by: 'connector_credential_id', windowMinutes: 24 * 60),
                actionLabel: 'Reconnect app',
            ),
            self::AiProviderCredentialInvalid => new NotificationDefinition(
                category: NotificationCategory::Connections,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                needsAction: true,
                grouping: new NotificationGrouping(by: 'ai_provider_credential_id', windowMinutes: 24 * 60),
                actionLabel: 'Open AI providers',
            ),
            self::AiProviderAvailabilityChanged => new NotificationDefinition(
                category: NotificationCategory::Connections,
                severity: NotificationSeverity::Info,
                configurable: false,
                actionLabel: 'Open AI providers',
            ),
            self::IntegrationChannelDisabled => new NotificationDefinition(
                category: NotificationCategory::Connections,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Open channels',
            ),
            self::AgentActionApprovalRequested => new NotificationDefinition(
                category: NotificationCategory::Agents,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Review actions',
            ),
            self::SessionEvaluationNotify => new NotificationDefinition(
                category: NotificationCategory::Agents,
                severity: NotificationSeverity::Warning,
                grouping: new NotificationGrouping(by: 'agent_id', windowMinutes: 60),
                actionLabel: 'Open grading',
            ),
            self::EvalRegressed => new NotificationDefinition(
                category: NotificationCategory::Agents,
                severity: NotificationSeverity::Critical,
                actionLabel: 'Open evals',
            ),
            self::ReflectionRunCompleted => new NotificationDefinition(
                category: NotificationCategory::Agents,
                severity: NotificationSeverity::Info,
                actionLabel: 'Review suggestions',
            ),
            self::PaymentFailed => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Critical,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Update payment',
            ),
            self::PaymentRecovered => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Success,
                actionLabel: 'Open billing',
            ),
            self::SubscriptionRenewed => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Success,
                actionLabel: 'Open billing',
            ),
            self::SubscriptionCanceled => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Warning,
                actionLabel: 'View plans',
            ),
            self::TrialEnding => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Warning,
                defaultEmail: true,
                actionLabel: 'View plans',
            ),
            self::CreditsLow => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Warning,
                actionLabel: 'Top up credits',
            ),
            self::CreditsExhausted => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Critical,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Top up credits',
            ),
            self::OverageCapReached => new NotificationDefinition(
                category: NotificationCategory::Billing,
                severity: NotificationSeverity::Critical,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Open credits',
            ),
            self::ReferralSignedUp => new NotificationDefinition(
                category: NotificationCategory::Referrals,
                severity: NotificationSeverity::Info,
                actionLabel: 'View referrals',
            ),
            self::ReferralRewardGranted => new NotificationDefinition(
                category: NotificationCategory::Referrals,
                severity: NotificationSeverity::Success,
                actionLabel: 'View rewards',
            ),
            self::ReferralMilestoneReached => new NotificationDefinition(
                category: NotificationCategory::Referrals,
                severity: NotificationSeverity::Success,
                actionLabel: 'View referrals',
            ),
            self::ReferralPlanTimeEnding => new NotificationDefinition(
                category: NotificationCategory::Referrals,
                severity: NotificationSeverity::Warning,
                actionLabel: 'View plans',
            ),
            self::AssistantDailyReport, self::AssistantMeetingBrief => new NotificationDefinition(
                category: NotificationCategory::Assistant,
                severity: NotificationSeverity::Info,
                scope: NotificationScope::Account,
                defaultInApp: false,
                defaultEmail: true,
                actionLabel: 'Open assistant',
            ),
            self::AssistantFollowUp => new NotificationDefinition(
                category: NotificationCategory::Assistant,
                severity: NotificationSeverity::Info,
                scope: NotificationScope::Account,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Open assistant',
            ),
            self::AssistantTriggerDisabled => new NotificationDefinition(
                category: NotificationCategory::Assistant,
                severity: NotificationSeverity::Warning,
                scope: NotificationScope::Account,
                defaultEmail: true,
                needsAction: true,
                actionLabel: 'Open triggers',
            ),
            self::SecurityAlert => new NotificationDefinition(
                category: NotificationCategory::Security,
                severity: NotificationSeverity::Warning,
                scope: NotificationScope::Account,
                defaultEmail: true,
                mandatory: true,
            ),
            self::PlatformMessage => new NotificationDefinition(
                category: NotificationCategory::ProductUpdates,
                severity: NotificationSeverity::Info,
                scope: NotificationScope::Account,
                mandatory: true,
                configurable: false,
            ),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MemberInvited => 'Member invited',
            self::MemberJoined => 'Member joined',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Member role changed',
            self::RunApprovalRequested => 'Run approval requested',
            self::RunFailed => 'Run failed',
            self::PublishReviewRequested => 'Workflow change needs review',
            self::PublishRequestDecided => 'Workflow change reviewed',
            self::TriggerDisabled => 'Trigger switched off',
            self::ConnectorCredentialExpired => 'Connector credential expired',
            self::AiProviderCredentialInvalid => 'AI provider key stopped working',
            self::AiProviderAvailabilityChanged => 'AI provider turned off or back on',
            self::IntegrationChannelDisabled => 'Chat or webhook channel switched off',
            self::AgentActionApprovalRequested => 'Agent action needs approval',
            self::SessionEvaluationNotify => 'Agent session needs attention',
            self::EvalRegressed => 'Agent eval got worse',
            self::ReflectionRunCompleted => 'Agent reflection completed',
            self::PaymentFailed => 'Payment failed',
            self::PaymentRecovered => 'Payment recovered',
            self::SubscriptionCanceled => 'Subscription canceled',
            self::TrialEnding => 'Trial ending soon',
            self::SubscriptionRenewed => 'Subscription renewed',
            self::CreditsLow => 'Credits running low',
            self::CreditsExhausted => 'Credits exhausted',
            self::OverageCapReached => 'Overage cap reached',
            self::ReferralSignedUp => 'Someone joined with your referral link',
            self::ReferralRewardGranted => 'Referral reward received',
            self::ReferralMilestoneReached => 'Referral milestone reached',
            self::ReferralPlanTimeEnding => 'Free plan time ending soon',
            self::AssistantDailyReport => 'Daily report',
            self::AssistantMeetingBrief => 'Meeting brief',
            self::AssistantFollowUp => 'Follow-up reminder',
            self::AssistantTriggerDisabled => 'Assistant trigger switched off',
            self::SecurityAlert => 'Security alert',
            self::PlatformMessage => 'Message from the team',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MemberInvited => 'Someone is invited to join the workspace.',
            self::MemberJoined => 'An invited member accepts and joins the workspace.',
            self::MemberRemoved => 'A member is removed from the workspace.',
            self::MemberRoleChanged => "A member's role in the workspace changes.",
            self::RunApprovalRequested => 'A workflow run pauses awaiting human approval.',
            self::RunFailed => 'A workflow or agent run fails. Repeats for the same workflow within an hour are grouped.',
            self::PublishReviewRequested => 'Someone asks for a workflow change to be reviewed before it goes live.',
            self::PublishRequestDecided => 'A change you asked to publish is approved and live, or rejected.',
            self::TriggerDisabled => 'A trigger failed too many times in a row and was switched off.',
            self::ConnectorCredentialExpired => 'A connector credential expires and could not be automatically refreshed.',
            self::AiProviderCredentialInvalid => 'An AI provider key or connection you rely on is rejected by its provider or can\'t be renewed.',
            self::AiProviderAvailabilityChanged => 'We turned off, or turned back on, an AI provider your workspace has a key for.',
            self::IntegrationChannelDisabled => 'A Slack, Discord or webhook channel kept failing and was switched off.',
            self::AgentActionApprovalRequested => 'An agent pauses to ask before taking an action, such as sending an email or deleting a file.',
            self::SessionEvaluationNotify => 'An automatic session evaluation fails a criterion configured to notify.',
            self::EvalRegressed => 'An eval suite that re-ran after an agent change passes fewer of its cases than before.',
            self::ReflectionRunCompleted => 'A scheduled reflection run finishes analyzing an agent\'s recent conversations.',
            self::PaymentFailed => 'A subscription invoice charge fails.',
            self::PaymentRecovered => 'A previously failed subscription charge succeeds and the plan is restored.',
            self::SubscriptionCanceled => 'A subscription ends, including when Stripe gives up after repeated failed charges.',
            self::TrialEnding => 'A trial is about to end and convert to a paid charge.',
            self::SubscriptionRenewed => 'An ordinary subscription renewal charge succeeds.',
            self::CreditsLow => 'A workspace crosses the configured percentage of its credit allowance.',
            self::CreditsExhausted => 'A workspace runs out of credits and new runs are being refused.',
            self::OverageCapReached => 'A workspace spends its whole overage allowance for the period and new runs are being refused.',
            self::ReferralSignedUp => 'Someone signs up using your referral link.',
            self::ReferralRewardGranted => 'Credits, plan time or an invoice credit from the referral program is added.',
            self::ReferralMilestoneReached => 'You reach a referral milestone and earn its bonus.',
            self::ReferralPlanTimeEnding => 'Free plan time earned through referrals is about to run out.',
            self::AssistantDailyReport => 'Your assistant finishes your daily report.',
            self::AssistantMeetingBrief => 'Your assistant prepares a brief for an upcoming meeting.',
            self::AssistantFollowUp => 'Nobody has replied to an email you asked your assistant to follow up on.',
            self::AssistantTriggerDisabled => 'One of your assistant\'s triggers failed too many times in a row and was switched off.',
            self::SecurityAlert => 'A new sign-in or an important change to your account.',
            self::PlatformMessage => 'News and announcements sent by the team.',
        };
    }

    /**
     * Where following the notification leads: a path inside the app,
     * relative to its root, or null when there is nowhere useful to go.
     *
     * Built from what is stored on the notification, never from live
     * models, so a notification about something since deleted still renders
     * and old rows get links without a backfill.
     *
     * @param  array<string, mixed>  $data
     */
    public function actionPath(?string $workspaceId, array $data): ?string
    {
        if ($this === self::PlatformMessage) {
            return self::stringFrom($data, 'action_url');
        }

        if ($workspaceId === null) {
            return null;
        }

        $workspace = '/'.rawurlencode($workspaceId);
        $id = fn (string $key): ?string => self::stringFrom($data, $key);

        return match ($this) {
            self::MemberInvited, self::MemberJoined, self::MemberRemoved, self::MemberRoleChanged => "{$workspace}/settings/members",
            self::RunApprovalRequested, self::RunFailed => $id('run_id') !== null
                ? "{$workspace}/trail?run=".rawurlencode($id('run_id'))
                : "{$workspace}/trail",
            self::PublishReviewRequested, self::PublishRequestDecided => $id('workflow_id') === null ? null
                : "{$workspace}/playbooks/".rawurlencode($id('workflow_id')).'/edit'
                    .($id('publish_request_id') !== null ? '?publish_request='.rawurlencode($id('publish_request_id')) : ''),
            self::TriggerDisabled => "{$workspace}/settings/triggers",
            self::ConnectorCredentialExpired => "{$workspace}/apps"
                .($id('connector_credential_id') !== null ? '?account='.rawurlencode($id('connector_credential_id')) : ''),
            self::AiProviderCredentialInvalid, self::AiProviderAvailabilityChanged => "{$workspace}/settings/ai-providers",
            self::IntegrationChannelDisabled => "{$workspace}/settings/notification-channels",
            self::AgentActionApprovalRequested => "{$workspace}/approvals",
            self::SessionEvaluationNotify => self::agentInsights($workspace, $id('agent_id'), 'grading'),
            self::EvalRegressed => self::agentInsights($workspace, $id('agent_id'), 'evals'),
            self::ReflectionRunCompleted => self::agentInsights($workspace, $id('agent_id'), 'reflections'),
            self::PaymentFailed, self::PaymentRecovered, self::SubscriptionRenewed => "{$workspace}/settings/billing",
            self::SubscriptionCanceled, self::TrialEnding, self::ReferralPlanTimeEnding => "{$workspace}/settings/billing/plans",
            self::CreditsLow, self::CreditsExhausted, self::OverageCapReached => "{$workspace}/settings/billing/credits",
            self::ReferralSignedUp, self::ReferralRewardGranted, self::ReferralMilestoneReached => "{$workspace}/settings/referrals",
            self::AssistantDailyReport, self::AssistantMeetingBrief, self::AssistantFollowUp, self::AssistantTriggerDisabled => "{$workspace}/assistant",
            self::SecurityAlert => "{$workspace}/settings/security",
            self::PlatformMessage => null,
        };
    }

    /**
     * The label of the notification's button: the event's own, or the one a
     * super admin wrote for a platform message.
     *
     * @param  array<string, mixed>  $data
     */
    public function actionLabel(array $data): ?string
    {
        if ($this === self::PlatformMessage) {
            return self::stringFrom($data, 'action_label');
        }

        return $this->definition()->actionLabel;
    }

    /**
     * Events whose delivery the member decides on the settings page.
     *
     * @return array<int, self>
     */
    public static function configurable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $event): bool => $event->definition()->configurable && ! $event->definition()->mandatory,
        ));
    }

    /**
     * @return array<int, self>
     */
    public static function inCategory(NotificationCategory $category): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $event): bool => $event->definition()->category === $category,
        ));
    }

    /**
     * Events the bell's "Needs action" filter shows.
     *
     * @return array<int, string>
     */
    public static function needingAction(): array
    {
        return array_values(array_map(
            fn (self $event): string => $event->value,
            array_filter(self::cases(), fn (self $event): bool => $event->definition()->needsAction),
        ));
    }

    /**
     * @return array{key: string, label: string, description: string, category: string, severity: string, scope: string, mandatory: bool, configurable: bool, needs_action: bool, grouped: bool, defaults: array{in_app: bool, email: bool}}
     */
    public function toCatalogEntry(): array
    {
        $definition = $this->definition();

        return [
            'key' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'category' => $definition->category->value,
            'severity' => $definition->severity->value,
            'scope' => $definition->scope->value,
            'mandatory' => $definition->mandatory,
            'configurable' => $definition->configurable && ! $definition->mandatory,
            'needs_action' => $definition->needsAction,
            'grouped' => $definition->grouping !== null,
            'defaults' => $definition->defaults(),
        ];
    }

    /**
     * The flat list of events, as `GET /notifications/events` has always
     * returned it.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $event): array => $event->toCatalogEntry(), self::cases());
    }

    /**
     * Events grouped by category in display order: what the settings page and
     * the bell's filters render.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, scope: string, preference_key: string, events: array<int, array<string, mixed>>}>
     */
    public static function categorizedCatalog(): array
    {
        $categories = [];

        foreach (NotificationCategory::cases() as $category) {
            $events = self::inCategory($category);

            if ($events === []) {
                continue;
            }

            $categories[] = [
                'key' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
                'icon' => $category->icon(),
                'scope' => $category->isAccountLevel() ? NotificationScope::Account->value : NotificationScope::Workspace->value,
                'preference_key' => $category->preferenceKey(),
                'events' => array_map(fn (self $event): array => $event->toCatalogEntry(), $events),
            ];
        }

        return $categories;
    }

    private static function agentInsights(string $workspace, ?string $agentId, string $tab): ?string
    {
        return $agentId === null ? null : "{$workspace}/agents/".rawurlencode($agentId)."/insights?tab={$tab}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringFrom(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
