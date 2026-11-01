<?php

namespace App\Services\Agents;

use App\Ai\Agents\ReflectionReviewerAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitReflectionsTool;
use App\Ai\ToolSubmission;
use App\Enums\Agents\ReflectionApplyBehavior;
use App\Enums\Agents\ReflectionRunStatus;
use App\Enums\Agents\ReflectionStatus;
use App\Enums\Agents\ReflectionType;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Jobs\Agents\ApplyReflectionJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Reflection;
use App\Models\Agents\ReflectionRun;
use App\Models\Agents\ReflectionSettings;
use App\Models\Agents\Skill;
use App\Models\Runs\Run;
use App\Notifications\Agents\ReflectionReportNotification;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reviews an `Agent`'s activity since its last completed review, mines it
 * for recurring patterns, and proposes `Reflection`s: new/updated skills,
 * instruction changes, or flagged tool gaps.
 *
 * Collapses the "mine → validate against transcripts → check existing
 * knowledge" pipeline (docs/gumloop/output/raw/core-concepts/reflections.md)
 * into one model call that sees the transcripts, instructions, skills and
 * earlier reflections together, so a review costs about one conversation.
 *
 * Only a completed review moves `last_run_at`; a skipped or failed one keeps
 * its activity for the next review. Support is counted in code from the
 * sessions the model cites, so a model can't inflate its own evidence past
 * the auto-apply gate.
 */
class ReflectionAnalyzer
{
    private const AUTO_APPLY_MIN_CONFIDENCE = 75;

    private const AUTO_APPLY_MIN_SUPPORT = 3;

    private const MIN_SUPPORT = 2;

    private const MAX_MESSAGE_CHARS = 2000;

    private const MAX_TOOL_ARGUMENT_CHARS = 300;

    private const PAST_REFLECTIONS_LIMIT = 30;

    /**
     * The most recent sessions one review reads. The first review of a busy
     * agent would otherwise read its whole history, overflowing the model's
     * context so the review fails every time and the window never moves.
     */
    private const MAX_SESSIONS = 50;

    /**
     * Budget for all transcripts together (roughly 50k tokens), shared out
     * evenly so a single long session can't crowd out the rest.
     */
    private const MAX_TRANSCRIPT_CHARS = 200_000;

    public function __construct(
        private readonly CreditGate $creditGate,
        private readonly NotificationDispatcher $notifications,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
    ) {}

