<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Workspaces\Permission;
use App\Rules\SafeOutboundUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::NotificationChannelManage->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['discord', 'slack', 'webhook'])],
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
            'config.url' => ['required', 'url', new SafeOutboundUrl],
            'config.headers' => ['sometimes', 'array'],
            'config.headers.*' => ['string'],
            // A Slack app's signing secret, so approve/reject buttons in its messages can be trusted.
            'config.signing_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
