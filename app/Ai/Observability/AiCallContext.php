<?php

namespace App\Ai\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Who and what the AI calls being made right now belong to: the workspace
 * and member paying, the feature, and the run or record they serve. The
 * provider events `AiCallRecorder` listens to carry none of that, so callers
 * describe their work here and every call made inside it is attributed.
 *
 * Bound as a scoped singleton, so it starts empty for every request and
 * queued job. `ByokProviderRegistrar::apply()`, which runs before every
 * model call, fills in the workspace and member when nobody else has.
 *
 * The trace id lives in Laravel's `Context`, so it also appears on every log
 * line written during the work and travels with any job it dispatches.
 */
class AiCallContext
{
    public const string TRACE_KEY = 'trace_id';

    /** @var array{workspace_id?: string, user_id?: string, feature?: string, run_id?: string, subject_type?: string, subject_id?: string} */
    private array $attributes = [];

    /**
     * Runs `$callback` with these attributes added, restoring the previous
     * ones afterwards (also when it throws).
     *
     * @template TReturn
     *
     * @param  array{workspace_id?: string|null, user_id?: string|null, feature?: string|null, run_id?: string|null, subject_type?: string|null, subject_id?: string|null}  $attributes
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(array $attributes, callable $callback): mixed
    {
        $previous = $this->attributes;
        $this->attributes = [...$previous, ...array_filter($attributes, fn (mixed $value): bool => $value !== null && $value !== '')];
        $this->traceId();

        try {
            return $callback();
        } finally {
            $this->attributes = $previous;
        }
    }

    /**
     * Fills in who pays for the calls about to be made, without overriding
     * anything a caller already set.
     */
    public function remember(?string $workspaceId, ?string $userId): void
    {
        if ($workspaceId !== null && ! isset($this->attributes['workspace_id'])) {
            $this->attributes['workspace_id'] = $workspaceId;
        }

        if ($userId !== null && ! isset($this->attributes['user_id'])) {
            $this->attributes['user_id'] = $userId;
        }
    }

    public function get(string $key): ?string
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return array{workspace_id?: string, user_id?: string, feature?: string, run_id?: string, subject_type?: string, subject_id?: string}
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * The id shared by every call, log line and job of this piece of work.
     */
    public function traceId(): string
    {
        $traceId = Context::get(self::TRACE_KEY);

        if (! is_string($traceId) || $traceId === '') {
            $traceId = (string) Str::uuid();
            Context::add(self::TRACE_KEY, $traceId);
        }

        return $traceId;
    }
}
