<?php

namespace App\Models\Concerns;

/**
 * Lets a model be the parent of a scoped route binding (`->scopeBindings()`).
 *
 * Laravel finds `{child}` inside `{parent}` by calling the parent's relation
 * named after the route parameter (`{evalSuite}` calls `evalSuites()`), and a
 * parameter with no matching relation is a 500, not a 404. Our URLs predate
 * that rule and don't always match their relation (`{page}` is
 * `workflowPages()`, `{member}` is a `users()` row), so the model says which
 * relation each of its route parameters uses.
 *
 * Every entry is covered by `ScopedRouteBindingsTest`, which walks the whole
 * route table and fails when a parameter has no relation to resolve through.
 */
trait ResolvesScopedRouteBindings
{
    /**
     * @return array<string, string> route parameter name => relation method
     */
    abstract protected function scopedRouteBindingRelations(): array;

    /**
     * @param  string  $childType
     */
    protected function childRouteBindingRelationshipName($childType): string
    {
        return $this->scopedRouteBindingRelations()[$childType] ?? parent::childRouteBindingRelationshipName($childType);
    }
}
