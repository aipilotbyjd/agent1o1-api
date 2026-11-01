<?php

namespace App\Services\Agents;

use App\Ai\Agents\EvalJudgeAgent;
use App\Ai\Tools\SubmitVerdictTool;
use App\Ai\ToolSubmission;
use App\Enums\Agents\EvalAssertionType;
use App\Models\Agents\Agent;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use Throwable;

/**
 * Grades one expectation against one answer.
 *
 * String assertions are compared case-insensitively: an eval that fails
 * because the agent wrote "Refund" instead of "refund" is testing
 * capitalisation, which is not what anyone writes a `contains` assertion for.
 * A test that really cares about exact casing belongs in an `llm_rubric`
 * that says so.
 */
class AssertionGrader
{
    public function __construct(
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
    ) {}

    /**
     * `$agent` is the agent under test; an `llm_rubric` is judged with the
     * judge model set in its evaluation settings, falling back to its own
     * model: see `ModelCatalogResolver::forJudging()`. A judged assertion
     * also carries the judge call's token `usage`, for `EvalRunner` to bill
     * against the case.
     *
     * @param  array<string, mixed>  $assertion  `{type, value}`
     * @param  array<int, string>  $toolCalls  names of the tools the answer called
     * @return array{type: string, value: string, passed: bool, error: string|null, usage?: array<string, int>}
     */
    public function grade(array $assertion, string $output, Agent $agent, array $toolCalls = []): array
    {
        $type = EvalAssertionType::tryFrom($assertion['type'] ?? '');
        $value = (string) ($assertion['value'] ?? '');

        if ($type === null) {
            return $this->result($assertion['type'] ?? '', $value, false, "Unknown assertion type '".($assertion['type'] ?? '')."'.");
        }

        if ($type === EvalAssertionType::ToolCalled || $type === EvalAssertionType::ToolNotCalled) {
            $called = in_array(mb_strtolower(trim($value)), array_map(mb_strtolower(...), $toolCalls), true);

            return $this->result($type->value, $value, $type === EvalAssertionType::ToolCalled ? $called : ! $called, null);
        }

        if (! $type->needsJudge()) {
            return $this->result($type->value, $value, $this->gradeLiteral($type, $value, $output), null);
        }

        try {
            [$passed, $usage] = $this->gradeWithJudge($value, $output, $agent);

            return [...$this->result($type->value, $value, $passed, null), 'usage' => $usage];
        } catch (Throwable $e) {
            // A judge that couldn't be reached is a failed *assertion*, not a
            // failed suite: the rest of the cases still carry information, and
            // reporting the reason beats reporting a silent pass.
            return $this->result($type->value, $value, false, "Judge unavailable: {$e->getMessage()}");
        }
    }

    private function gradeLiteral(EvalAssertionType $type, string $value, string $output): bool
    {
        $haystack = mb_strtolower($output);
        $needle = mb_strtolower($value);

        return match ($type) {
            EvalAssertionType::Contains => str_contains($haystack, $needle),
            EvalAssertionType::NotContains => ! str_contains($haystack, $needle),
            EvalAssertionType::Equals => trim($haystack) === trim($needle),
            EvalAssertionType::ToolCalled, EvalAssertionType::ToolNotCalled, EvalAssertionType::LlmRubric => false,
        };
    }

    /**
     * @return array{0: bool, 1: array<string, int>} the verdict and the judge call's token usage
     */
    private function gradeWithJudge(string $rubric, string $output, Agent $agent): array
    {
        [$provider, $model] = $this->byok->apply(...$this->modelCatalog->forJudging($agent, $agent->evaluationSettings?->model), workspaceId: $agent->workspace_id, userId: null);

        $response = (new EvalJudgeAgent)->prompt(EvalJudgeAgent::promptFor($rubric, $output), provider: $provider, model: $model);

        return [
            (ToolSubmission::arguments($response, SubmitVerdictTool::NAME)['passed'] ?? false) === true,
            $response->usage->toArray(),
        ];
    }

    /**
     * @return array{type: string, value: string, passed: bool, error: string|null}
     */
    private function result(string $type, string $value, bool $passed, ?string $error): array
    {
        return ['type' => $type, 'value' => $value, 'passed' => $passed, 'error' => $error];
    }
}
