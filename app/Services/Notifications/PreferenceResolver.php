<?php

namespace App\Services\Notifications;

use App\Enums\Notifications\NotificationType;
use App\Enums\Notifications\NotificationScope;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides which channels a member gets an event on. The most specific choice
 * wins, a whole row at a time:
 *
 * 1. a mandatory event always uses its defaults;
 * 2. workspace-scoped events: the member's row for the event in that
 *    workspace, then their row for its category there;
 * 3. account-scoped events (or no workspace): the person's account row for
 *    the event, then for its category;
 * 4. the event's defaults (`NotificationDefinition`).
 *
 * Preferences are loaded once per member and workspace and kept for the rest
 * of the request or job (the resolver is scoped), and `warm()` loads a whole
 * recipient list in two queries, so a send never queries per recipient per
 * channel.
 */
class PreferenceResolver
{
    /**
     * @var array<string, Collection<string, NotificationPreference>>
     */
    private array $workspacePreferences = [];

    /**
     * @var array<string, Collection<string, AccountNotificationPreference>>
     */
    private array $accountPreferences = [];

    /**
     * Loads the preferences of every recipient of a send up front.
     *
     * @param  iterable<int, User>  $users
     */
    public function warm(iterable $users, ?string $workspaceId): void
    {
        $userIds = collect($users)
            ->map(fn (User $user): string => (string) $user->getKey())
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        if ($workspaceId !== null) {
            $missing = $userIds->reject(fn (string $userId): bool => isset($this->workspacePreferences[$this->workspaceCacheKey($userId, $workspaceId)]));
            $rows = NotificationPreference::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('user_id', $missing)
                ->get()
                ->groupBy('user_id');

            foreach ($missing as $userId) {
                $this->workspacePreferences[$this->workspaceCacheKey($userId, $workspaceId)] = ($rows->get($userId) ?? collect())->keyBy('event_key');
            }
        }

        $missing = $userIds->reject(fn (string $userId): bool => isset($this->accountPreferences[$userId]));
        $rows = AccountNotificationPreference::query()->whereIn('user_id', $missing)->get()->groupBy('user_id');

        foreach ($missing as $userId) {
            $this->accountPreferences[$userId] = ($rows->get($userId) ?? collect())->keyBy('key');
        }
    }

    public function resolve(User $user, NotificationType $event, ?string $workspaceId): ResolvedPreference
    {
        $definition = $event->definition();
        $defaults = new ResolvedPreference($definition->defaultInApp, $definition->defaultEmail);

        if ($definition->mandatory) {
            return $defaults;
        }

        $categoryKey = $definition->category->preferenceKey();

        if ($definition->scope === NotificationScope::Workspace && $workspaceId !== null) {
            $rows = $this->workspaceRowsFor($user, $workspaceId);
            $row = $rows->get($event->value) ?? $rows->get($categoryKey);
        } else {
            $rows = $this->accountRowsFor($user);
            $row = $rows->get($event->value) ?? $rows->get($categoryKey);
        }

        if ($row === null) {
            return $defaults;
        }

        return new ResolvedPreference((bool) $row->in_app, (bool) $row->email, (bool) $row->email_digest);
    }

    /**
     * Drops what was loaded, after a member changes their preferences.
     */
    public function forget(User $user): void
    {
        $userId = (string) $user->getKey();
        unset($this->accountPreferences[$userId]);

        foreach (array_keys($this->workspacePreferences) as $key) {
            if (str_starts_with($key, "{$userId}|")) {
                unset($this->workspacePreferences[$key]);
            }
        }
    }

    /**
     * @return Collection<string, NotificationPreference>
     */
    private function workspaceRowsFor(User $user, string $workspaceId): Collection
    {
        $key = $this->workspaceCacheKey((string) $user->getKey(), $workspaceId);

        if (! isset($this->workspacePreferences[$key])) {
            $this->warm([$user], $workspaceId);
        }

        return $this->workspacePreferences[$key];
    }

    /**
     * @return Collection<string, AccountNotificationPreference>
     */
    private function accountRowsFor(User $user): Collection
    {
        $userId = (string) $user->getKey();

        if (! isset($this->accountPreferences[$userId])) {
            $this->warm([$user], null);
        }

        return $this->accountPreferences[$userId];
    }

    private function workspaceCacheKey(string $userId, string $workspaceId): string
    {
        return "{$userId}|{$workspaceId}";
    }
}
