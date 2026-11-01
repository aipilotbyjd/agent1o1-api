<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Templates\PageTemplate;
use App\Services\Setup\AbstractSetupDataset;
use App\Services\Workflows\Pages\PageSchema;

/**
 * Global layouts offered when creating a page: a few plain starters, and
 * full pages for common uses with a cover, introduction, the form, the
 * result and the sections around them. Field and output blocks are left
 * unbound: `PageSchema::fromTemplate()` fills them with the chosen
 * workflow's inputs and outputs, adding or removing blocks to match.
 */
class PageTemplatesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'page-templates';
    }

    public function label(): string
    {
        return 'Page templates';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function rows(): int
    {
        return PageTemplate::query()->whereNull('workspace_id')->count();
    }

    public function apply(): void
    {
        $schema = app(PageSchema::class);

        foreach (require database_path('setup/page-templates.php') as $slug => $template) {
            PageTemplate::query()->updateOrCreate(
                ['slug' => $slug, 'workspace_id' => null],
                [...$template, 'content' => $schema->normalize($template['content']), 'visibility' => 'public'],
            );
        }
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/page-templates.php'), app_path('Services/Setup/PageBlocks.php')];
    }

    public function problems(): array
    {
        $problems = [];

        foreach (require database_path('setup/page-templates.php') as $slug => $template) {
            foreach ($this->missing($template, ['name', 'content']) as $field) {
                $problems[] = "Page template [{$slug}] has no {$field}.";
            }
        }

        return $problems;
    }
}
