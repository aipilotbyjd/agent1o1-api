<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\System;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;

/**
 * Retrying every failed job that matches a filter: a reason, and the filter.
 */
class FailedJobFilterRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), ...FailedJobFilters::rules()];
    }

    /**
     * @return array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}
     */
    public function filters(): array
    {
        return $this->safe()->only(array_keys(FailedJobFilters::rules()));
    }
}
