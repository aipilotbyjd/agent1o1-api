<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Changelog;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleChangelogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['scheduled_for' => ['required', 'date', 'after:now']];
    }
}
