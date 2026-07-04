<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'slug', 'logo', 'settings', 'owner_id', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'trialed_plan_slugs'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'trialed_plan_slugs' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot('id', 'role', 'joined_at')
            ->withTimestamps();
    }

    /** Alias used by scopeBindings() to resolve the {user} route param. */
    public function users(): BelongsToMany
    {
        return $this->members();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function usagePeriods(): HasMany
    {
        return $this->hasMany(UsagePeriod::class);
    }

    public function currentPeriod(): HasOne
    {
        return $this->hasOne(UsagePeriod::class)->where('is_current', true);
    }

    public function creditPacks(): HasMany
    {
        return $this->hasMany(CreditPack::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(Variable::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function replayPacks(): HasMany
    {
        return $this->hasMany(ExecutionReplayPack::class);
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function logStreamingConfigs(): HasMany
    {
        return $this->hasMany(LogStreamingConfig::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    public function environments(): HasMany
    {
        return $this->hasMany(WorkspaceEnvironment::class);
    }

    public function gitSyncConfig(): HasOne
    {
        return $this->hasOne(GitSyncConfig::class);
    }

    public function agentSkills(): HasMany
    {
        return $this->hasMany(AgentSkill::class);
    }

    public function builderSessions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderSession::class);
    }

    public function currentPlan(): ?Plan
    {
        $planId = Cache::remember("workspace:{$this->id}:plan_id", 300, function () {
            $sub = $this->subscription()->with('plan')->first();

            if ($sub && in_array($sub->status, [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Canceled,
            ])) {
                return $sub->plan?->id;
            }

            return Plan::where('slug', 'free')->value('id');
        });

        // Defensive guard: stale Redis cache may hold a serialized model object
        // from before the scalar-ID caching fix. Evict and fall back to free plan.
        if ($planId !== null && ! is_scalar($planId)) {
            Cache::forget("workspace:{$this->id}:plan_id");

            return Plan::where('slug', 'free')->first();
        }

        return $planId ? Plan::find($planId) : null;
    }

    public function invalidatePlanCache(): void
    {
        Cache::forget("workspace:{$this->id}:plan_id");
    }
}
