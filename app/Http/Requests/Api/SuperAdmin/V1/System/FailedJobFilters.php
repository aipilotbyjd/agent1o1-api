<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\System;

/**
 * The filter fields shared by listing, bulk retry and flushing failed jobs.
 */
final class FailedJobFilters
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'queue' => ['nullable', 'string', 'max:100'],
            'class' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }
}
