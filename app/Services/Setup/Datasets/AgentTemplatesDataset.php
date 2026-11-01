<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Agents\Agent;
use App\Models\Templates\AgentTemplate;
use App\Services\Setup\AbstractSetupDataset;

/**
 * Global starter templates shown in every workspace's agent builder.
 */
class AgentTemplatesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'agent-templates';
    }

    public function label(): string
    {
        return 'Agent templates';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function rows(): int
    {
        return AgentTemplate::query()->whereNull('workspace_id')->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/agent-templates.php')];
    }

    public function apply(): void
    {
        foreach ($this->templates() as $slug => $template) {
            AgentTemplate::query()->updateOrCreate(
                ['slug' => $slug, 'workspace_id' => null],
                [...$template, 'visibility' => 'public'],
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return require database_path('setup/agent-templates.php');
    }

    public function problems(): array
    {
        $problems = [];

        foreach (require database_path('setup/agent-templates.php') as $slug => $template) {
            foreach ($this->missing($template, ['name', 'description', 'category']) as $field) {
                $problems[] = "Agent template [{$slug}] has no {$field}.";
            }

            if (! in_array($template['icon'] ?? null, Agent::ICONS, true)) {
                $problems[] = "Agent template [{$slug}] has an icon an agent cannot use.";
            }

            if (! in_array($template['color'] ?? null, Agent::COLORS, true)) {
                $problems[] = "Agent template [{$slug}] has a color an agent cannot use.";
            }

            if (blank($template['config']['instructions'] ?? null)) {
                $problems[] = "Agent template [{$slug}] has no instructions.";
            }
        }

        return $problems;
    }
}