    public function run(Agent $agent): ReflectionRun
    {
        $settings = $agent->reflectionSettings ?? $agent->reflectionSettings()->save(new ReflectionSettings);

        $sessions = $this->sessionsSinceLastReview($agent, $settings);
        $hasEnoughActivity = $sessions->isNotEmpty() && $sessions->count() >= $settings->min_chats_threshold;

        // A skipped review costs nothing, so only a review that will actually
        // prompt the model is gated on credits.
        if ($hasEnoughActivity) {
            $this->creditGate->assertCanStartRun($agent->workspace);
        }

        $reflectionRun = $agent->reflectionRuns()->create(['workspace_id' => $agent->workspace_id]);

        if (! $hasEnoughActivity) {
            return $this->skip($agent, $settings, $reflectionRun, $sessions->count());
        }

        $reflectionRun->forceFill(['status' => ReflectionRunStatus::Running, 'started_at' => now()])->save();

        $run = $this->openRun($reflectionRun, $agent);

        try {
            [$candidates, $usage] = $this->minePatterns($agent, $settings, $sessions);
            $created = $this->proposeReflections($agent, $reflectionRun, $settings, $sessions, $candidates);

            $reflectionRun->forceFill([
                'status' => ReflectionRunStatus::Completed,
                'sessions_analyzed_count' => $sessions->count(),
                'usage' => $usage,
                'finished_at' => now(),
            ])->save();

            // The window restarts where this review started, so a chat that
            // happened while the model was analyzing is still picked up next time.
            $settings->forceFill(['last_run_at' => $reflectionRun->started_at])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['reflections_proposed' => $created],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));
        } catch (Throwable $e) {
            $reflectionRun->forceFill([
                'status' => ReflectionRunStatus::Failed,
                'sessions_analyzed_count' => $sessions->count(),
                'skip_reason' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            $run->forceFill(['status' => RunStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()])->save();

            event(new RunFailed($run));
        }

        $this->report($agent, $settings, $reflectionRun->fresh());

        return $reflectionRun->fresh();
    }

    /**
     * The agent's own chats since the last review, at most the latest
     * `MAX_SESSIONS`, oldest first. Conversations a subagent ran on a
     * parent's behalf are left out: they aren't chats with a person, and the
     * clones of one request would otherwise count as separate sessions
     * showing the same pattern, inflating support past the auto-apply gate.
     *
     * @return Collection<int, AgentSession>
     */
    private function sessionsSinceLastReview(Agent $agent, ReflectionSettings $settings): Collection
    {
        $since = $settings->last_run_at;

        return $agent->sessions()
            ->with([
                'messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
                'runs' => fn ($query) => $query->where('status', RunStatus::Failed->value)->orderBy('created_at'),
            ])
            ->when($since, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('last_activity_at', '>', $since)
                ->orWhere(fn (Builder $query) => $query->whereNull('last_activity_at')->where('created_at', '>', $since))))
            ->whereNull('parent_session_id')
            ->latest()
            ->latest('id')
            ->limit(self::MAX_SESSIONS)
            ->get()
            ->reverse()
            ->values();
    }

    private function skip(Agent $agent, ReflectionSettings $settings, ReflectionRun $reflectionRun, int $sessionCount): ReflectionRun
    {
        $reason = $sessionCount === 0
            ? 'No new chats since the last review.'
            : "Only {$sessionCount} chat(s) since the last review; needs at least {$settings->min_chats_threshold}.";

        $reflectionRun->forceFill([
            'status' => ReflectionRunStatus::Skipped,
            'skip_reason' => $reason,
            'sessions_analyzed_count' => $sessionCount,
            'finished_at' => now(),
        ])->save();

        $this->report($agent, $settings, $reflectionRun);

        return $reflectionRun;
    }

    /**
     * @param  Collection<int, AgentSession>  $sessions
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function minePatterns(Agent $agent, ReflectionSettings $settings, Collection $sessions): array
    {
        $charsPerSession = intdiv(self::MAX_TRANSCRIPT_CHARS, max(1, $sessions->count()));

        $transcript = $sessions->values()
            ->map(fn (AgentSession $session, int $index): string => Str::limit($this->transcriptFor($session, $index + 1), $charsPerSession))
            ->implode("\n\n---\n\n");

        $existingSkills = $agent->skills->isEmpty()
            ? 'None.'
            : $agent->skills->map(fn ($skill): string => "- [id {$skill->id}] {$skill->name}:\n{$skill->instructions}")->implode("\n\n");

        $pastReflections = $agent->reflections()
            ->where('status', '!=', ReflectionStatus::Superseded->value)
            ->latest()
            ->limit(self::PAST_REFLECTIONS_LIMIT)
            ->get(['title', 'status', 'type'])
            ->map(fn (Reflection $reflection): string => "- [{$reflection->status->value}] ({$reflection->type->value}) {$reflection->title}")
            ->implode("\n") ?: 'None.';

        $instructions = trim((string) $agent->instructions) !== '' ? $agent->instructions : 'None.';

        $toolName = SubmitReflectionsTool::NAME;

        $extra = $settings->extra_instructions
            ? "\nExtra focus requested by the agent owner:\n{$settings->extra_instructions}\n"
            : '';

        $prompt = <<<PROMPT
            You are reviewing an AI agent's recent activity to find recurring patterns worth fixing: repeated
            mistakes, recurring tool errors, inefficient tool usage, missing knowledge, requests the agent
            handled the same multi-step way more than once, or places it asked for clarification it shouldn't
            have needed. Only propose a pattern that shows up across at least 2 distinct sessions. Reject
            anything that happened once, is already handled by the current instructions or skills, or is
            simply part of the job.

            Agent's current instructions:
            {$instructions}

            Agent's existing skills:
            {$existingSkills}

            Earlier reflections for this agent, newest first:
            {$pastReflections}
            Never re-propose a pending or dismissed one. If an applied one's problem is still showing up, you may
            propose a new fix for it and say in the rationale that the earlier fix didn't hold.
            {$extra}
            Activity since the last review (one block per session):
            {$transcript}

            Pick the type that fits:
            - "new_skill": a repeated multi-step workflow. proposed_prompt is the full instructions of the new skill.
            - "skill_fix": an existing skill misses a case. proposed_prompt is the COMPLETE revised instructions for
              that skill (it replaces the current text, so keep everything that still applies). Set target_skill_id.
            - "instruction_update": a behavioral rule or domain fact. proposed_prompt is the COMPLETE revised agent
              instructions (it replaces the current instructions, so keep everything that still applies).
            - "tool_access": the agent is working around a missing integration or permission. proposed_prompt
              describes exactly what access is needed and why.

            Submit your findings with the {$toolName} tool, citing in session_numbers the sessions that show
            each pattern. Submit an empty list if nothing qualifies.
            PROMPT;

        // Reviewing an agent's own sessions is grading work: a stronger
        // model chosen as the grader catches mistakes the agent's own model
        // would repeat.
        [$provider, $model] = $this->byok->apply(...$this->modelCatalog->forJudging($agent, $agent->evaluationSettings?->model), workspaceId: $agent->workspace_id, userId: null);

        $startedAt = now();

        $response = (new ReflectionReviewerAgent)->prompt($prompt, provider: $provider, model: $model);

        $reflections = ToolSubmission::arguments($response, SubmitReflectionsTool::NAME)['reflections'] ?? [];

        return [
            is_array($reflections) && array_is_list($reflections) ? $reflections : [],
            ResponseUsage::from($response, $startedAt),
        ];
    }

    private function transcriptFor(AgentSession $session, int $number): string
    {
        $lines = $session->messages->flatMap(function (AgentMessage $message): array {
            $lines = collect($message->tool_calls ?? [])
                ->map(fn (array $call): string => sprintf(
                    'tool call: %s(%s)',
                    $call['name'] ?? 'unknown',
                    Str::limit(json_encode($call['arguments'] ?? []) ?: '', self::MAX_TOOL_ARGUMENT_CHARS),
                ))
                ->all();

            if (trim((string) $message->content) !== '') {
                $lines[] = "{$message->role->value}: ".Str::limit($message->content, self::MAX_MESSAGE_CHARS);
            }

            return $lines;
        });

        $errors = $session->runs->map(fn (Run $run): string => 'turn failed: '.Str::limit((string) $run->error, self::MAX_TOOL_ARGUMENT_CHARS));

        return "Session {$number} ({$session->created_at->toDateString()}):\n".$lines->merge($errors)->implode("\n");
    }

    /**
     * @param  Collection<int, AgentSession>  $sessions
     * @param  array<int, mixed>  $candidates
     */
    private function proposeReflections(
        Agent $agent,
        ReflectionRun $reflectionRun,
        ReflectionSettings $settings,
        Collection $sessions,
        array $candidates,
    ): int {
        $sessionIdsByNumber = $sessions->values()->mapWithKeys(fn (AgentSession $session, int $index): array => [$index + 1 => $session->id]);
        // A synced skill is changed in its repository, so no fix is proposed for it.
        $skillIds = $agent->skills->reject(fn (Skill $skill): bool => $skill->isSynced())->pluck('id')->map(fn ($id): string => (string) $id);
        $dismissedTitles = $agent->reflections()
            ->where('status', ReflectionStatus::Dismissed->value)
            ->pluck('title')
            ->map(fn (string $title): string => Str::lower(trim($title)));

        $created = 0;

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $type = ReflectionType::tryFrom((string) ($candidate['type'] ?? ''));
            $title = trim((string) ($candidate['title'] ?? ''));
            $proposedPrompt = trim((string) ($candidate['proposed_prompt'] ?? ''));

            if ($type === null || $title === '' || $proposedPrompt === '') {
                continue;
            }

            if ($dismissedTitles->contains(Str::lower($title))) {
                continue;
            }

            $supportingSessionIds = collect(is_array($candidate['session_numbers'] ?? null) ? $candidate['session_numbers'] : [])
                ->filter(fn ($number): bool => is_numeric($number))
                ->map(fn ($number) => $sessionIdsByNumber->get((int) $number))
                ->filter()
                ->unique()
                ->values();

            if ($supportingSessionIds->count() < self::MIN_SUPPORT) {
                continue;
            }

            $targetSkillId = null;

            if ($type === ReflectionType::SkillFix) {
                $targetSkillId = (string) ($candidate['target_skill_id'] ?? '');

                if (! $skillIds->contains($targetSkillId)) {
                    continue;
                }
            }

            // A newer proposal for the same recurring pattern supersedes the
            // older pending one rather than sitting alongside it.
            $agent->reflections()
                ->where('status', ReflectionStatus::Pending->value)
                ->where('title', $title)
                ->update(['status' => ReflectionStatus::Superseded->value]);

            $reflection = $reflectionRun->reflections()->create([
                'workspace_id' => $agent->workspace_id,
                'agent_id' => $agent->id,
                'type' => $type->value,
                'title' => $title,
                'rationale' => (string) ($candidate['rationale'] ?? ''),
                'evidence' => ['session_ids' => $supportingSessionIds->all()],
                'confidence' => max(0, min(100, (int) ($candidate['confidence'] ?? 0))),
                'support_count' => $supportingSessionIds->count(),
                'proposed_prompt' => $proposedPrompt,
                'target_skill_id' => $targetSkillId,
            ]);

            $created++;

            if ($this->isAutoApplyEligible($settings, $reflection)) {
                ApplyReflectionJob::dispatch($reflection);
            }
        }

        return $created;
    }

    private function isAutoApplyEligible(ReflectionSettings $settings, Reflection $reflection): bool
    {
        return $settings->apply_behavior === ReflectionApplyBehavior::AutoApply
            && $reflection->type->isAutoApplyEligible()
            && $reflection->confidence >= self::AUTO_APPLY_MIN_CONFIDENCE
            && $reflection->support_count >= self::AUTO_APPLY_MIN_SUPPORT;
    }

    private function openRun(ReflectionRun $reflectionRun, Agent $agent): Run
    {
        $run = $reflectionRun->runs()->create([
            'workspace_id' => $agent->workspace_id,
            'trigger_type' => 'reflection',
            'input' => ['reflection_run_id' => $reflectionRun->id],
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }

    private function report(Agent $agent, ReflectionSettings $settings, ReflectionRun $reflectionRun): void
    {
        if ($reflectionRun->status === ReflectionRunStatus::Skipped && ! $settings->notify_on_skip) {
            return;
        }

        // Which channel(s) each recipient actually receives this on (email,
        // in-app, webhook) is decided by `WorkspaceEventNotification::via()`
        // against their own `NotificationPreference` for
        // `ReflectionRunCompleted`: this only decides whether the event
        // fires at all.
        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($agent->workspace),
            new ReflectionReportNotification($reflectionRun),
        );
    }
}
