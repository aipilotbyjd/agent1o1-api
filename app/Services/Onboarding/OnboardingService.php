<?php

namespace App\Services\Onboarding;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Onboarding\DiscoverySource;
use App\Enums\Onboarding\JobRole;
use App\Enums\Onboarding\OnboardingStep;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Ai\AiProviderSettings;
use App\Services\Analytics\ProductAnalytics;
use App\Services\Templates\TemplateRecommendations;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Str;

class OnboardingService
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly ProductAnalytics $analytics,
        private readonly TemplateRecommendations $templates,
    ) {}

    /**
     * @return array{
     *     dismissed: bool,
     *     discovery_answered: bool,
     *     completed: bool,
     *     percent: int,
     *     current_step: string,
     *     steps: array<int, array{key: string, label: string, description: string, skippable: bool, completed: bool, skipped: bool}>,
     *     meta: array<string, mixed>
     * }
     */
    public function state(User $user): array
    {
        $currentStep = $user->onboarding_current_step ?? OnboardingStep::first();

        $steps = collect(OnboardingStep::cases())->map(fn (OnboardingStep $step): array => [
            'key' => $step->value,
            'label' => $step->label(),
            'description' => $step->description(),
            'skippable' => $step->isSkippable(),
            'completed' => $this->isStepCompleted($user, $step, $currentStep),
            'skipped' => $this->isStepSkipped($user, $step),
        ]);

        $percent = (int) round($steps->where('completed', true)->count() / $steps->count() * 100);

        return [
            'dismissed' => $user->onboarding_dismissed_at !== null,
            'discovery_answered' => $user->discovery_source !== null,
            'completed' => $user->hasCompletedOnboarding(),
            'percent' => $percent,
            'current_step' => $currentStep->value,
            'steps' => $steps->all(),
            'meta' => $this->meta($user),
        ];
    }

    /**
     * Opens the wizard. Signup creates no workspace, so a user who has none
     * (and was not invited into one) gets their own here, on the Free plan,
     * instead of being asked to name it before they have seen anything.
     */
    public function start(User $user): array
    {
        if ($user->current_workspace_id === null && ! $user->workspaces()->exists()) {
            $this->workspaces->create($user, ['name' => $this->workspaceNameFor($user)]);
        }

        return $this->state($user->fresh());
    }

    public function selectRole(User $user, JobRole $role): array
    {
        $user->update(['job_role' => $role]);

        $this->advancePast($user, OnboardingStep::RoleSelection);

        return $this->state($user->fresh());
    }

    /**
     * "How did you find us", asked in the app once the user has had a
     * successful run. It is not an onboarding step, so it moves no progress.
     */
    public function submitDiscovery(User $user, DiscoverySource $source): array
    {
        $user->update(['discovery_source' => $source]);

        return $this->state($user->fresh());
    }

    /**
     * Saves that the user finished a step and pressed Continue, so signing in
     * again resumes after it. The AI step needs a working key to be behind
     * the user: the first run cannot work without one.
     */
    public function continue(User $user, OnboardingStep $step): array
    {
        abort_if(
            $step === OnboardingStep::ConnectAi && ! $this->hasAiKey($user),
            422,
            'Connect an AI provider before continuing.',
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
        $this->requireWorkspace($user);

        $alreadyCompleted = $user->hasCompletedOnboarding();

        $user->update(['onboarding_completed_at' => now()]);

        if (! $alreadyCompleted) {
            $this->track($user, 'onboarding_completed');
        }

        return $this->state($user->fresh());
    }

    public function dismiss(User $user): void
    {
        $this->requireWorkspace($user);

        $alreadyDismissed = $user->onboarding_dismissed_at !== null;

        $user->update(['onboarding_dismissed_at' => now()]);

        if (! $alreadyDismissed) {
            $this->track($user, 'onboarding_dismissed', [
                'step' => ($user->onboarding_current_step ?? OnboardingStep::first())->value,
            ]);
        }
    }

    /**
     * The name a new user's workspace starts with: their company, read from
     * a work email's domain (`jane@northwind.io` is "Northwind"), else
     * "Jane's workspace". It can be renamed at any time.
     */
    public function workspaceNameFor(User $user): string
    {
        $domain = Str::of($user->email)->after('@')->lower()->value();
        $sharedProviders = config('referrals.free_email_domains', []);

        if ($domain !== '' && ! in_array($domain, $sharedProviders, true)) {
            $company = Str::of($domain)->before('.')->replace(['-', '_'], ' ')->headline()->value();

            if ($company !== '') {
                return $company;
            }
        }

        return Str::of($user->name)->before(' ')->trim()->value()."'s workspace";
    }

    private function requireWorkspace(User $user): void
    {
        abort_if($user->current_workspace_id === null, 422, 'Open onboarding to set up your workspace first.');
    }

    /**
     * Whether the member's workspace runs AI on a key of its own that their
     * calls would use: a working team key, or a working personal key of
     * theirs while the workspace allows personal keys.
     */
    private function hasAiKey(User $user): bool
    {
        if ($user->current_workspace_id === null) {
            return false;
        }

        $personalAllowed = WorkspaceAiKeyPolicy::forWorkspace($user->current_workspace_id)->allow_personal_keys;

        return AiProviderCredential::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->where('validation_status', AiProviderCredentialStatus::Valid->value)
            ->whereIn('execution_provider', app(AiProviderSettings::class)->enabledProviders())
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->when($personalAllowed, fn ($query) => $query->orWhere(fn ($query) => $query
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $user->id))))
            ->exists();
    }

    /**
     * A workflow run the user started that finished successfully, in their
     * current workspace. Sample runs count: the point of the step is seeing
     * an automation work. A single-step test from the editor does not.
     */
    private function hasSucceededRun(User $user): bool
    {
        if ($user->current_workspace_id === null) {
            return false;
        }

        return Run::query()
            ->succeededWorkflowRuns()
            ->where('workspace_id', $user->current_workspace_id)
            ->where('triggered_by', $user->id)
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
            OnboardingStep::RoleSelection => $user->job_role !== null,
            OnboardingStep::ConnectAi => $this->hasAiKey($user),
            OnboardingStep::FirstRun => $this->hasSucceededRun($user),
            default => $this->ordinal($step) < $this->ordinal($currentStep),
        };
    }

    private function isStepSkipped(User $user, OnboardingStep $step): bool
    {
        return in_array($step, $this->skippedSteps($user), strict: true);
    }

    /**
     * Saved values that no longer name a step (from an earlier version of
     * the wizard) are ignored.
     *
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

        $current = $user->onboarding_current_step ?? OnboardingStep::first();
        $target = $this->heldAtAiStep($user, $step, $current, $step->next() ?? $step);

        // Only the first time through a step, and only once progress really
        // moves: picking a role saves it and the Continue that follows saves
        // it again. The last step has nowhere to move, so it counts when it
        // is the current one.
        $movesPast = $this->ordinal($target) > $this->ordinal($current)
            || ($step->next() === null && $step === $current);

        if ($movesPast && $this->ordinal($step) >= $this->ordinal($current)) {
            $this->track($user, $keepSkipped ? 'onboarding_step_skipped' : 'onboarding_step_completed', [
                'step' => $step->value,
                'step_number' => $this->ordinal($step) + 1,
            ]);
        }

        if ($this->ordinal($target) > $this->ordinal($current)) {
            $user->update(['onboarding_current_step' => $target]);
        }
    }

    /**
     * Saved progress means "everything before this is behind me". Finishing
     * a later step first must not carry progress over the AI step while the
     * workspace still has no key: it stops there instead.
     */
    private function heldAtAiStep(User $user, OnboardingStep $step, OnboardingStep $current, OnboardingStep $target): OnboardingStep
    {
        $aiStep = OnboardingStep::ConnectAi;

        $isAhead = $this->ordinal($aiStep) < $this->ordinal($target)
            && $aiStep !== $step
            && $this->ordinal($aiStep) >= $this->ordinal($current);

        return $isAhead && ! $this->hasAiKey($user) ? $aiStep : $target;
    }

    /**
     * Onboarding funnel events (see docs/POSTHOG.md), grouped under the
     * workspace once the user has one.
     *
     * @param  array<string, scalar|null>  $properties
     */
    private function track(User $user, string $event, array $properties = []): void
    {
        $this->analytics->capture(
            (string) $user->getKey(),
            $event,
            $properties,
            $user->current_workspace_id !== null ? (string) $user->current_workspace_id : null,
        );
    }

    private function ordinal(OnboardingStep $step): int
    {
        return array_search($step, OnboardingStep::cases(), strict: true);
    }

    /**
     * `recommended_templates` is what the Pick step offers first: the
     * template their signup link pointed at, then ones for their role (see
     * `TemplateRecommendations`).
     *
     * @return array{
     *     job_roles: array<int, array{value: string, label: string, description: string}>,
     *     discovery_sources: array<int, array{value: string, label: string}>,
     *     recommended_templates: array<int, array<string, mixed>>
     * }
     */
    private function meta(User $user): array
    {
        $workspace = $user->currentWorkspace()->first();

        return [
            'job_roles' => collect(JobRole::cases())->map(fn (JobRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ])->all(),
            'discovery_sources' => collect(DiscoverySource::cases())->map(fn (DiscoverySource $source): array => [
                'value' => $source->value,
                'label' => $source->label(),
            ])->all(),
            'recommended_templates' => $workspace === null
                ? []
                : $this->templates->forRole($workspace, $user->job_role, linkedSlug: $user->signup_attribution['template'] ?? null),
        ];
    }
}
