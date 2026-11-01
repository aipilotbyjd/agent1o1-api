<?php

namespace App\Services\SuperAdmin\Catalog;

use App\Models\Nodes\CatalogFunction;
use App\Models\Nodes\CatalogVendor;
use App\Models\Nodes\NodeCategory;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\Workflows\CatalogTaxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The functions and vendors an app can be filed under, kept as data so a new
 * one is an admin's decision, not a deploy. Each can be added, renamed and
 * reordered; one an app still uses cannot be removed.
 */
class SuperAdminTaxonomyService
{
    /** @var array<string, class-string<Model>> */
    public const KINDS = ['functions' => CatalogFunction::class, 'vendors' => CatalogVendor::class];

    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly CatalogTaxonomy $taxonomy,
    ) {}

    /**
     * @return array{functions: list<array<string, mixed>>, vendors: list<array<string, mixed>>}
     */
    public function all(): array
    {
        $apps = NodeCategory::query()->where('kind', 'app')->get(['slug', 'functions', 'vendor']);

        return [
            'functions' => CatalogFunction::query()->orderBy('sort_order')->orderBy('label')->get()
                ->map(fn (CatalogFunction $function): array => [...$this->item($function), 'is_system' => $function->is_system, 'apps_count' => $apps->filter(fn (NodeCategory $app): bool => in_array($function->slug, $app->functions ?? [], true))->count()])
                ->all(),
            'vendors' => CatalogVendor::query()->orderBy('sort_order')->orderBy('label')->get()
                ->map(fn (CatalogVendor $vendor): array => [...$this->item($vendor), 'apps_count' => $apps->where('vendor', $vendor->slug)->count()])
                ->all(),
        ];
    }

    /**
     * @param  array{slug: string, label: string, sort_order?: int}  $attributes
     */
    public function create(SuperAdmin $actor, string $kind, array $attributes, string $reason): Model
    {
        $model = DB::transaction(function () use ($actor, $kind, $attributes, $reason): Model {
            $model = (self::KINDS[$kind])::query()->create([
                'sort_order' => $attributes['sort_order'] ?? ((self::KINDS[$kind])::query()->where('slug', '!=', 'other')->max('sort_order') ?? 0) + 1,
                ...$attributes,
            ]);

            $this->audit->record($actor, "catalog.{$this->singular($kind)}.created", $model, null, $this->item($model), $reason);

            return $model;
        });

        $this->taxonomy->flush();

        return $model;
    }

    /**
     * @param  array{label?: string, sort_order?: int}  $changes  the slug never changes: apps refer to it
     */
    public function update(SuperAdmin $actor, string $kind, string $id, array $changes, string $reason): Model
    {
        $model = (self::KINDS[$kind])::query()->findOrFail($id);

        abort_if($this->isSystem($model), 409, 'This one is built in and cannot be changed.');

        $model->fill($changes);

        if ($model->isDirty()) {
            $before = array_intersect_key($model->getOriginal(), $model->getDirty());

            DB::transaction(function () use ($actor, $kind, $model, $before, $reason): void {
                $model->save();
                $this->audit->record($actor, "catalog.{$this->singular($kind)}.updated", $model, $before, array_intersect_key($model->getAttributes(), $before), $reason);
            });

            $this->taxonomy->flush();
        }

        return $model;
    }

    public function delete(SuperAdmin $actor, string $kind, string $id, string $reason): void
    {
        $model = (self::KINDS[$kind])::query()->findOrFail($id);

        abort_if($this->isSystem($model), 409, 'This one is built in and cannot be removed.');

        $using = NodeCategory::query()->where('kind', 'app')->get(['name', 'functions', 'vendor'])
            ->filter(fn (NodeCategory $app): bool => $kind === 'functions' ? in_array($model->slug, $app->functions ?? [], true) : $app->vendor === $model->slug);

        abort_if($using->isNotEmpty(), 409, 'Still used by '.$using->count().' '.($using->count() === 1 ? 'app' : 'apps').': '.$using->take(5)->pluck('name')->join(', ').($using->count() > 5 ? ' and more' : '').'. Move them first.');

        DB::transaction(function () use ($actor, $kind, $model, $reason): void {
            $this->audit->record($actor, "catalog.{$this->singular($kind)}.deleted", $model, $this->item($model), null, $reason);
            $model->delete();
        });

        $this->taxonomy->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function item(Model $model): array
    {
        return ['id' => $model->id, 'slug' => $model->slug, 'label' => $model->label, 'sort_order' => $model->sort_order];
    }

    private function isSystem(Model $model): bool
    {
        return $model instanceof CatalogFunction && $model->is_system;
    }

    private function singular(string $kind): string
    {
        return $kind === 'functions' ? 'function' : 'vendor';
    }
}
