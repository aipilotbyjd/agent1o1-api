<?php

namespace App\Services;

use App\Enums\DiscoverySource;
use App\Enums\JobRole;
use App\Enums\OnboardingStep;
use App\Models\Credential;
use App\Models\CredentialType;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnboardingService
{
    /**
     * Compute the full onboarding wizard state for a user.
     *
     * @return array{dismissed: bool, completed: bool, percent: int, current_step: string|null, steps: list<array{key: string, label: string, description: string, completed: bool}>, meta: array{plans: list<mixed>, credential_types: list<mixed>, job_roles: list<mixed>, discovery_sources: list<mixed>, workspace_slug_suggestion: string}}
     */
    public function state(User $user): array
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        $completion = $this->resolveCompletion($user, $workspaceIds);

        $steps = array_map(fn (OnboardingStep $step) => [
            'key' => $step->value,
            'label' => $step->label(),
            'description' => $step->description(),
            'completed' => $completion[$step->value],
        ], OnboardingStep::cases());

        $done = count(array_filter($completion));
        $total = count($completion);

        $currentStep = null;
        foreach (OnboardingStep::cases() as $step) {
            if (! $completion[$step->value]) {
                $currentStep = $step->value;
                break;
            }
        }

        return [
            'dismissed' => $user->onboarding_dismissed_at !== null,
            'completed' => $done === $total,
            'percent' => (int) round(($done / $total) * 100),
            'current_step' => $currentStep,
            'steps' => $steps,
            'meta' => $this->buildMeta($user),
        ];
    }

    public function saveRole(User $user, JobRole $role): void
    {
        $user->update(['job_role' => $role]);
    }

    public function saveDiscovery(User $user, DiscoverySource $source): void
    {
        $user->update(['discovery_source' => $source]);
    }

    public function savePlanSelection(User $user): void
    {
        // Placeholder — actual plan selection goes through the billing flow.
        // This method exists so the controller can mark the step progressed
        // when the user explicitly picks the free plan.
    }

    /**
     * @param  Collection<int, string>  $workspaceIds
     * @return array<string, bool>
     */
    private function resolveCompletion(User $user, Collection $workspaceIds): array
    {
        $hasSentInvitation = $workspaceIds->isNotEmpty()
            && Invitation::whereIn('workspace_id', $workspaceIds)->exists();

        $hasExtraMembers = $workspaceIds->isNotEmpty()
            && DB::table('workspace_members')
                ->whereIn('workspace_id', $workspaceIds)
                ->where('user_id', '!=', $user->id)
                ->exists();

        $hasSubscription = $workspaceIds->isNotEmpty()
            && Subscription::whereIn('workspace_id', $workspaceIds)->exists();

        return [
            OnboardingStep::ProfilePicture->value => $user->avatar !== null,
            OnboardingStep::CreateWorkspace->value => $workspaceIds->isNotEmpty(),
            OnboardingStep::InviteTeam->value => $hasSentInvitation || $hasExtraMembers,
            OnboardingStep::RoleSelection->value => $user->job_role !== null,
            OnboardingStep::ChoosePlan->value => $hasSubscription,
            OnboardingStep::ConnectApps->value => $workspaceIds->isNotEmpty()
                && Credential::whereIn('workspace_id', $workspaceIds)->exists(),
            OnboardingStep::DiscoverySurvey->value => $user->discovery_source !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function buildMeta(User $user): array
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'features' => $plan->features,
                'limits' => $plan->limits,
                'trial_days' => $plan->trial_days,
            ])
            ->values()
            ->all();

        $credentialTypes = CredentialType::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CredentialType $ct) => [
                'id' => $ct->id,
                'key' => $ct->key,
                'name' => $ct->name,
                'description' => $ct->description,
                'auth_type' => $ct->auth_type,
                'icon' => $ct->icon,
            ])
            ->values()
            ->all();

        $jobRoles = array_map(fn (JobRole $r) => [
            'value' => $r->value,
            'label' => $r->label(),
            'description' => $r->description(),
        ], JobRole::cases());

        $discoverySources = array_map(fn (DiscoverySource $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ], DiscoverySource::cases());

        $slugSuggestion = $user->name
            ? Str::slug(explode(' ', $user->name)[0].'-automation')
            : 'my-workspace';

        return [
            'plans' => $plans,
            'credential_types' => $credentialTypes,
            'job_roles' => $jobRoles,
            'discovery_sources' => $discoverySources,
            'workspace_slug_suggestion' => $slugSuggestion,
        ];
    }
}
