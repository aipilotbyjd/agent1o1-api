<?php

namespace App\Services\Templates;

use App\Enums\Templates\TemplateType;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\PageTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use App\Models\Workspaces\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every template a workspace can see (workflow, agent, collection and page
 * templates) as one list, so a gallery can scroll through them together
 * instead of loading four separate lists. Each row carries only what a card
 * needs; the full template comes from its own endpoint.
 */
class TemplateCatalog
{
    /**
     * @param  array<int, TemplateType>  $types  Empty means every type.
     */
    public function paginate(Workspace $workspace, array $types = [], ?string $search = null, int $perPage = 12): LengthAwarePaginator
    {
        $types = $types === [] ? TemplateType::cases() : $types;

        $union = null;

        foreach ($types as $type) {
            $query = $this->rowsFor($type, $workspace)->toBase();
            $union = $union === null ? $query : $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'templates')
            ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes((string) $search, '\\%_').'%';

                $query->whereLike('name', $pattern)
                    ->orWhereLike('description', $pattern)
                    ->orWhereLike('category', $pattern);
            }))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * One type's visible templates, selected into the shared row shape.
     *
     * @return EloquentBuilder<covariant WorkflowTemplate|AgentTemplate|TemplateCollection|PageTemplate>
     */
    private function rowsFor(TemplateType $type, Workspace $workspace): EloquentBuilder
    {
        $common = ['id', 'workspace_id', 'name', 'description', 'category', 'icon', 'color', 'created_at'];

        return match ($type) {
            TemplateType::Workflow => WorkflowTemplate::query()->visibleTo($workspace)
                ->select($common)
                ->selectRaw("'workflow' as type, is_featured, sort_order, usage_count, 0 as items_count"),
            TemplateType::Agent => AgentTemplate::query()->visibleTo($workspace)
                ->select($common)
                ->selectRaw("'agent' as type, is_featured, sort_order, usage_count, 0 as items_count"),
            TemplateType::Page => PageTemplate::query()->visibleTo($workspace)
                ->select($common)
                ->selectRaw("'page' as type, is_featured, sort_order, usage_count, 0 as items_count"),
            TemplateType::Collection => TemplateCollection::query()->visibleTo($workspace)
                ->select($common)
                ->selectRaw("'collection' as type, false as is_featured, 0 as sort_order, 0 as usage_count")
                ->selectSub(
                    DB::table('template_collection_items')->selectRaw('count(*)')->whereColumn('template_collection_items.collection_id', 'template_collections.id'),
                    'items_count',
                ),
        };
    }
}
