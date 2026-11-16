<?php

namespace App\Services\Dashboard;

use App\Enums\Dashboard\GettingStartedMilestone;
use App\Enums\RunStatus;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeTester;

/**
 * The dashboard's getting-started checklist. Every milestone is read off what
 * the workspace has actually done, so it cannot be ticked by clicking through
 * a screen and never needs to be stored. Milestones belong to the workspace
 * (a teammate's first run counts for everyone); hiding the checklist is each
 * member's own choice.
 */
final class GettingStarted
{
    /**
     * How many templates an empty workspace is offered. Three fits one row of
     * the dashboard and is a choice, not a catalogue.
     */
    private const int RECOMMENDED_TEMPLATES = 3;

    /**
     * @return array{
     *     dismissed: bool,
     *     completed: bool,
     *     milestones: array<int, array{key: string, label: string, description: string, completed: bool}>,
     *     recommended_templates: array<int, array<string, mixed>>
     * }
     */
    public function for(Workspace $workspace, User $user): array
    {
        $milestones = array_map(fn (GettingStartedMilestone $milestone): array => [
            'key' => $milestone->value,
            'label' => $milestone->label(),
            'description' => $milestone->description(),
            'completed' => $this->isReached($workspace, $milestone),
        ], GettingStartedMilestone::cases());

        return [
            'dismissed' => $workspace->members()
                ->where('user_id', $user->id)
                ->whereNotNull('getting_started_dismissed_at')
                ->exists(),
            'completed' => ! in_array(false, array_column($milestones, 'completed'), true),
            'milestones' => $milestones,
            'recommended_templates' => $this->recommendedTemplates($workspace, $user),
        ];
    }

    public function dismiss(Workspace $workspace, User $user): void
    {
        $workspace->members()
            ->where('user_id', $user->id)
            ->whereNull('getting_started_dismissed_at')
            ->update(['getting_started_dismissed_at' => now()]);
    }

    /**
     * A run only counts once it has finished successfully, and single-node
     * tests from the builder do not: the milestone is "it worked", which a
     * failed run or a tap on Test is not.
     */
    private function isReached(Workspace $workspace, GettingStartedMilestone $milestone): bool
    {
        return match ($milestone) {
            GettingStartedMilestone::RunWorkflow => $workspace->runs()
                ->whereNotNull('workflow_id')
                ->where('status', RunStatus::Completed)
                ->where('trigger_type', '!=', NodeTester::TRIGGER_TYPE)
                ->exists(),
            GettingStartedMilestone::ConnectApp => $workspace->connectorCredentials()->exists(),
            GettingStartedMilestone::ActivateTrigger => $workspace->triggers()->where('is_active', true)->exists(),
            GettingStartedMilestone::CreateAgent => $workspace->agents()->exists(),
            GettingStartedMilestone::InviteTeammate => $workspace->invitations()->exists()
                || $workspace->members()->count() > 1,
        };
    }

    /**
     * Templates to start from, for a workspace that has no workflow yet: the
     * ones for the viewer's job role first, then the catalogue's featured
     * order. Empty once the workspace has a workflow of its own, because by
     * then the dashboard has real activity to show instead.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recommendedTemplates(Workspace $workspace, User $user): array
    {
        if ($workspace->workflows()->visible()->exists()) {
            return [];
        }

        $categories = $user->job_role?->templateCategories() ?? [];

        return WorkflowTemplate::query()
            ->visibleTo($workspace)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get(['id', 'name', 'description', 'category', 'icon', 'color', 'is_featured', 'sort_order', 'created_at'])
            ->sortBy(fn (WorkflowTemplate $template): int => in_array($template->category, $categories, true) ? 0 : 1)
            ->take(self::RECOMMENDED_TEMPLATES)
            ->map(fn (WorkflowTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'icon' => $template->icon,
                'color' => $template->color,
            ])
            ->values()
            ->all();
    }
}
