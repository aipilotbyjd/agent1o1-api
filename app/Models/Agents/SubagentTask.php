<?php

namespace App\Models\Agents;

use App\Enums\Agents\SubagentTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of work a conversation handed to a subagent through
 * `InvokeAgentTool`. It runs in the background (`RunSubagentTaskJob`) in its
 * own conversation (`session_id`), and the parent picks up the result with
 * `WaitForSubagentsTool`, which stamps `collected_at` so each result is
 * reported once.
 *
 * A task whose job was lost (a crashed worker, a flushed queue) would stay
 * queued or running forever, holding one of the parent's
 * `InvokeAgentTool::MAX_CONCURRENT` slots and never reaching the parent:
 * `failStale()` gives up on it once it's had far longer than a job can run.
 */
#[Fillable(['workspace_id', 'parent_session_id', 'agent_id', 'session_id', 'task'])]
class SubagentTask extends Model
{
    use HasUuids;

    /**
     * Well past `RunSubagentTaskJob::$timeout`, allowing for queue wait.
     */
    public const STALE_AFTER_MINUTES = 15;

    /**
     * Marks `$parentSessionId`'s subagent tasks that are still unfinished long
     * after they were started as failed. One waiting on an approval is
     * waiting on a person, not stuck, so it's left alone.
     */
    public static function failStale(string $parentSessionId): void
    {
        static::query()
            ->where('parent_session_id', $parentSessionId)
            ->whereIn('status', [SubagentTaskStatus::Queued->value, SubagentTaskStatus::Running->value])
            ->where('created_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update([
                'status' => SubagentTaskStatus::Failed->value,
                'error' => 'The subagent never finished.',
                'finished_at' => now(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubagentTaskStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function parentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'parent_session_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
