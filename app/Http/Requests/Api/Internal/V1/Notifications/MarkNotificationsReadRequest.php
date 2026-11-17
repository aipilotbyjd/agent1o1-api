<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marks notifications read: the listed `ids`, or with `all`, everything
 * unread, optionally only in one `category` or `workspace_id`.
 */
class MarkNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required_without:all', 'array', 'max:200'],
            'ids.*' => ['uuid'],
            'all' => ['required_without:ids', 'boolean'],
            'category' => ['sometimes', 'nullable', Rule::enum(NotificationCategory::class)],
            'workspace_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
