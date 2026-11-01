<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

class ScheduleBroadcastRequest extends StartBroadcastRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
