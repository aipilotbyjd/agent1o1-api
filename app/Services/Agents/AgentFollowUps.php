<?php

namespace App\Services\Agents;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Timezones\Timezones;
use App\Services\Triggers\TriggerService;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Follow-ups an agent schedules for itself ("I'll check this PR again
 * tomorrow at 9"). Each is an ordinary schedule `Trigger` aimed at the
 * agent, so it shows in the agent's triggers, counts toward the plan's
 * active triggers, and can be paused or deleted there like any other.
 *
 * What makes it a follow-up is `config.follow_up`: the task the agent set
 * itself and the conversation it was set in. When it fires,
 * `TargetRunStarter` continues that conversation instead of starting a
 * fresh one, so the result lands where the person asked for it and the
 * agent has the context it scheduled the work with.
 *
 * A one-time follow-up (`run_at`) switches itself off after firing, like any
 * one-time schedule; a repeating one (`cron`) runs until it is cancelled.
 */
class AgentFollowUps
{
    public const int MAX_TASK_CHARS = 2000;

    public const int MAX_TITLE_CHARS = 120;

    /**
     * @param  array{task?: mixed, title?: mixed, run_at?: mixed, cron?: mixed, timezone?: mixed}  $input
     *
     * @throws ValidationException when the follow-up can't be scheduled as asked
     */
    public function schedule(Agent $agent, AgentSession $session, ?string $userId, array $input): Trigger
    {
        $task = Str::limit(trim((string) ($input['task'] ?? '')), self::MAX_TASK_CHARS, '');
        $runAt = filled($input['run_at'] ?? null) ? trim((string) $input['run_at']) : null;
        $cron = filled($input['cron'] ?? null) ? trim((string) $input['cron']) : null;
        $timezone = $this->timezone($agent, $userId, $input['timezone'] ?? null);

        if ($task === '') {
            throw ValidationException::withMessages(['task' => 'Say what you will do when the follow-up runs.']);
        }

        if (($runAt === null) === ($cron === null)) {
            throw ValidationException::withMessages(['run_at' => 'Give either run_at (one time) or cron (repeating), not both or neither.']);
        }

        $schedule = $runAt !== null
            ? ['run_once_at' => $this->oneTimeSlot($runAt, $timezone)->toIso8601String()]
            : ['cron' => $this->repeating($cron, $timezone)];

        if ($this->active($agent)->count() >= (int) config('triggers.agent_follow_ups.max_active_per_agent')) {
            throw ValidationException::withMessages(['task' => 'This agent already has the most follow-ups it may have waiting. Cancel one first.']);
        }

        $title = Str::limit(Str::squish((string) ($input['title'] ?? '')), self::MAX_TITLE_CHARS, '') ?: Str::limit(Str::squish($task), 60);

        // Resolved here rather than injected: the trigger pipeline starts agent
        // turns, whose tools include this class, so injecting it would loop.
        return app(TriggerService::class)->create(
            $agent->workspace,
            TriggerTargetType::Agent,
            $agent->id,
            TriggerType::Schedule,
            ['config' => [
                ...$schedule,
                'timezone' => $timezone,
                'follow_up' => [
                    'title' => $title,
                    'task' => $task,
                    'agent_session_id' => $session->id,
                    'scheduled_at' => now()->toIso8601String(),
                ],
            ]],
            $userId !== null ? User::query()->find($userId) : null,
        );
    }

    /**
     * Follow-ups of this agent still waiting to run, soonest first. With a
     * user, only the ones set while working for them.
     *
     * @return Collection<int, Trigger>
     */
    public function active(Agent $agent, ?string $userId = null, bool $forUser = false): Collection
    {
        return $this->query($agent)
            ->where('is_active', true)
            ->when($forUser, fn (Builder $query) => $userId === null ? $query->whereNull('created_by') : $query->where('created_by', $userId))
            ->orderByRaw('next_run_at is null')
            ->orderBy('next_run_at')
            ->get();
    }

    /**
     * Switches one of this agent's follow-ups off, if it was set while
     * working for this user. Kept rather than deleted, so its history stays
     * in the agent's triggers.
     */
    public function cancel(Agent $agent, string $triggerId, ?string $userId): ?Trigger
    {
        $trigger = $this->query($agent)
            ->whereKey($triggerId)
            ->when($userId === null, fn (Builder $query) => $query->whereNull('created_by'), fn (Builder $query) => $query->where('created_by', $userId))
            ->first();

        if ($trigger === null) {
            return null;
        }

        $trigger->update(['is_active' => false]);

        return $trigger;
    }

    /**
     * How a follow-up is described back to the agent.
     *
     * @return array{id: string, title: string|null, task: string|null, repeats: bool, schedule: string|null, next_run: string|null, timezone: string, active: bool}
     */
    public function describe(Trigger $trigger): array
    {
        $timezone = Timezones::resolve($trigger->config['timezone'] ?? null, $trigger->workspace?->timezone ?? Timezones::DEFAULT);

        return [
            'id' => $trigger->id,
            'title' => $trigger->config['follow_up']['title'] ?? null,
            'task' => $trigger->config['follow_up']['task'] ?? null,
            'repeats' => filled($trigger->config['cron'] ?? null),
            'schedule' => $trigger->config['cron'] ?? null,
            'next_run' => $trigger->next_run_at?->copy()->tz($timezone)->format('D, M j, Y g:i A'),
            'timezone' => $timezone,
            'active' => $trigger->is_active,
        ];
    }

    /**
     * The message the agent is sent when a follow-up fires: the task it set
     * itself, marked as a follow-up so it doesn't read as the person asking
     * again.
     */
    public function messageFor(Trigger $trigger): string
    {
        $followUp = $trigger->config['follow_up'] ?? [];
        $timezone = Timezones::resolve($trigger->config['timezone'] ?? null, $trigger->workspace?->timezone ?? Timezones::DEFAULT);

        try {
            $setOn = filled($followUp['scheduled_at'] ?? null) ? Carbon::parse($followUp['scheduled_at'])->tz($timezone)->format('l, F j, Y') : null;
        } catch (Throwable) {
            $setOn = null;
        }

        $when = $setOn !== null ? " on {$setOn}" : '';
        $repeat = filled($trigger->config['cron'] ?? null) ? ' It repeats on a schedule until it is cancelled.' : '';

        return "[Scheduled follow-up] You scheduled this follow-up yourself{$when}.{$repeat} Do it now and report what you found or did:\n\n"
            .($followUp['task'] ?? '');
    }

    /**
     * Whether a trigger is an agent follow-up.
     */
    public static function isFollowUp(Trigger $trigger): bool
    {
        return is_array($trigger->config['follow_up'] ?? null);
    }

    /**
     * @return Builder<Trigger>
     */
    private function query(Agent $agent): Builder
    {
        return Trigger::query()
            ->with('workspace')
            ->where('workspace_id', $agent->workspace_id)
            ->where('target_type', TriggerTargetType::Agent->value)
            ->where('target_id', $agent->id)
            ->where('type', TriggerType::Schedule)
            ->whereNotNull('config->follow_up');
    }

    /**
     * The follow-up's timezone: the one the model gave if it's real, else
     * the person's own, so "tomorrow at 9" means 9 on their clock.
     */
    private function timezone(Agent $agent, ?string $userId, mixed $requested): string
    {
        $default = ($userId !== null ? User::query()->find($userId) : null)?->timezoneIn($agent->workspace)
            ?? $agent->workspace?->timezone
            ?? Timezones::DEFAULT;

        return Timezones::resolve(Timezones::canonical($requested), $default);
    }

    /**
     * @throws ValidationException
     */
    private function oneTimeSlot(string $runAt, string $timezone): CarbonInterface
    {
        try {
            $slot = Carbon::parse($runAt, $timezone)->utc()->startOfMinute();
        } catch (Throwable) {
            throw ValidationException::withMessages(['run_at' => 'run_at is not a date and time. Use ISO 8601, e.g. 2026-05-04T09:00.']);
        }

        if ($slot->lte(now())) {
            throw ValidationException::withMessages(['run_at' => 'run_at is in the past. Pick a time in the future (it is now '.now($timezone)->format('Y-m-d H:i')." in {$timezone})."]);
        }

        if ($slot->gt(now()->addDays((int) config('triggers.agent_follow_ups.max_days_ahead')))) {
            throw ValidationException::withMessages(['run_at' => 'run_at is too far ahead: a follow-up can be at most '.config('triggers.agent_follow_ups.max_days_ahead').' days away.']);
        }

        return $slot;
    }

    /**
     * A valid cron that doesn't run more often than the configured minimum,
     * checked over its next few runs so "every 5 minutes during 9-10" can't
     * slip through on a gap between days.
     *
     * @throws ValidationException
     */
    private function repeating(string $cron, string $timezone): string
    {
        if (! CronExpression::isValidExpression($cron)) {
            throw ValidationException::withMessages(['cron' => 'cron is not a valid 5-field cron expression, e.g. "0 9 * * 1-5" for 9:00 on weekdays.']);
        }

        $minimum = (int) config('triggers.agent_follow_ups.min_interval_minutes');
        $runs = (new CronExpression($cron))->getMultipleRunDates(6, now(), false, false, $timezone);

        for ($i = 1, $count = count($runs); $i < $count; $i++) {
            if (($runs[$i]->getTimestamp() - $runs[$i - 1]->getTimestamp()) / 60 < $minimum) {
                throw ValidationException::withMessages(['cron' => "A repeating follow-up can run at most once every {$minimum} minutes."]);
            }
        }

        return $cron;
    }
}
