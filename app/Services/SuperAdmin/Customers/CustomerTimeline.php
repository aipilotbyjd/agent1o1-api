<?php

namespace App\Services\SuperAdmin\Customers;

use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One feed of what happened to a user or a workspace, newest first, from the
 * sources the admin may read: runs, credit transactions, plan grants and the
 * admin actions on the record. A page is the newest `limit` events older than
 * `before`; `next` is the time to pass as `before` for the following page.
 *
 * Opening a record is itself in the audit log; those "viewed" rows are not
 * events in the customer's life and are left out.
 */
class CustomerTimeline
{
    public const TYPES = ['run', 'credits', 'plan', 'admin'];

    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    /**
     * @param  list<string>  $types  empty means every type the admin may see
     * @return array{events: list<array<string, mixed>>, next: ?string, skipped: list<string>}
     */
    public function page(SuperAdmin $admin, Model $subject, ?CarbonInterface $before = null, array $types = [], int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $types = $types === [] ? self::TYPES : array_values(array_intersect(self::TYPES, $types));
        $skipped = [];
        $events = collect();

        foreach ($types as $type) {
            $ability = match ($type) {
                'run' => SuperAdminAbility::RunsView,
                'credits', 'plan' => SuperAdminAbility::BillingView,
                'admin' => SuperAdminAbility::AuditView,
            };

            // Credits and plan grants belong to a workspace; a user has none.
            if (in_array($type, ['credits', 'plan'], true) && ! $subject instanceof Workspace) {
                continue;
            }

            if (! $admin->hasAbility($ability)) {
                $skipped[] = $type;

                continue;
            }

            $events = $events->concat(match ($type) {
                'run' => $this->runs($subject, $before, $limit),
                'credits' => $this->credits($subject, $before, $limit),
                'plan' => $this->planGrants($subject, $before, $limit),
                'admin' => $this->adminActions($subject, $before, $limit),
            });
        }

        $sorted = $events->sortByDesc(fn (array $event): int => Carbon::parse($event['at'])->getTimestamp())->values();
        $page = $sorted->take($limit)->values();

        return [
            'events' => $page->all(),
            'next' => $sorted->count() > $limit ? Carbon::parse($page->last()['at'])->toIso8601String() : null,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function runs(Model $subject, ?CarbonInterface $before, int $limit): Collection
    {
        $query = Run::query()->with(['workspace:id,name', 'workflow:id,name'])
            ->when($subject instanceof Workspace, fn ($query) => $query->where('workspace_id', $subject->getKey()))
            ->when($subject instanceof User, fn ($query) => $query->where('triggered_by', $subject->getKey()))
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1);

        return $query->get()->map(fn (Run $run): array => [
            'type' => 'run',
            'id' => $run->id,
            'at' => $run->created_at,
            'status' => $run->status->value,
            'workflow' => $run->workflow?->name,
            'workspace' => $run->workspace === null ? null : ['id' => $run->workspace->id, 'name' => $run->workspace->name],
            'trigger_type' => $run->trigger_type,
            'error' => $run->error === null ? null : Str::limit($run->error, 200),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function credits(Model $subject, ?CarbonInterface $before, int $limit): Collection
    {
        /** @var Workspace $subject */
        return $subject->creditTransactions()
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get()
            ->map(fn ($transaction): array => [
                'type' => 'credits',
                'id' => $transaction->id,
                'at' => $transaction->created_at,
                'credits' => $transaction->credits,
                'source_type' => $transaction->source_type->value,
                'reason' => $transaction->reason,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function planGrants(Model $subject, ?CarbonInterface $before, int $limit): Collection
    {
        /** @var Workspace $subject */
        return $subject->planGrants()->with('plan:id,name,slug')
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get()
            ->map(fn ($grant): array => [
                'type' => 'plan',
                'id' => $grant->id,
                'at' => $grant->granted_at ?? $grant->created_at,
                'plan' => $grant->plan?->name,
                'status' => $grant->status->value,
                'source' => $grant->source->value,
                'expires_at' => $grant->expires_at,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function adminActions(Model $subject, ?CarbonInterface $before, int $limit): Collection
    {
        return SuperAdminAuditLog::query()->with('admin:id,name,email')
            ->where('subject_type', CustomerSubjects::label($subject))
            ->where('subject_id', $subject->getKey())
            ->where('action', 'not like', '%viewed')
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get()
            ->map(fn (SuperAdminAuditLog $entry): array => [
                'type' => 'admin',
                'id' => $entry->id,
                'at' => $entry->created_at,
                'action' => $entry->action,
                'reason' => $entry->reason,
                'actor' => $entry->admin?->name ?? $entry->admin?->email ?? $entry->actor_label,
            ]);
    }
}
