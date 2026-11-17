<?php

namespace App\Http\Requests\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationScope;
use App\Enums\Notifications\NotificationType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Several preference changes at once, for a workspace
 * (`PUT /workspaces/{workspace}/notification-preferences/bulk`) or for the
 * account (`PUT /notifications/preferences`).
 *
 * A `key` is an event key or a category key (`category:<name>`). Events a
 * member can't change (mandatory ones, and those decided by someone else)
 * are refused, as are keys for the other scope: a workspace has no say over
 * account-level events, and the account none over workspace ones.
 *
 * `category:product_updates` (account only) is the marketing opt-out: its
 * `email` switches marketing email and messages on or off.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
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
            'preferences' => ['required', 'array', 'min:1', 'max:100'],
            'preferences.*.key' => ['required', 'string', 'distinct', $this->keyRule()],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.email' => ['sometimes', 'boolean'],
            'preferences.*.email_digest' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, array{key: string, in_app?: bool, email?: bool, email_digest?: bool}>
     */
    public function changes(): array
    {
        return $this->validated('preferences');
    }

    private function scope(): NotificationScope
    {
        return $this->route('workspace') !== null ? NotificationScope::Workspace : NotificationScope::Account;
    }

    private function keyRule(): Closure
    {
        $scope = $this->scope();

        return function (string $attribute, mixed $value, Closure $fail) use ($scope): void {
            if (! is_string($value) || ! self::acceptsKey($value, $scope)) {
                $fail('This notification setting can\'t be changed here.');
            }
        };
    }

    public static function acceptsKey(string $key, NotificationScope $scope): bool
    {
        $category = NotificationCategory::fromPreferenceKey($key);

        // Product updates are the marketing opt-out, kept on the user.
        if ($category === NotificationCategory::ProductUpdates) {
            return $scope === NotificationScope::Account;
        }

        if ($category !== null) {
            $categoryScope = $category->isAccountLevel() ? NotificationScope::Account : NotificationScope::Workspace;

            return $categoryScope === $scope
                && collect(NotificationType::inCategory($category))->contains(fn (NotificationType $event): bool => in_array($event, NotificationType::configurable(), true));
        }

        $event = NotificationType::tryFrom($key);

        return $event !== null
            && in_array($event, NotificationType::configurable(), true)
            && $event->definition()->scope === $scope;
    }
}
