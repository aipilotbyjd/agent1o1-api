<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Services\Ai\ByokProviderRegistrar;
use Illuminate\Validation\Rule;

/**
 * Sets what a backend model costs per million tokens. Keyed by provider and
 * model id, so it covers every catalog entry that route serves. A workspace's
 * own key is never priced: its calls cost you nothing.
 *
 * `status` says whether the operator checked the rates against the provider
 * (`verified`, the default when a price is set by hand), only entered them
 * (`unverified`), or is setting a placeholder (`demo`). Long-context rates
 * apply to the whole request once its input passes the threshold.
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
            'cache_read_per_mtok' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cache_write_per_mtok' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'long_context_threshold' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'long_input_per_mtok' => ['nullable', 'required_with:long_context_threshold', 'numeric', 'min:0', 'max:100000'],
            'long_output_per_mtok' => ['nullable', 'required_with:long_context_threshold', 'numeric', 'min:0', 'max:100000'],
            'source' => ['required', Rule::enum(ModelPriceSource::class)->except(ModelPriceSource::Config)],
            'status' => ['sometimes', Rule::enum(ModelPriceStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
