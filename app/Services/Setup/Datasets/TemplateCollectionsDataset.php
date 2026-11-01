<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Templates\WorkflowTemplate;
use App\Services\Setup\AbstractSetupDataset;

/**
 * Global starter kits: groups of workflow and agent templates installed
 * together. A kit names its templates by slug, so this runs after the two
 * template datasets and refuses a kit that names one that does not exist.
 */
class TemplateCollectionsDataset extends AbstractSetupDataset
{
    private const int MAX_ITEMS = 12;

    public function key(): string
    {
        return 'template-collections';
    }

    public function label(): string
    {
        return 'Template collections';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function dependencies(): array
    {
        return ['agent-templates', 'workflow-templates'];
    }

    public function rows(): int
    {
        return TemplateCollection::query()->whereNull('workspace_id')->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/template-collections.php')];
    }

    public function apply(): void
    {
        $workflows = WorkflowTemplate::query()->whereNull('workspace_id')->get()->keyBy('slug');
        $agents = AgentTemplate::query()->whereNull('workspace_id')->get()->keyBy('slug');

        foreach ($this->collections() as $slug => $definition) {
            $items = $definition['items'];
            unset($definition['items']);

            $collection = TemplateCollection::withTrashed()->updateOrCreate(
                ['slug' => $slug, 'workspace_id' => null],
                [...$definition, 'visibility' => 'public'],
            );

            if ($collection->trashed()) {
                $collection->restore();
            }

            // Rebuilt every time, so a removed or reordered item follows the file.
            TemplateCollectionItem::query()->where('collection_id', $collection->id)->delete();

            foreach (array_values($items) as $position => [$kind, $templateSlug]) {
                $template = $kind === 'workflow' ? $workflows[$templateSlug] : $agents[$templateSlug];

                TemplateCollectionItem::query()->create([
                    'collection_id' => $collection->id,
                    'templatable_type' => $template->getMorphClass(),
                    'templatable_id' => $template->id,
                    'position' => $position,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function collections(): array
    {
        return require database_path('setup/template-collections.php');
    }

    public function problems(): array
    {
        $workflowSlugs = array_keys(require database_path('setup/workflow-templates.php'));
        $agentSlugs = array_keys(require database_path('setup/agent-templates.php'));
        $problems = [];

        foreach ($this->collections() as $slug => $collection) {
            foreach ($this->missing($collection, ['name', 'description', 'category']) as $field) {
                $problems[] = "Collection [{$slug}] has no {$field}.";
            }

            $items = $collection['items'] ?? [];

            if (count($items) < 2 || count($items) > self::MAX_ITEMS) {
                $problems[] = "Collection [{$slug}] needs between 2 and ".self::MAX_ITEMS.' templates, not '.count($items).'.';
            }

            $seen = [];

            foreach ($items as [$kind, $templateSlug]) {
                $known = match ($kind) {
                    'workflow' => $workflowSlugs,
                    'agent' => $agentSlugs,
                    default => null,
                };

                if ($known === null) {
                    $problems[] = "Collection [{$slug}] has an item of kind [{$kind}]; use workflow or agent.";
                } elseif (! in_array($templateSlug, $known, true)) {
                    $problems[] = "Collection [{$slug}] names the {$kind} template [{$templateSlug}], which does not exist.";
                }

                if (isset($seen["{$kind}:{$templateSlug}"])) {
                    $problems[] = "Collection [{$slug}] lists the {$kind} template [{$templateSlug}] twice.";
                }

                $seen["{$kind}:{$templateSlug}"] = true;
            }
        }

        return $problems;
    }
}
