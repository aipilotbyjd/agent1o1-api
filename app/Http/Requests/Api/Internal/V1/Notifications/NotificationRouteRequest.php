<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationEvent;
use App\Enums\Notifications\NotificationScope;
use App\Enums\Notifications\NotificationSeverity;
use App\Enums\Workspaces\Permission;
use App\Models\Workspaces\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creates or changes a route from a workspace's notifications to one of its
 * chat or webhook channels. A route covers one `event_key`, one `category`,
 * or (neither) every workspace event; account-level events never leave the
 * person's own inbox, so they can't be routed.
 */
class NotificationRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::NotificationChannelManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $creating = $this->isMethod('post');

        return [
            'notification_channel_id' => [
                $creating ? 'required' : 'sometimes',
                'uuid',
                Rule::exists('notification_channels', 'id')->where('workspace_id', $workspace instanceof Workspace ? $workspace->id : null),
            ],
            'event_key' => ['sometimes', 'nullable', Rule::enum(NotificationEvent::class)],
            'category' => ['sometimes', 'nullable', Rule::enum(NotificationCategory::class)],
            'min_severity' => ['sometimes', 'nullable', Rule::enum(NotificationSeverity::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $eventKey = $this->input('event_key');
                $category = $this->input('category');

                if ($eventKey !== null && $category !== null) {
                    $validator->errors()->add('event_key', 'A route covers one event or one category, not both.');
                }

                $event = is_string($eventKey) ? NotificationEvent::tryFrom($eventKey) : null;

                if ($event !== null && $event->definition()->scope !== NotificationScope::Workspace) {
                    $validator->errors()->add('event_key', 'Only workspace events can be sent to a channel.');
                }

                $categoryCase = is_string($category) ? NotificationCategory::tryFrom($category) : null;

                if ($categoryCase !== null && $categoryCase->isAccountLevel()) {
                    $validator->errors()->add('category', 'Only workspace events can be sent to a channel.');
                }
            },
        ];
    }
}
