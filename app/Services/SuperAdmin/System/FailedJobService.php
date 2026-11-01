<?php

namespace App\Services\SuperAdmin\System;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Reads and acts on Laravel's `failed_jobs` table. Payloads are shown with
 * secrets redacted and the serialized job body removed: an operator needs to
 * know what failed and why, not to read another tenant's data out of a job.
 */
class FailedJobService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function count(): int
    {
        return $this->table()->count();
    }

    /**
     * @param  array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (object $row): array => $this->summarize($row));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $uuid): ?array
    {
        $row = $this->table()->where('uuid', $uuid)->first();

        return $row === null ? null : $this->detail($row);
    }

    /**
     * Failed-job counts per queue since `$since`.
     *
     * @return array<string, int>
     */
    public function failedPerQueue(Carbon $since): array
    {
        return $this->table()->where('failed_at', '>=', $since)
            ->select('queue', DB::raw('count(*) as total'))
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * The failure picture of the last `$hours`: which job classes fail most and on which queues.
     *
     * @return array{window_hours: int, total: int, by_class: list<array{class: string, failures: int, last_failed_at: ?string}>, by_queue: array<string, int>}
     */
    public function summary(int $hours): array
    {
        $rows = $this->table()->where('failed_at', '>=', now()->subHours($hours))->get(['queue', 'payload', 'failed_at']);

        $byClass = [];

        foreach ($rows as $row) {
            $class = $this->jobClass($row->payload);
            $byClass[$class]['class'] = $class;
            $byClass[$class]['failures'] = ($byClass[$class]['failures'] ?? 0) + 1;
            $byClass[$class]['last_failed_at'] = max($byClass[$class]['last_failed_at'] ?? '', (string) $row->failed_at) ?: null;
        }

        $byClass = array_values($byClass);
        usort($byClass, fn (array $a, array $b): int => $b['failures'] <=> $a['failures']);

        return [
            'window_hours' => $hours,
            'total' => $rows->count(),
            'by_class' => array_slice($byClass, 0, 10),
            'by_queue' => $rows->countBy('queue')->sortDesc()->all(),
        ];
    }

    public function retry(SuperAdmin $actor, string $uuid, string $reason): void
    {
        abort_unless($this->table()->where('uuid', $uuid)->exists(), 404, 'No such failed job.');

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->audit->record($actor, 'system.failed_job_retried', null, null, ['uuid' => $uuid], $reason);
    }

    /**
     * Retries every failed job matching the filter, up to the configured cap.
     *
     * @param  array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}  $filters
     * @return array{retried: int, remaining: int}
     */
    public function retryMatching(SuperAdmin $actor, array $filters, string $reason): array
    {
        $limit = (int) config('super_admin_health.bulk_retry_limit');
        $matching = $this->filtered($filters)->orderBy('failed_at');
        $total = (clone $matching)->count();
        $uuids = $matching->limit($limit)->pluck('uuid')->all();

        foreach (array_chunk($uuids, 100) as $chunk) {
            Artisan::call('queue:retry', ['id' => $chunk]);
        }

        $this->audit->record($actor, 'system.failed_jobs_bulk_retried', null, null, ['count' => count($uuids), 'filters' => array_filter($filters)], $reason);

        return ['retried' => count($uuids), 'remaining' => max(0, $total - count($uuids))];
    }

    public function delete(SuperAdmin $actor, string $uuid, string $reason): void
    {
        abort_unless($this->table()->where('uuid', $uuid)->exists(), 404, 'No such failed job.');

        app('queue.failer')->forget($uuid);

        $this->audit->record($actor, 'system.failed_job_deleted', null, null, ['uuid' => $uuid], $reason);
    }

    /**
     * @param  array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}  $filters
     */
    public function flush(SuperAdmin $actor, array $filters, string $reason): int
    {
        $deleted = $this->filtered($filters)->delete();

        $this->audit->record($actor, 'system.failed_jobs_flushed', null, null, ['count' => $deleted, 'filters' => array_filter($filters)], $reason);

        return $deleted;
    }

    /**
     * @param  array{queue?: ?string, class?: ?string, from?: ?string, to?: ?string, search?: ?string}  $filters
     */
    private function filtered(array $filters): Builder
    {
        return $this->table()
            ->when(filled($filters['queue'] ?? null), fn (Builder $query) => $query->where('queue', $filters['queue']))
            // Matched on the class's own name: backslashes in a LIKE pattern mean different things per database.
            ->when(filled($filters['class'] ?? null), fn (Builder $query) => $query->where('payload', 'like', '%'.addcslashes(class_basename($filters['class']), '%_').'%'))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->where('failed_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->where('failed_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where('exception', 'like', '%'.$filters['search'].'%'));
    }

    private function table(): Builder
    {
        return DB::connection(config('queue.failed.database'))->table(config('queue.failed.table', 'failed_jobs'));
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(object $row): array
    {
        return [
            'id' => $row->uuid,
            'connection' => $row->connection,
            'queue' => $row->queue,
            'job' => $this->jobClass($row->payload),
            'exception' => str(strtok((string) $row->exception, "\n") ?: '')->limit(300, '')->toString(),
            'failed_at' => Carbon::parse($row->failed_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(object $row): array
    {
        $payload = json_decode((string) $row->payload, true) ?: [];

        if (isset($payload['data']['command'])) {
            $payload['data']['command'] = '[serialized job omitted]';
        }

        return $this->summarize($row) + [
            'attempts' => $payload['attempts'] ?? null,
            'max_tries' => $payload['maxTries'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'payload' => $this->redact($payload),
            'stack_trace' => $this->redactText(str((string) $row->exception)->limit(20000, '')->toString()),
        ];
    }

    private function jobClass(string $payload): string
    {
        return (string) (json_decode($payload, true)['displayName'] ?? 'unknown');
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function redact(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $value[$key] = '[redacted]';
            } elseif (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (config('super_admin_health.redact_keys') as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function redactText(string $text): string
    {
        return (string) preg_replace('/(password|secret|token|api[_-]?key|authorization)(["\']?\s*[:=]\s*["\']?)[^\s"\',;)]+/i', '$1$2[redacted]', $text);
    }
}
