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
        return [database_path('setup/workflow-templates.php'), database_path('setup/workflow-template-samples.php')];
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
     * Each template with its sample run-form answers, if it has any.
     *
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        $templates = require database_path('setup/workflow-templates.php');
        $samples = require database_path('setup/workflow-template-samples.php');

        foreach ($templates as $slug => $template) {
            $templates[$slug]['sample_input'] = $samples[$slug] ?? null;
        }

        return $templates;
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

            foreach ($this->unknownSampleFields($template) as $field) {
                $problems[] = "Workflow template [{$slug}] has a sample answer for [{$field}], which is not one of its Input steps.";
            }
        }

        $unknownSlugs = array_diff_key(require database_path('setup/workflow-template-samples.php'), $this->templates());

        foreach (array_keys($unknownSlugs) as $slug) {
            $problems[] = "Workflow template samples name [{$slug}], which is not a template.";
        }

        return $problems;
    }

    /**
     * Sample answers that match no Input step: a renamed field would
     * otherwise leave the sample run's form silently empty.
     *
     * @param  array<string, mixed>  $template
     * @return array<int, string>
     */
    private function unknownSampleFields(array $template): array
    {
        $inputKeys = collect($template['graph']['nodes'] ?? [])
            ->where('type', 'workflow_input')
            ->pluck('key')
            ->all();

        return array_values(array_diff(array_keys($template['sample_input'] ?? []), $inputKeys));
    }
}
