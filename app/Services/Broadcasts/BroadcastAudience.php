<?php

namespace App\Services\Broadcasts;

use App\Enums\Auth\AuthEvent;
use App\Enums\Broadcasts\BroadcastCategory;
use App\Models\Billing\Plan;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns an `audience` definition into people. One definition serves in-app
 * banners (`matches()`, one member at a time) and email campaigns (`count()`,
 * `sample()`, `each()`, a whole segment), so what an admin previews is exactly
 * what is sent.
 *
 * An audience is an array with any of these keys, all of which must hold:
 *  - `plans`: plan slugs; a member counts through a workspace whose *effective*
 *    plan (`Workspace::currentPlan()`: subscription and grants) is one of them.
 *  - `roles`: `owners` or `all` (owners and members), counted through the same
 *    workspaces. Campaigns default to owners so members aren't mailed about
 *    their employer's billing; banners default to everyone.
 *  - `workspace_ids`: only through these workspaces.
 *  - `inactive_days`: no sign-in and no run in any of their workspaces for this long.
 * An empty audience is everyone. Email sends additionally leave out suspended
 * and unverified members and, for marketing mail, those who opted out.
 */
class BroadcastAudience
{
    public const ROLES = ['owners', 'all'];

    /** How many people `sample()` returns. */
    public const SAMPLE_SIZE = 10;

    private const CHUNK = 500;

    /**
     * The definition with its defaults filled in and unknown keys dropped, so
     * stored audiences and API output always have the same shape.
     *
     * @param  array<string, mixed>|null  $audience
     * @return array{plans: list<string>, roles: string, workspace_ids: list<string>, inactive_days: int|null}
     */
    public function normalize(?array $audience, string $defaultRoles = 'owners'): array
    {
        $audience ??= [];
        $roles = $audience['roles'] ?? $defaultRoles;

        return [
            'plans' => array_values(array_unique(array_map('strval', (array) ($audience['plans'] ?? [])))),
            'roles' => in_array($roles, self::ROLES, true) ? $roles : $defaultRoles,
            'workspace_ids' => array_values(array_unique(array_map('strval', (array) ($audience['workspace_ids'] ?? [])))),
            'inactive_days' => isset($audience['inactive_days']) && (int) $audience['inactive_days'] > 0 ? (int) $audience['inactive_days'] : null,
        ];
    }

    /**
     * Whether a banner aimed at `$audience` is for this member.
     *
     * @param  array<string, mixed>|null  $audience
     */
    public function matches(User $user, ?array $audience): bool
    {
        if (blank($audience)) {
            return true;
        }

        $definition = $this->normalize($audience, 'all');

        if ($definition === $this->normalize(null, 'all')) {
            return true;
        }

        if (! $this->baseQuery($definition)->whereKey($user->getKey())->exists()) {
            return false;
        }

        if ($definition['plans'] === []) {
            return true;
        }

        return $this->workspacesThrough($user, $definition)
            ->contains(fn (Workspace $workspace): bool => in_array($workspace->currentPlan()?->slug, $definition['plans'], true));
    }

    /**
     * How many people an email to this audience would reach.
     *
     * @param  array<string, mixed>|null  $audience
     */
    public function count(?array $audience, BroadcastCategory $category): int
    {
        $total = 0;

        $this->eachQuery($audience, $category, function (Builder $query) use (&$total): void {
            $total += (clone $query)->count();
        });

        return $total;
    }

    /**
     * A few of the people it would reach, for the admin to eyeball.
     *
     * @param  array<string, mixed>|null  $audience
     * @return Collection<int, User>
     */
    public function sample(?array $audience, BroadcastCategory $category): Collection
    {
        $sample = new Collection;

        $this->eachQuery($audience, $category, function (Builder $query) use (&$sample): void {
            if ($sample->count() < self::SAMPLE_SIZE) {
                $sample = $sample->concat((clone $query)->orderBy('users.id')->limit(self::SAMPLE_SIZE - $sample->count())->get(['users.id', 'users.name', 'users.email']));
            }
        });

        return $sample->values();
    }

    /**
     * Visits the audience in chunks of users, ordered by id.
     *
     * @param  array<string, mixed>|null  $audience
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function each(?array $audience, BroadcastCategory $category, callable $callback): void
    {
        $this->eachQuery($audience, $category, function (Builder $query) use ($callback): void {
            (clone $query)->select('users.*')->chunkById(self::CHUNK, $callback, 'users.id', 'id');
        });
    }

    /**
     * Whether this person may be emailed for `$category` right now. Checked
     * again just before a message leaves, because a campaign can sit queued
     * while a member opts out or is suspended.
     */
    public function isMailable(User $user, BroadcastCategory $category): bool
    {
        return $this->onlyMailable(User::query(), $category)->whereKey($user->getKey())->exists();
    }

