<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Catalog;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use Illuminate\Validation\Rule;

/**
 * A new function or vendor. The kind is in the URL; its slug is permanent.
 */
class StoreTaxonomyItemRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = $this->route('kind') === 'vendors' ? 'catalog_vendors' : 'catalog_functions';

        return [
            ...parent::rules(),
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique($table, 'slug')],
            'label' => ['required', 'string', 'min:1', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array{slug: string, label: string, sort_order?: int}
     */
    public function values(): array
    {
        return collect($this->validated())->except('reason')->all();
    }
}
