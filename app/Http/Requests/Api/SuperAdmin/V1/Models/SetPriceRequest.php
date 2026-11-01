<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Enums\Ai\ModelPriceSource;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Services\Ai\ByokProviderRegistrar;
use Illuminate\Validation\Rule;

/**
 * Sets what a backend model costs per million tokens. Keyed by provider and
 * model id, so it covers every catalog entry that route serves. A workspace's
 * own key is never priced: its calls cost you nothing.
 */
class SetPriceRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'provider' => ['required', 'string', 'max:100', 'not_regex:/^'.preg_quote(ByokProviderRegistrar::PREFIX, '/').'/'],
            'model_id' => ['required', 'string', 'max:200'],
            'input_per_mtok' => ['required', 'numeric', 'min:0', 'max:100000'],
            'output_per_mtok' => ['required', 'numeric', 'min:0', 'max:100000'],
            'source' => ['required', Rule::enum(ModelPriceSource::class)->except(ModelPriceSource::Config)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
