<?php

namespace App\Services\Agents;

use App\Ai\Agents\SessionEvalJudgeAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\SubmitEvaluationTool;
use App\Ai\ToolSubmission;
use App\Enums\Agents\SessionEvaluationGrade;
use App\Enums\Agents\SessionEvaluationStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Runs\Run;
use App\Notifications\Agents\SessionEvaluationNotifyNotification;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Notifications\NotificationDispatcher;
use Throwable;

/**
 * Grades one `AgentSession`'s transcript against its agent's
 * `AgentEvaluationSettings` and records the result: the live-conversation
 * counterpart to `EvalRunner`'s offline suite grading. See
 * `Listeners\Agents\ScheduleSessionEvaluation` for how this gets called
 * (debounced, off `RunCompleted`) and
 * docs/gumloop/output/raw/core-concepts/evaluations.md for the feature this
 * mirrors.
 *
 * A session that continues after being graded and completes again produces
 * a new evaluation that replaces the old one (`updateOrCreate` on
 * `agent_session_id`), same as Gumloop's "you always see the most recent
 * evaluation result".
 */
class SessionEvaluator
{
    public function __construct(
        private readonly CreditGate $creditGate,
        private readonly SessionEvaluationGrader $grader,
        private readonly NotificationDispatcher $notifications,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
    ) {}

    /**
     * Returns null without doing anything when evaluations aren't enabled
     * for this agent: the caller (the queued job) doesn't need to know why
     * an evaluation didn't happen, only that it may not have.
     */
    public function evaluate(AgentSession $session): ?AgentSessionEvaluation
    {
        $agent = $session->agent;
        $settings = $agent->evaluationSettings;

        if ($settings === null || ! $settings->is_enabled) {
            return null;
        }

        $this->creditGate->assertCanStartRun($session->workspace);

        $evaluation = AgentSessionEvaluation::updateOrCreate(
            ['agent_session_id' => $session->id],
            ['workspace_id' => $session->workspace_id, 'agent_id' => $agent->id],
        );

        $evaluation->forceFill(['status' => SessionEvaluationStatus::Evaluating, 'error' => null])->save();

        $run = $this->openRun($evaluation, $session);

        try {
            [$decoded, $usage] = $this->judge($session, $settings);

            $criteriaResults = is_array($decoded['criteria_results'] ?? null) ? $decoded['criteria_results'] : [];
            $callSuccessful = (string) ($decoded['call_successful'] ?? 'unknown');
            $sentiment = $settings->sentiment_enabled ? ($decoded['sentiment'] ?? null) : null;

            $grade = $this->grader->grade(
                $criteriaResults,
                $settings->criteria ?? [],
                $callSuccessful,
                $sentiment,
                $settings->sentiment_affects_grade,
            );

            $evaluation->forceFill([
                'status' => SessionEvaluationStatus::Completed,
                'grade' => $grade,
                'call_successful' => $callSuccessful,
                'sentiment' => $sentiment,
                'summary' => $decoded['summary'] ?? null,
                'criteria_results' => $criteriaResults,
                'data_results' => is_array($decoded['data_results'] ?? null) ? $decoded['data_results'] : [],
                'applied_tags' => is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [],
                'usage' => $usage,
                'evaluated_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['grade' => $grade->value],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));

            if ($grade === SessionEvaluationGrade::NeedsAttention) {
                $this->notifyAttention($evaluation->fresh());
            }
        } catch (Throwable $e) {
            $evaluation->forceFill(['status' => SessionEvaluationStatus::Failed, 'error' => $e->getMessage()])->save();

            $run->forceFill(['status' => RunStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()])->save();

            event(new RunFailed($run));
        }

        return $evaluation->fresh();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} the verdict and its usage
     */
    private function judge(AgentSession $session, AgentEvaluationSettings $settings): array
    {
        $agent = $session->agent;
        $transcript = $this->transcriptFor($session);
        [$provider, $model] = $this->byok->apply(...$this->modelCatalog->forJudging($agent, $settings->model), workspaceId: $session->workspace_id, userId: $session->user_id);

        $startedAt = now();

        $response = (new SessionEvalJudgeAgent)->prompt(
            SessionEvalJudgeAgent::promptFor($agent, $settings, $transcript),
            provider: $provider,
            model: $model,
        );

        return [
            ToolSubmission::arguments($response, SubmitEvaluationTool::NAME),
            ResponseUsage::from($response, $startedAt),
        ];
    }

    private function transcriptFor(AgentSession $session): string
    {
        return $session->messages()->orderBy('created_at')->orderBy('id')->get()
            ->map(fn ($message): string => "{$message->role->value}: {$message->content}")
            ->implode("\n");
    }

    private function openRun(AgentSessionEvaluation $evaluation, AgentSession $session): Run
    {
        $run = $evaluation->runs()->create([
            'workspace_id' => $session->workspace_id,
            'trigger_type' => 'session_evaluation',
            'input' => ['agent_session_id' => $session->id],
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }

    private function notifyAttention(AgentSessionEvaluation $evaluation): void
    {
        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($evaluation->workspace),
            new SessionEvaluationNotifyNotification($evaluation),
        );
    }
}