    /**
     * Runs `$callback` with the SQL-side query once, or once per slice of ids
     * when a plan filter makes the ids a list computed in PHP.
     *
     * @param  array<string, mixed>|null  $audience
     * @param  callable(Builder<User>): void  $callback
     */
    private function eachQuery(?array $audience, BroadcastCategory $category, callable $callback): void
    {
        $definition = $this->normalize($audience);
        $query = $this->onlyMailable($this->baseQuery($definition), $category);

        if ($definition['plans'] === []) {
            $callback($query);

            return;
        }

        foreach (array_chunk($this->userIdsOnPlans($definition), 1000) as $ids) {
            $callback((clone $query)->whereIn('users.id', $ids));
        }
    }

    /**
     * Who can be emailed at all: not suspended, a verified address, and (for
     * marketing) not opted out.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function onlyMailable(Builder $query, BroadcastCategory $category): Builder
    {
        return $query
            ->whereNull('users.suspended_at')
            ->whereNotNull('users.email_verified_at')
            ->when($category->respectsOptOut(), fn (Builder $query) => $query->whereNull('users.marketing_opted_out_at'));
    }

    /**
     * The filters that SQL can answer: role, workspaces and inactivity.
     *
     * @param  array{plans: list<string>, roles: string, workspace_ids: list<string>, inactive_days: int|null}  $definition
     * @return Builder<User>
     */
    private function baseQuery(array $definition): Builder
    {
        $query = User::query();

        if ($definition['workspace_ids'] !== [] || $definition['roles'] === 'owners') {
            $query->where(function (Builder $query) use ($definition): void {
                $query->whereHas('ownedWorkspaces', fn (Builder $workspaces) => $this->inScope($workspaces, $definition));

                if ($definition['roles'] === 'all') {
                    $query->orWhereHas('workspaces', fn (Builder $workspaces) => $this->inScope($workspaces, $definition));
                }
            });
        }

        if ($definition['inactive_days'] !== null) {
            $cutoff = now()->subDays($definition['inactive_days']);

            $query->where('users.created_at', '<', $cutoff)
                ->whereDoesntHave('authEvents', fn (Builder $events) => $events->where('event', AuthEvent::LoggedIn)->where('created_at', '>=', $cutoff))
                ->whereDoesntHave('ownedWorkspaces', fn (Builder $workspaces) => $workspaces->whereHas('runs', fn (Builder $runs) => $runs->where('runs.created_at', '>=', $cutoff)))
                ->whereDoesntHave('workspaces', fn (Builder $workspaces) => $workspaces->whereHas('runs', fn (Builder $runs) => $runs->where('runs.created_at', '>=', $cutoff)));
        }

        return $query;
    }

    /**
     * @param  Builder<Workspace>  $workspaces
     * @param  array{plans: list<string>, roles: string, workspace_ids: list<string>, inactive_days: int|null}  $definition
     */
    private function inScope(Builder $workspaces, array $definition): void
    {
        if ($definition['workspace_ids'] !== []) {
            $workspaces->whereIn($workspaces->getModel()->qualifyColumn('id'), $definition['workspace_ids']);
        }
    }

    /**
     * The workspaces a member counts through for this audience.
     *
     * @param  array{plans: list<string>, roles: string, workspace_ids: list<string>, inactive_days: int|null}  $definition
     * @return Collection<int, Workspace>
     */
    private function workspacesThrough(User $user, array $definition): Collection
    {
        $workspaces = $user->ownedWorkspaces()->get();

        if ($definition['roles'] === 'all') {
            $workspaces = $workspaces->concat($user->workspaces()->get())->unique('id');
        }

        return $definition['workspace_ids'] === []
            ? $workspaces->values()
            : $workspaces->whereIn('id', $definition['workspace_ids'])->values();
    }

    /**
     * Every member who counts through a workspace on one of the plans. The
     * effective plan is a rule in PHP (`Workspace::currentPlan()`), so it is
     * resolved per workspace rather than re-derived in SQL. When the free
     * plan isn't wanted, only workspaces that have ever held a subscription
     * or a grant can match, which skips the large free majority.
     *
     * @param  array{plans: list<string>, roles: string, workspace_ids: list<string>, inactive_days: int|null}  $definition
     * @return list<string>
     */
    private function userIdsOnPlans(array $definition): array
    {
        $wantsDefault = in_array(Plan::default()?->slug, $definition['plans'], true);
        $ids = [];

        Workspace::query()
            ->when($definition['workspace_ids'] !== [], fn (Builder $query) => $query->whereIn('id', $definition['workspace_ids']))
            ->when(! $wantsDefault, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereHas('subscriptions')->orWhereHas('planGrants')))
            ->chunkById(self::CHUNK, function (Collection $workspaces) use (&$ids, $definition): void {
                $matching = $workspaces->filter(fn (Workspace $workspace): bool => in_array($workspace->currentPlan()?->slug, $definition['plans'], true));

                foreach ($matching->pluck('owner_id') as $ownerId) {
                    $ids[$ownerId] = true;
                }

                if ($definition['roles'] === 'all' && $matching->isNotEmpty()) {
                    foreach (DB::table('workspace_members')->whereIn('workspace_id', $matching->pluck('id'))->pluck('user_id') as $memberId) {
                        $ids[$memberId] = true;
                    }
                }
            });

        return array_keys($ids);
    }
}
