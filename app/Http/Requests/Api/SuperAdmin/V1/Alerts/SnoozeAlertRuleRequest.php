<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Alerts;

use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;

class SnoozeAlertRuleRequest extends ReasonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Up to a week; 0 clears an existing snooze.
            'minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ];
    }
}
