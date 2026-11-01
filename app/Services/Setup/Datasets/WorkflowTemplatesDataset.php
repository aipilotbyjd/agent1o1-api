<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Templates\WorkflowTemplate;
use App\Services\Setup\AbstractSetupDataset;
use App\Services\Workflows\GraphValidator;

/**
 * Global starter workflows shown in every workspace's template library.
 */
class WorkflowTemplatesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'workflow-templates';
    }

    public function label(): string
    {
        return 'Workflow templates';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function rows(): int
    {
        return WorkflowTemplate::query()->whereNull('workspace_id')->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/workflow-templates.php')];
    }

    public function apply(): void
    {
        $sort = 0;

        foreach ($this->templates() as $slug => $template) {
            WorkflowTemplate::query()->updateOrCreate(
                ['slug' => $slug, 'workspace_id' => null],
                [...$template, 'visibility' => 'public', 'sort_order' => $sort],
            );

            $sort++;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return require database_path('setup/workflow-templates.php');
    }

    public function problems(): array
    {
        $problems = [];
        $validator = app(GraphValidator::class);

        foreach ($this->templates() as $slug => $template) {
            foreach ($this->missing($template, ['name', 'description', 'category']) as $field) {
                $problems[] = "Workflow template [{$slug}] has no {$field}.";
            }

            foreach ($validator->validate($template['graph']['nodes'] ?? [], $template['graph']['edges'] ?? []) as $issue) {
                $problems[] = "Workflow template [{$slug}]: {$issue}";
            }
        }

        return $problems;
    }
}
