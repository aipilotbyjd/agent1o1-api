<?php

namespace App\Listeners\Agents;

use App\Events\Runs\RunCompleted;
use App\Jobs\Agents\EvaluateAgentSessionJob;
use App\Models\Agents\AgentSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Debounces `SessionEvaluator` off every agent turn's `RunCompleted`, the
 * same event `RecordRunCreditUsage` bills against. Fires once per burst of
 * turns rather than once per turn: each call stores a fresh token in cache
 * and (re)schedules `EvaluateAgentSessionJob` after
 * `DEBOUNCE_SECONDS`, so a conversation still going when this fires gets
 * superseded by the next turn's token: see the job's docblock.
 *
 * Never fires for a `Run` whose runnable isn't an `AgentSession`: an eval
 * suite run, a reflection run, or a session evaluation's own `Run` (opened
 * by `SessionEvaluator` itself) all complete without ever reaching here, so
 * grading a session can't recursively schedule another evaluation.
 */
class ScheduleSessionEvaluation implements ShouldQueue
{
    /**
     * Long enough that a still-active conversation isn't graded mid-thought,
     * short enough that a completed one is graded promptly.
     */
    private const int DEBOUNCE_SECONDS = 30;

    public function handle(RunCompleted $event): void
    {
        $run = $event->run->loadMissing('runnable');

        // A subagent's conversation is part of its parent's work, not a chat
        // with a person; grading each one would bill a judge call per clone.
        if (! $run->runnable instanceof AgentSession || $run->runnable->parent_session_id !== null) {
            return;
        }

        $session = $run->runnable;
        $settings = $session->agent->evaluationSettings;

        if ($settings === null || ! $settings->is_enabled) {
            return;
        }

        $token = (string) Str::uuid();

        Cache::put(self::cacheKey($session->id), $token, now()->addSeconds(self::DEBOUNCE_SECONDS + 60));

        EvaluateAgentSessionJob::dispatch($session, $token)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
    }

    public static function cacheKey(string $sessionId): string
    {
        return "session-evaluation:debounce:{$sessionId}";
    }
}
