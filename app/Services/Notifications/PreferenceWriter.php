<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Enums\Notifications\NotificationType;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Broadcasts\MarketingPreferences;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Applies a member's preference changes, for a workspace or for their
 * account, with the same rules everywhere:
 *
 * - a change names only what it changes; a new row starts from the event's
 *   (or category's) defaults;
 * - setting a whole category clears that category's per-event rows, so
 *   "email me about billing" means every billing event;
 * - `category:product_updates` is the marketing opt-out on the user.
 *
 * Keys are validated by `UpdateNotificationPreferencesRequest` first.
 */
class PreferenceWriter
{
    public function __construct(
        private readonly MarketingPreferences $marketing,
        private readonly PreferenceResolver $resolver,
    ) {}

    /**
     * @param  array<int, array{key: string, in_app?: bool, email?: bool, email_digest?: bool}>  $changes
     */
    public function applyToWorkspace(User $user, Workspace $workspace, array $changes): void
    {
        DB::transaction(function () use ($user, $workspace, $changes): void {
            foreach ($changes as $change) {
                $category = NotificationCategory::fromPreferenceKey($change['key']);

                if ($category !== null) {
                    NotificationPreference::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('user_id', $user->id)
                        ->whereIn('event_key', $this->eventKeysIn($category))
                        ->delete();
                }

                $preference = NotificationPreference::query()->firstOrNew([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'event_key' => $change['key'],
                ]);

                $this->fill($preference, $change);
            }
        });

        $this->resolver->forget($user);
    }

    /**
     * @param  array<int, array{key: string, in_app?: bool, email?: bool, email_digest?: bool}>  $changes
     */
    public function applyToAccount(User $user, array $changes): void
    {
        DB::transaction(function () use ($user, $changes): void {
            foreach ($changes as $change) {
                $category = NotificationCategory::fromPreferenceKey($change['key']);

                if ($category === NotificationCategory::ProductUpdates) {
                    if (array_key_exists('email', $change)) {
                        $change['email'] ? $this->marketing->optIn($user) : $this->marketing->optOut($user);
                    }

                    continue;
                }

                if ($category !== null) {
                    AccountNotificationPreference::query()
                        ->where('user_id', $user->id)
                        ->whereIn('key', $this->eventKeysIn($category))
                        ->delete();
                }

                $preference = AccountNotificationPreference::query()->firstOrNew([
                    'user_id' => $user->id,
                    'key' => $change['key'],
                ]);

                $this->fill($preference, $change);
            }
        });

        $this->resolver->forget($user);
    }

    /**
     * @param  array{key: string, in_app?: bool, email?: bool, email_digest?: bool}  $change
     */
    private function fill(Model $preference, array $change): void
    {
        if (! $preference->exists) {
            $defaults = $this->defaultsFor($change['key']);
            $preference->fill([
                'in_app' => $defaults['in_app'],
                'email' => $defaults['email'],
                'email_digest' => false,
            ]);
        }

        foreach (['in_app', 'email', 'email_digest'] as $field) {
            if (array_key_exists($field, $change)) {
                $preference->{$field} = (bool) $change[$field];
            }
        }

        $preference->save();
    }

    /**
     * An event's defaults, or for a category those of its first configurable
     * event (a category row is a member's own choice, so its starting point
     * only matters for the fields they didn't send).
     *
     * @return array{in_app: bool, email: bool}
     */
    private function defaultsFor(string $key): array
    {
        $event = NotificationType::tryFrom($key);

        if ($event === null) {
            $category = NotificationCategory::fromPreferenceKey($key);
            $event = $category === null ? null : collect(NotificationType::inCategory($category))
                ->first(fn (NotificationType $event): bool => in_array($event, NotificationType::configurable(), true));
        }

        return $event?->definition()->defaults() ?? ['in_app' => true, 'email' => false];
    }

    /**
     * @return array<int, string>
     */
    private function eventKeysIn(NotificationCategory $category): array
    {
        return array_map(fn (NotificationType $event): string => $event->value, NotificationType::inCategory($category));
    }
}
