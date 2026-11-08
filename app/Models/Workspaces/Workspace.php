<?php

namespace App\Models\Workspaces;

use App\Casts\Timezone;
use App\Enums\Billing\Feature;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentSession;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Agents\KnowledgeSource;
use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Artifacts\Artifact;
use App\Models\Auth\ApiKey;
use App\Models\Billing\CreditGrant;
use App\Models\Billing\CreditPack;
use App\Models\Billing\CreditTransaction;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanGrant;
use App\Models\Billing\Subscription;
use App\Models\Billing\UsagePeriod;
use App\Models\Concerns\ResolvesScopedRouteBindings;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Nodes\CustomNode;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationPreference;
use App\Models\Referrals\ReferralReward;
use App\Models\Runs\Run;
use App\Models\Secrets\Secret;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\PageTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Folder;
use App\Models\Workflows\PageSection;
use App\Models\Workflows\Tag;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Services\Billing\CreditWallet;
use App\Services\Billing\UsagePeriods;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'slug', 'avatar', 'timezone', 'owner_id'])]
class Workspace extends Model
{
    use Billable, HasFactory, HasUuids, ResolvesScopedRouteBindings, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'topup_credits' => 0,
        'credit_debt' => 0,
        'timezone' => 'UTC',
        'credit_overage_enabled' => false,
        'out_of_credits_notification_enabled' => true,
        'require_publish_approval' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topup_credits' => 'integer',
            'credit_debt' => 'integer',
            'timezone' => Timezone::class,
            'credit_overage_enabled' => 'boolean',
            'credit_overage_limit' => 'integer',
            'credit_usage_notification_thresholds' => 'array',
            'out_of_credits_notification_enabled' => 'boolean',
            'require_publish_approval' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Locked by a super admin (see `SuperAdminWorkspaceService`): members are
     * refused and no run starts.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Route parameters whose name isn't their relation's. `{member}` is the
     * `User`, found through the workspace's membership rows, and `{session}`
     * under a workspace is a workflow-builder chat (the assistant's own
     * sessions are checked against the member's assistant instead: see
     * routes/api/internal/assistant.php).
     *
     * @return array<string, string>
     */
    protected function scopedRouteBindingRelations(): array
    {
        return [
            'action' => 'agentActions',
            'member' => 'users',
            'node' => 'customNodes',
            'page' => 'workflowPages',
            'section' => 'pageSections',
            'session' => 'builderSessions',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function workflowPages(): HasMany
    {
        return $this->hasMany(WorkflowPage::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function builderSessions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderSession::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function agentSessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function skillSources(): HasMany
    {
        return $this->hasMany(SkillSource::class);
    }

    public function customNodes(): HasMany
    {
        return $this->hasMany(CustomNode::class);
    }

    public function connectorCredentials(): HasMany
    {
        return $this->hasMany(ConnectorCredential::class);
    }

    public function aiProviderCredentials(): HasMany
    {
        return $this->hasMany(AiProviderCredential::class);
    }

    public function aiKeyPolicy(): HasOne
    {
        return $this->hasOne(WorkspaceAiKeyPolicy::class);
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }

    public function agentActions(): HasMany
    {
        return $this->hasMany(AgentAction::class);
    }

    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public function documentEmbeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * @return HasMany<CreditGrant, $this>
     */
    public function creditGrants(): HasMany
    {
        return $this->hasMany(CreditGrant::class);
    }

    public function creditPacks(): HasMany
    {
        return $this->hasMany(CreditPack::class);
    }

    /**
     * @return HasMany<PlanGrant, $this>
     */
    public function planGrants(): HasMany
    {
        return $this->hasMany(PlanGrant::class);
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    public function usagePeriods(): HasMany
    {
        return $this->hasMany(UsagePeriod::class);
    }

    public function workflowTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTemplate::class);
    }

    public function agentTemplates(): HasMany
    {
        return $this->hasMany(AgentTemplate::class);
    }

    public function pageSections(): HasMany
    {
        return $this->hasMany(PageSection::class);
    }

    public function pageTemplates(): HasMany
    {
        return $this->hasMany(PageTemplate::class);
    }

    public function templateCollections(): HasMany
    {
        return $this->hasMany(TemplateCollection::class);
    }

    /**
     * The subscription whose plan currently entitles this workspace:
     * `null` unless Cashier considers it `valid()` (active, on trial, or on
     * its grace period). Cashier's `subscription()` returns the row whatever
     * its status, so reading entitlements straight off it would keep a
     * canceled or `past_due` workspace on its paid plan indefinitely.
     */
    public function activeSubscription(): ?Subscription
    {
        $subscription = $this->subscription('default');

        return $subscription?->valid() ? $subscription : null;
    }

    /**
     * The plan whose credits and limits apply right now, resolved from two
     * independent sources: an active `PlanGrant` (a lifetime purchase or a
     * comp) and a valid Cashier subscription. When a workspace holds both,
     * the *more generous* of the two applies: a lifetime Starter holder who
     * later subscribes to Pro must not be silently downgraded to what they
     * bought outright, and a Pro subscriber who buys a lifetime Starter must
     * not lose their Pro allowance. Falls back to the configured default
     * (Free) plan when neither entitles, so "unsubscribed" means the free
     * tier and not an unmetered one.
     */
    /**
     * Whether changes must be reviewed before they go live: the admin's
     * switch, honoured only while the plan includes approvals. A lapsed plan
     * quietly lifts the rule rather than locking every workflow.
     */
    public function requiresPublishApproval(): bool
    {
        return $this->require_publish_approval
            && (bool) $this->currentPlan()?->hasFeature(Feature::WorkflowApprovals);
    }

    public function currentPlan(): ?Plan
    {
        $candidates = collect([
            $this->activePlanGrant()?->plan,
            $this->activeSubscription()?->plan,
        ])->filter();

        if ($candidates->isEmpty()) {
            return Plan::default();
        }

        return $candidates->sortByDesc(fn (Plan $plan): int => $plan->creditsMonthly())->first();
    }

    /**
     * The workspace's non-subscription entitlement, if any: a lifetime
     * purchase, a comp, or time earned through referrals. When a workspace
     * holds several the *most generous* wins, newest breaking a tie: a
     * lifetime Pro holder who earns a month of referral Starter must not be
     * dropped to Starter for that month, and an upgrade from lifetime
     * Starter to lifetime Pro still lands on Pro. `currentPlan()` only
     * weighs this one grant against the subscription.
     */
    public function activePlanGrant(): ?PlanGrant
    {
        return $this->planGrants()
            ->active()
            ->with('plan')
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn (PlanGrant $grant): int => $grant->plan?->creditsMonthly() ?? 0)
            ->first();
    }

    /**
     * The `UsagePeriod` covering right now, opening one if none does. A
     * subscribed workspace's periods follow its Stripe billing cycle; any
     * other uses the calendar month. The allowance (`credits_limit`) comes
     * from `CreditAllowancePolicy`: plan, trial or free credits. Stays `null`
     * (unlimited) only when no default plan is seeded. See `UsagePeriods`.
     */
    public function currentUsagePeriod(): UsagePeriod
    {
        return app(UsagePeriods::class)->current($this);
    }

    /**
     * Credits spendable right now: what is left of this period's allowance
     * plus every carry-over grant (top-ups, promo, referral, admin), minus
     * any debt, floored at zero (`credit_debt` holds the rest). `null` means
     * unlimited. See `CreditWallet`.
     */
    public function availableCredits(): ?int
    {
        $period = $this->currentUsagePeriod();

        if ($period->credits_limit === null) {
            return null;
        }

        return max(0, app(CreditWallet::class)->balance($this));
    }

    /**
     * Percentages of this period's allowance at which the workspace wants to
     * be told it is running low: Gumloop's Credit Usage Notifications,
     * defaulting to 75% and 90%.
     *
     * `null` means the workspace never chose, so the estate default applies.
     * An empty array is a deliberate "don't tell me" and is honoured as one,
     * which is why this can't just coalesce to the default.
     *
     * Sorted ascending and de-duplicated so `DeductCreditsAction` can reason
     * about "the highest line this charge crossed" without re-normalizing.
     *
     * @return list<int>
     */
    public function creditUsageNotificationThresholds(): array
    {
        $thresholds = $this->credit_usage_notification_thresholds
            ?? config('billing.credit_notifications.default_thresholds', []);

        $thresholds = array_unique(array_map(intval(...), $thresholds));

        sort($thresholds);

        return array_values($thresholds);
    }
}
