<?php

namespace App\Services\Agents\Approvals;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\ActionReviewerAgent;
use App\Ai\Tools\SubmitRiskAssessmentTool;
use App\Ai\ToolSubmission;
use App\Enums\Agents\ActionRisk;
use App\Enums\Agents\AgentMessageRole;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\AgentAction;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Smart mode's risk check for one call. Fails closed: a reviewer that
 * errors, times out or answers with something unreadable yields `High`, so
 * the call asks rather than runs.
 *
 * Runs on the judge model from the agent's evaluation settings when one is
 * set (the same one evals use), else the agent's own model, and is billed
 * like any other judge call.
 */
class ActionReviewer
{
    /**
     * How many of the conversation's earlier actions the reviewer sees.
     */
    public const int PREVIOUS_ACTIONS = 10;

    public function __construct(
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
        private readonly DeductCreditsAction $deductCredits,
        private readonly CreditMeter $meter,
    ) {}

    /**
     * @return array{risk: ActionRisk, reason: string, usage: array<string, mixed>|null}
     */
    public function review(ActionContext $context, ToolCall $call): array
    {
        $agent = $context->agent;

        try {
            [$provider, $model] = $this->byok->apply(...$this->modelCatalog->forJudging($agent, $agent->evaluationSettings?->model), workspaceId: $context->run->workspace_id, userId: $context->run->triggered_by);

            $response = (new ActionReviewerAgent)->prompt(
                ActionReviewerAgent::promptFor(
                    (string) $agent->instructions,
                    $this->latestRequest($context),
                    $call->toolName,
                    $call->effectiveArguments,
                    $this->previousActions($context),
                ),
                provider: $provider,
                model: $model,
            );

            $usage = [...$response->usage->toArray(), ...$response->meta->toArray()];
            $this->charge($context, $usage);

            $submission = ToolSubmission::arguments($response, SubmitRiskAssessmentTool::NAME);

            return [
                'risk' => ActionRisk::tryFrom((string) ($submission['risk'] ?? '')) ?? ActionRisk::High,
                'reason' => Str::limit(trim((string) ($submission['reason'] ?? '')), 500) ?: 'The reviewer gave no reason.',
                'usage' => $usage,
            ];
        } catch (Throwable $e) {
            Log::warning('Agent action review failed; asking instead.', [
                'agent_id' => $agent->id,
                'tool' => $call->toolName,
                'exception' => $e->getMessage(),
            ]);

            return ['risk' => ActionRisk::High, 'reason' => 'The safety review could not be completed, so this needs a person to decide.', 'usage' => null];
        }
    }

    private function latestRequest(ActionContext $context): string
    {
        if ($context->session === null) {
            return (string) ($context->run->input['message'] ?? '');
        }

        return (string) $context->session->messages()
            ->where('role', AgentMessageRole::User)
            ->latest('id')
            ->value('content');
    }

    /**
     * @return list<string>
     */
    private function previousActions(ActionContext $context): array
    {
        if ($context->session === null) {
            return [];
        }

        return $context->session->actions()
            ->latest('id')
            ->limit(self::PREVIOUS_ACTIONS)
            ->get()
            ->reverse()
            ->map(fn (AgentAction $action): string => "- {$action->tool_name} ({$action->status->value}): ".Str::limit((string) json_encode($action->effectiveArguments()), 300))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function charge(ActionContext $context, array $usage): void
    {
        $credits = $this->meter->costForActionReview($usage);

        if ($credits === 0) {
            return;
        }

        $this->deductCredits->execute(
            $context->run->workspace,
            CreditTransactionType::ActionReview,
            (string) Str::uuid(),
            $credits,
            'Agent action review',
            allowOverdraft: true,
        );
    }
}
