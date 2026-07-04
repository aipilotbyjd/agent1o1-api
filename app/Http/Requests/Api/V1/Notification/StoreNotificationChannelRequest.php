<?php

namespace App\Http\Requests\Api\V1\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['slack', 'discord', 'webhook', 'sms'])],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
