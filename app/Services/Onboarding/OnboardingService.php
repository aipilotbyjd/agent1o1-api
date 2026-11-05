<?php

namespace App\Services\Onboarding;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Onboarding\JobRole;
use App\Enums\Onboarding\OnboardingStep;
use App\Enums\Workspaces\Role;
use App\Http\Resources\Api\Internal\V1\Billing\PlanResource;
use App\Models\Ai\AiProviderCredential;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Services\Workspaces\WorkspaceInvitationService;
use App\Services\Workspaces\WorkspaceService;

class OnboardingService
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly WorkspaceInvitationService $invitations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function state(User $user): array
    {
        $currentStep = $user->onboarding_current_step ?? OnboardingStep::ProfilePicture;

        $steps = collect(OnboardingStep::cases())->map(fn (OnboardingStep $step) => [
            'key' => $step->value,
            'label' => $step->label(),
            'description' => $step->description(),
            'completed' => $this->isStepCompleted($user, $step, $currentStep),
            'skipped' => $this->isStepSkipped($user, $step),
        ]);

        $percent = (int) round($steps->where('completed', true)->count() / $steps->count() * 100);

        return [
            'dismissed' => $user->onboarding_dismissed_at !== null,
            'completed' => $user->hasCompletedOnboarding(),
            'percent' => $percent,
            'current_step' => $currentStep->value,
            'steps' => $steps->all(),
            'meta' => $this->meta($user),
        ];
    }

    /**
     * @param  array<int, string>  $emails
     */
    public function inviteTeam(User $user, array $emails, Role $role, ?string $note): array
    {
        $workspace = $user->currentWorkspace;

        abort_if($workspace === null, 422, 'Create a workspace before inviting your team.');

        foreach ($emails as $email) {
            $this->invitations->invite($workspace, $email, $role, $user);
        }

        $this->advancePast($user, OnboardingStep::InviteTeam);

        return $this->state($user->fresh());
    }

    public function selectRole(User $user, JobRole $role): array
    {
        $user->update(['job_role' => $role]);

        $this->advancePast($user, OnboardingStep::RoleSelection);

        return $this->state($user->fresh());
    }

    public function selectPlan(User $user, string $planSlug): array
    {
        Plan::query()->where('slug', $planSlug)->where('is_active', true)->firstOrFail();

        // Free is the only plan actionable here; paid plans go through Stripe checkout separately.
        $this->advancePast($user, OnboardingStep::ChoosePlan);

        return $this->state($user->fresh());
    }

    public function submitDiscovery(User $user, DiscoverySource $source): array
    {
        $user->update(['discovery_source' => $source]);

        $this->advancePast($user, OnboardingStep::DiscoverySurvey);

        return $this->state($user->fresh());
    }

    /**
     * Saves that the user finished a step and pressed Continue, so signing in
     * again resumes after it. Steps that exist to collect something must have
     * it: a profile picture, a workspace.
     */
    public function continue(User $user, OnboardingStep $step): array
    {
        abort_if(
            $step === OnboardingStep::ProfilePicture && $user->avatar === null,
            422,
            'Add a profile picture before continuing.',
        );

        abort_if(
            $step === OnboardingStep::CreateWorkspace && $user->current_workspace_id === null,
            422,
            'Create a workspace before continuing.',
        );

        abort_if(
            $step === OnboardingStep::ChoosePlan && ! $this->isStepCompleted($user, $step, $user->onboarding_current_step ?? OnboardingStep::ProfilePicture),
            422,
            'Choose a plan before continuing.',
        );

        $this->advancePast($user, $step);

        return $this->state($user->fresh());
    }

    /**
     * Postpones a step: the user moves on and is not sent back to it at the
     * next sign-in, but it stays open (skipped, not completed) so it can be
     * finished later.
     */
    public function skip(User $user, OnboardingStep $step): array
    {
        abort_unless($step->isSkippable(), 422, 'This step cannot be skipped.');

        $skipped = $this->skippedSteps($user);

        if (! in_array($step, $skipped, strict: true)) {
            $user->update(['onboarding_skipped_steps' => $this->toValues([...$skipped, $step])]);
        }

        $this->advancePast($user, $step, keepSkipped: true);

        return $this->state($user->fresh());
    }

    public function complete(User $user): array
    {
        $this->requireAccountSetup($user);

        $user->update(['onboarding_completed_at' => now()]);

        return $this->state($user->fresh());
    }

    public function dismiss(User $user): void
    {
        $this->requireAccountSetup($user);

        $user->update(['onboarding_dismissed_at' => now()]);
    }

    /**
     * Signup creates no workspace and no plan, so onboarding is where every
     * owner gets both. It cannot be closed, by finishing or by dismissing it,
     * until they have.
     */
    private function requireAccountSetup(User $user): void
    {
        abort_if($user->current_workspace_id === null, 422, 'Create a workspace to finish onboarding.');

        $current = $user->onboarding_current_step ?? OnboardingStep::ProfilePicture;

        abort_unless($this->isStepCompleted($user, OnboardingStep::ChoosePlan, $current), 422, 'Choose a plan to finish onboarding.');
    }

    /**
     * A plan is chosen by picking Free (which moves past the step), or by the
     * workspace holding a subscription or plan grant. A member of someone
     * else's workspace has nothing to choose: the owner pays for it.
     */
    private function hasPlan(User $user): bool
    {
        $workspace = $user->currentWorkspace()->first();

        if ($workspace === null) {
            return false;
        }

        return $workspace->owner_id !== $user->id
            || $workspace->activeSubscription() !== null
            || $workspace->activePlanGrant() !== null;
    }

    /**
     * Whether the member's workspace already runs AI on a key of its own
     * that they can use: a team key, or a personal key of theirs.
     */
    private function hasAiKey(User $user): bool
    {
        if ($user->current_workspace_id === null) {
            return false;
        }

        return AiProviderCredential::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->where('validation_status', '!=', AiProviderCredentialStatus::Invalid->value)
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->orWhere('created_by', $user->id))
            ->exists();
    }

    private function isStepCompleted(User $user, OnboardingStep $step, OnboardingStep $currentStep): bool
    {
        if ($user->hasCompletedOnboarding()) {
            return true;
        }

        if ($this->isStepSkipped($user, $step)) {
            return false;
        }

        return match ($step) {
            OnboardingStep::ProfilePicture => $user->avatar !== null,
            OnboardingStep::CreateWorkspace => $user->current_workspace_id !== null,
            OnboardingStep::RoleSelection => $user->job_role !== null,
            OnboardingStep::DiscoverySurvey => $user->discovery_source !== null,
            OnboardingStep::ChoosePlan => $this->ordinal($step) < $this->ordinal($currentStep) || $this->hasPlan($user),
            OnboardingStep::ConnectAi => $this->ordinal($step) < $this->ordinal($currentStep) || $this->hasAiKey($user),
            default => $this->ordinal($step) < $this->ordinal($currentStep),
        };
    }

    private function isStepSkipped(User $user, OnboardingStep $step): bool
    {
        return in_array($step, $this->skippedSteps($user), strict: true);
    }

    /**
     * @return array<int, OnboardingStep>
     */
    private function skippedSteps(User $user): array
    {
        return collect($user->onboarding_skipped_steps ?? [])
            ->map(fn (string $value): ?OnboardingStep => OnboardingStep::tryFrom($value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, OnboardingStep>  $steps
     * @return array<int, string>
     */
    private function toValues(array $steps): array
    {
        return array_values(array_map(fn (OnboardingStep $step): string => $step->value, $steps));
    }

    private function advancePast(User $user, OnboardingStep $step, bool $keepSkipped = false): void
    {
        if (! $keepSkipped && $this->isStepSkipped($user, $step)) {
            $remaining = array_filter($this->skippedSteps($user), fn (OnboardingStep $skipped): bool => $skipped !== $step);
            $user->update(['onboarding_skipped_steps' => $this->toValues($remaining)]);
        }

        $current = $user->onboarding_current_step ?? OnboardingStep::ProfilePicture;

        if ($this->ordinal($step) < $this->ordinal($current)) {
            return;
        }

        $user->update(['onboarding_current_step' => $step->next() ?? $step]);
    }

    private function ordinal(OnboardingStep $step): int
    {
        return array_search($step, OnboardingStep::cases(), strict: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(User $user): array
    {
        return [
            'workspace_slug_suggestion' => $this->workspaces->suggestSlug($user->name),
            'plans' => PlanResource::collection(Plan::query()->where('is_active', true)->orderBy('sort_order')->get()),
            'job_roles' => collect(JobRole::cases())->map(fn (JobRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ])->all(),
            'discovery_sources' => collect(DiscoverySource::cases())->map(fn (DiscoverySource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ])->all(),
            'credential_types' => [],
        ];
    }
}
