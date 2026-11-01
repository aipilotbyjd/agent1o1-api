<?php

namespace App\Services\SuperAdmin\Audit;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records every change a super admin makes, from the API or the command
 * line, so "who gave this workspace a year of Pro?" always has an answer.
 *
 * `$subject` is stored as a snake_case label of its class plus its key (not
 * a morph type) so a new model never needs registering. Recording an entry
 * also flags the current request as audited, which tells
 * `AuditSuperAdminRequest` not to add its generic fallback row.
 */
class SuperAdminAuditLogger
{
    public const AUDITED_ATTRIBUTE = 'super_admin_audited';

    public const REQUEST_ID_ATTRIBUTE = 'super_admin_request_id';

    /** The key of the PostgreSQL advisory lock that serialises appends to the chain. */
    private const int ADVISORY_LOCK = 724901347;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        ?SuperAdmin $actor,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?string $actorLabel = null,
    ): SuperAdminAuditLog {
        $request = request();
        $overHttp = $request->route() !== null;

        $request->attributes->set(self::AUDITED_ATTRIBUTE, true);

        return $this->append([
            'super_admin_id' => $actor?->id,
            'actor_label' => $actorLabel ?? ($actor === null ? $this->defaultActorLabel() : null),
            'action' => $action,
            'subject_type' => $subject !== null ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => $overHttp ? $request->ip() : null,
            'user_agent' => $overHttp ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'request_id' => $overHttp ? $this->requestId($request) : null,
        ]);
    }

    /**
     * Writes the entry as the next link of the chain. The chain's end is read
     * under a lock that lasts until the surrounding transaction commits (the
     * caller's, if it has one: audited changes usually run in one), so two
     * entries can never be given the same place; the unique index on `sequence`
     * is the backstop.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function append(array $attributes): SuperAdminAuditLog
    {
        return DB::transaction(function () use ($attributes): SuperAdminAuditLog {
            $last = $this->lockedChainEnd();

            $entry = new SuperAdminAuditLog($attributes);
            $entry->forceFill([
                'sequence' => ($last?->sequence ?? 0) + 1,
                'previous_hash' => $last?->hash,
                // Whole seconds, because that is what the database keeps and the hash must match it.
                'created_at' => now()->startOfSecond(),
            ]);
            $entry->hash = AuditChain::hash($entry);
            $entry->save();

            return $entry;
        });
    }

    /**
     * The newest chained entry, locked against anyone else appending until the
     * transaction ends. PostgreSQL cannot lock "the newest row" reliably (a
     * waiting writer is handed the row it waited on, not the new newest), so it
     * takes an advisory lock instead; elsewhere the row lock does it.
     */
    private function lockedChainEnd(): ?SuperAdminAuditLog
    {
        $newest = SuperAdminAuditLog::query()->whereNotNull('sequence')->orderByDesc('sequence');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK]);

            return $newest->first(['sequence', 'hash']);
        }

        return $newest->lockForUpdate()->first(['sequence', 'hash']);
    }

    /**
     * Records an update, keeping only the attributes that actually changed so
     * the log reads as a diff rather than two full snapshots. Returns null
     * (and writes nothing) when nothing changed.
     *
     * @param  array<string, mixed>  $original
     */
    public function recordChanges(?SuperAdmin $actor, string $action, Model $subject, array $original, ?string $reason = null, ?string $actorLabel = null): ?SuperAdminAuditLog
    {
        $changes = collect($subject->getChanges())->except(['updated_at']);

        if ($changes->isEmpty()) {
            return null;
        }

        return $this->record(
            $actor,
            $action,
            $subject,
            $changes->mapWithKeys(fn (mixed $value, string $key): array => [$key => $original[$key] ?? null])->all(),
            $changes->all(),
            $reason,
            $actorLabel,
        );
    }

    /**
     * Who to name when no super admin is acting: the command line when run from
     * it (the referral `referrals:*` commands, say), otherwise the system itself.
     */
    private function defaultActorLabel(): string
    {
        return app()->runningInConsole() ? 'cli:'.get_current_user() : 'system';
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->headers->get('X-Request-Id') ?? $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        return is_string($id) && $id !== '' ? Str::limit($id, 64, '') : null;
    }
}
