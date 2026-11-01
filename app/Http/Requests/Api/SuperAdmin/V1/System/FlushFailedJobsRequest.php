<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\System;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * Permanently deleting failed jobs that match a filter: a reason and a fresh
 * authenticator code. At least one filter is required, so a bare request can
 * never empty the table by accident.
 */
class FlushFailedJobsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...FailedJobFilters::rules(),
            'everything' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}
     */
    public function filters(): array
    {
        return $this->safe()->only(array_keys(FailedJobFilters::rules()));
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function ($validator): void {
                if ($validator->errors()->isEmpty() && array_filter($this->filters()) === [] && ! $this->boolean('everything')) {
                    $validator->errors()->add('everything', 'Give a filter, or set "everything" to true to delete every failed job.');
                }
            },
        ];
    }
}
