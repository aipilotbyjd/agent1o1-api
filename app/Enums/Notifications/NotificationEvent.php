<?php

namespace App\Enums\Notifications;

/**
 * Single source of truth for the toggleable notification-event catalogue:
 * drives both `NotificationPreferenceController::upsert`'s validation and
 * the `/notifications/events` catalogue endpoint the frontend settings
 * screen reads. Ported from the old project's `NotificationEvent`, trimmed
 * to the events that already have a real trigger site in this codebase.
 */
enum NotificationEvent: string
{
    case MemberInvited = 'workspace.member_invited';
    case MemberJoined = 'workspace.member_joined';
    case MemberRemoved = 'workspace.member_removed';
    case MemberRoleChanged = 'workspace.member_role_changed';
    case RunApprovalRequested = 'run.approval_requested';
    case PublishReviewRequested = 'workflow.publish_review_requested';
    case PublishRequestDecided = 'workflow.publish_request_decided';
    case ConnectorCredentialExpired = 'connector.credential_expired';
    case TriggerDisabled = 'trigger.disabled';
    case AiProviderCredentialInvalid = 'ai.provider_credential_invalid';
    case PaymentFailed = 'billing.payment_failed';
    case PaymentRecovered = 'billing.payment_recovered';
    case SubscriptionCanceled = 'billing.subscription_canceled';
    case TrialEnding = 'billing.trial_ending';
    case SubscriptionRenewed = 'billing.subscription_renewed';
    case CreditsLow = 'billing.credits_low';
    case CreditsExhausted = 'billing.credits_exhausted';
    case OverageCapReached = 'billing.overage_cap_reached';
    case RunFailed = 'run.failed';
    case ReflectionRunCompleted = 'agent.reflection_run_completed';
    case SessionEvaluationNotify = 'agent.session_evaluation_notify';
    case EvalRegressed = 'agent.eval_regressed';
    case AgentActionApprovalRequested = 'agent.action_approval_requested';
    case ReferralSignedUp = 'referral.signed_up';
    case ReferralRewardGranted = 'referral.reward_granted';
    case ReferralMilestoneReached = 'referral.milestone_reached';
    case ReferralPlanTimeEnding = 'referral.plan_time_ending';

    public const DEFAULT_IN_APP = true;

    public const DEFAULT_EMAIL = false;

    public function label(): string
    {
        return match ($this) {
            self::MemberInvited => 'Member invited',
            self::MemberJoined => 'Member joined',
            self::MemberRemoved => 'Member removed',
            self::MemberRoleChanged => 'Member role changed',
            self::RunApprovalRequested => 'Run approval requested',
            self::PublishReviewRequested => 'Workflow change needs review',
            self::PublishRequestDecided => 'Workflow change reviewed',
            self::ConnectorCredentialExpired => 'Connector credential expired',
            self::TriggerDisabled => 'Trigger switched off',
            self::AiProviderCredentialInvalid => 'AI provider key stopped working',
            self::PaymentFailed => 'Payment failed',
            self::PaymentRecovered => 'Payment recovered',
            self::SubscriptionCanceled => 'Subscription canceled',
            self::TrialEnding => 'Trial ending soon',
            self::SubscriptionRenewed => 'Subscription renewed',
            self::CreditsLow => 'Credits running low',
            self::CreditsExhausted => 'Credits exhausted',
            self::OverageCapReached => 'Overage cap reached',
            self::RunFailed => 'Run failed',
            self::ReflectionRunCompleted => 'Agent reflection completed',
            self::SessionEvaluationNotify => 'Agent session needs attention',
            self::EvalRegressed => 'Agent eval got worse',
            self::AgentActionApprovalRequested => 'Agent action needs approval',
            self::ReferralSignedUp => 'Someone joined with your referral link',
            self::ReferralRewardGranted => 'Referral reward received',
            self::ReferralMilestoneReached => 'Referral milestone reached',
            self::ReferralPlanTimeEnding => 'Free plan time ending soon',
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
            self::PublishReviewRequested => 'Someone asks for a workflow change to be reviewed before it goes live.',
            self::PublishRequestDecided => 'A change you asked to publish is approved and live, or rejected.',
            self::ConnectorCredentialExpired => 'A connector credential expires and could not be automatically refreshed.',
            self::TriggerDisabled => 'A trigger failed too many times in a row and was switched off.',
            self::AiProviderCredentialInvalid => 'An AI provider key you rely on is rejected by its provider, so the platform\'s own key is used again.',
            self::PaymentFailed => 'A subscription invoice charge fails.',
            self::PaymentRecovered => 'A previously failed subscription charge succeeds and the plan is restored.',
            self::SubscriptionCanceled => 'A subscription ends, including when Stripe gives up after repeated failed charges.',
            self::TrialEnding => 'A trial is about to end and convert to a paid charge.',
            self::SubscriptionRenewed => 'An ordinary subscription renewal charge succeeds.',
            self::CreditsLow => 'A workspace crosses the configured percentage of its credit allowance.',
            self::CreditsExhausted => 'A workspace runs out of credits and new runs are being refused.',
            self::OverageCapReached => 'A workspace spends its whole overage allowance for the period and new runs are being refused.',
            self::RunFailed => 'A workflow or agent run fails.',
            self::ReflectionRunCompleted => 'A scheduled reflection run finishes analyzing an agent\'s recent conversations.',
            self::SessionEvaluationNotify => 'An automatic session evaluation fails a criterion configured to notify.',
            self::EvalRegressed => 'An eval suite that re-ran after an agent change passes fewer of its cases than before.',
            self::AgentActionApprovalRequested => 'An agent pauses to ask before taking an action, such as sending an email or deleting a file.',
            self::ReferralSignedUp => 'Someone signs up using your referral link.',
            self::ReferralRewardGranted => 'Credits, plan time or an invoice credit from the referral program is added.',
            self::ReferralMilestoneReached => 'You reach a referral milestone and earn its bonus.',
            self::ReferralPlanTimeEnding => 'Free plan time earned through referrals is about to run out.',
        };
    }

    /**
     * @return array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}
     */
    public function toCatalogEntry(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'defaults' => [
                'in_app' => self::DEFAULT_IN_APP,
                'email' => self::DEFAULT_EMAIL,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, defaults: array{in_app: bool, email: bool}}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $event): array => $event->toCatalogEntry(), self::cases());
    }
}
