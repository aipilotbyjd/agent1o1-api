<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Broadcasts;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * Sending an email to real people: a reason, a fresh authenticator code, and
 * the recipient count the admin previewed (the send is refused if the audience
 * has moved since).
 */
class StartBroadcastRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expected_recipient_count' => ['required', 'integer', 'min:1'],
        ];
    }
}
