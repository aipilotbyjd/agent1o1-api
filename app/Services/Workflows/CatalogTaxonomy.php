<?php

namespace App\Services\Workflows;

use App\Models\Nodes\CatalogFunction;
use App\Models\Nodes\CatalogVendor;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;

/**
 * The functions and vendors an app can be filed under, read once per request.
 * Whoever changes them calls `flush()` so the next read sees the change.
 */
#[Singleton]
class CatalogTaxonomy
{
    /** @var Collection<string, CatalogFunction>|null */
    private ?Collection $functions = null;

    /** @var Collection<string, CatalogVendor>|null */
    private ?Collection $vendors = null;

    /**
     * @return Collection<string, CatalogFunction> by slug, in display order
     */
    public function functions(): Collection
    {
        return $this->functions ??= CatalogFunction::query()->orderBy('sort_order')->orderBy('label')->get()->keyBy('slug');
    }

    /**
     * @return Collection<string, CatalogVendor> by slug, in display order
     */
    public function vendors(): Collection
    {
        return $this->vendors ??= CatalogVendor::query()->orderBy('sort_order')->orderBy('label')->get()->keyBy('slug');
    }

    public function flush(): void
    {
        $this->functions = null;
        $this->vendors = null;
    }
}
