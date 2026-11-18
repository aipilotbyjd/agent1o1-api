<?php

namespace App\Services\Templates;

use App\Contracts\NodeContract;
use App\Enums\Onboarding\JobRole;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeRegistry;

/**
 * The few workflow templates to offer someone starting out: the onboarding
 * wizard's "Pick a starting point" and the dashboard of an empty workspace.
 *
 * Ordered by fit, then by what is quickest to see working: the template a
 * signup link pointed at, if any, then templates for the person's job role,
 * and within those, ones that carry example answers so a sample run can
 * show them working straight away (`SampleRuns`), then the catalogue's
 * featured order.
 */
final class TemplateRecommendations
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * @return array<int, array{id: string, name: string, description: string|null, category: string|null, icon: string|null, color: string|null, has_sample: bool, apps: array<int, string>}>
     */
    public function forRole(Workspace $workspace, ?JobRole $role, int $limit = 3, ?string $linkedSlug = null): array
    {
        $categories = $role?->templateCategories() ?? [];

        return WorkflowTemplate::query()
            ->visibleTo($workspace)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get(['id', 'slug', 'name', 'description', 'category', 'icon', 'color', 'graph', 'sample_input', 'is_featured', 'sort_order', 'created_at'])
            ->sortBy([
                fn (WorkflowTemplate $a, WorkflowTemplate $b): int => ($b->slug === $linkedSlug) <=> ($a->slug === $linkedSlug),
                fn (WorkflowTemplate $a, WorkflowTemplate $b): int => $this->fitsRole($b, $categories) <=> $this->fitsRole($a, $categories),
                fn (WorkflowTemplate $a, WorkflowTemplate $b): int => filled($b->sample_input) <=> filled($a->sample_input),
            ])
            ->take($limit)
            ->map(fn (WorkflowTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'icon' => $template->icon,
                'color' => $template->color,
                'has_sample' => filled($template->sample_input),
                'apps' => $this->appsUsedBy($template),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function fitsRole(WorkflowTemplate $template, array $categories): bool
    {
        return in_array($template->category, $categories, true);
    }

    /**
     * The apps a template's steps act on, by connector key, in the order
     * they first appear: what a card lists so people know what they will be
     * asked to connect.
     *
     * @return array<int, string>
     */
    private function appsUsedBy(WorkflowTemplate $template): array
    {
        return collect($template->graph['nodes'] ?? [])
            ->pluck('type')
            ->filter(fn (?string $type): bool => $type !== null && $this->registry->has($type))
            ->map(fn (string $type): NodeContract => $this->registry->resolve($type))
            ->filter(fn (NodeContract $node): bool => $this->registry->isAppNode($node))
            ->map(fn (NodeContract $node): string => $node->category())
            ->unique()
            ->values()
            ->all();
    }
}
