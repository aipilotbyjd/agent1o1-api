<?php

namespace App\Services\SuperAdmin\Catalog;

use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\Nodes\NodeCategory;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Switching catalog items on and off: connectors, custom nodes, and the
 * visibility of templates. Nothing is deleted, so anything switched off can
 * be switched back on, and every change is audited with the stated reason.
 *
 * (The model catalog is deliberately not here: it is operator-fixed.)
 */
class SuperAdminCatalogService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function setConnectorActive(SuperAdmin $actor, Connector $connector, bool $active, string $reason): Connector
    {
        return $this->setActive($actor, $connector, 'catalog.connector', $active, $reason);
    }

    public function setCustomNodeActive(SuperAdmin $actor, CustomNode $node, bool $active, string $reason): CustomNode
    {
        return $this->setActive($actor, $node, 'catalog.node', $active, $reason);
    }

    /**
     * Re-files an integration in the editor's Apps panel. Only app categories
     * can be filed: the core ones are not part of that taxonomy.
     *
     * @param  list<string>  $functions  slugs, one to three, the first is the primary
     */
    public function setAppCategoryTaxonomy(SuperAdmin $actor, NodeCategory $category, array $functions, ?string $vendor, string $reason): NodeCategory
    {
        abort_unless($category->kind === 'app', 404, 'Only app categories are filed here.');

        $before = $this->taxonomy($category);

        $category->forceFill(['functions' => array_values($functions), 'vendor' => $vendor]);

        if (! $category->isDirty(['functions', 'vendor'])) {
            return $category;
        }

        $category->markAdminEdited(['functions', 'vendor']);

        DB::transaction(function () use ($actor, $category, $before, $reason): void {
            $category->save();

            $this->audit->record($actor, 'catalog.app_category.updated', $category, $before, $this->taxonomy($category), $reason);
        });

        return $category;
    }

    /**
     * @return array{functions: list<string>, vendor: ?string}
     */
    private function taxonomy(NodeCategory $category): array
    {
        return ['functions' => array_values($category->functions ?? []), 'vendor' => $category->vendor];
    }

    /**
     * Publishing is limited to platform templates (no workspace): a workspace's
     * private template is not the platform's to make public. Unpublishing is
     * allowed for any.
     */
    public function setTemplateVisibility(SuperAdmin $actor, Model $template, string $visibility, string $reason): Model
    {
        abort_if(
            $visibility === 'public' && $template->workspace_id !== null,
            409,
            'Only platform templates can be published from here.',
        );

        if ($template->visibility === $visibility) {
            return $template;
        }

        DB::transaction(function () use ($actor, $template, $visibility, $reason): void {
            $previous = $template->visibility;

            $template->forceFill(['visibility' => $visibility])->save();

            $this->audit->record(
                $actor,
                $visibility === 'public' ? 'catalog.template.published' : 'catalog.template.unpublished',
                $template,
                ['visibility' => $previous],
                ['visibility' => $visibility],
                $reason,
            );
        });

        return $template;
    }

    /**
     * Sets whether a template is featured and where it sits among the others.
     *
     * @param  array{is_featured?: bool, sort_order?: int}  $changes
     */
    public function setTemplateCuration(SuperAdmin $actor, Model $template, array $changes, string $reason): Model
    {
        $template->fill($changes);

        if (! $template->isDirty()) {
            return $template;
        }

        $before = array_intersect_key($template->getOriginal(), $template->getDirty());

        DB::transaction(function () use ($actor, $template, $before, $reason): void {
            $template->save();

            $this->audit->record($actor, 'catalog.template.curated', $template, $before, array_intersect_key($template->getAttributes(), $before), $reason);
        });

        return $template;
    }

    /**
     * Runs one action over many templates. A template that cannot take it
     * (publishing a workspace's private one) is left alone and counted as skipped.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $ids
     * @return array{updated: int, skipped: int}
     */
    public function bulkTemplates(SuperAdmin $actor, string $model, array $ids, string $action, string $reason): array
    {
        $updated = $skipped = 0;

        foreach ($model::query()->whereIn('id', $ids)->get() as $template) {
            if ($action === 'publish' && $template->workspace_id !== null) {
                $skipped++;

                continue;
            }

            $before = [$template->visibility, $template->is_featured];

            match ($action) {
                'publish' => $this->setTemplateVisibility($actor, $template, 'public', $reason),
                'unpublish' => $this->setTemplateVisibility($actor, $template, 'private', $reason),
                'feature' => $this->setTemplateCuration($actor, $template, ['is_featured' => true], $reason),
                'unfeature' => $this->setTemplateCuration($actor, $template, ['is_featured' => false], $reason),
            };

            [$template->visibility, $template->is_featured] === $before ? $skipped++ : $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function setActive(SuperAdmin $actor, Model $item, string $actionPrefix, bool $active, string $reason): Model
    {
        if ((bool) $item->is_active === $active) {
            return $item;
        }

        DB::transaction(function () use ($actor, $item, $actionPrefix, $active, $reason): void {
            $item->forceFill(['is_active' => $active])->save();

            $this->audit->record(
                $actor,
                $actionPrefix.($active ? '.enabled' : '.disabled'),
                $item,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $reason,
            );
        });

        return $item;
    }
}
