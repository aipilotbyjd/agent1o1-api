<?php

namespace App\Services\Agent;

use App\Agents\AgentRunner;
use App\Agents\Internal\EvalJudgeAgent;
use App\Models\Agent;
use App\Models\AgentEvalCase;
use App\Models\AgentEvalRun;
use App\Models\AgentEvalSuite;
use App\Models\Credential;
use App\Models\User;
use Throwable;

/**
 * Agent eval/testing framework (roadmap item 9).
 *
 * Runs every case in a suite against an agent and grades the response with a
 * mix of deterministic assertions (contains / regex / equals) and an LLM rubric
 * judge, producing a pass/fail report. Meant to gate publishing or catch
 * regressions after editing instructions.
 *
 * Supported assertion types:
 *   {"type": "contains",     "value": "refund"}
 *   {"type": "not_contains", "value": "sorry"}
 *   {"type": "equals",       "value": "42"}
 *   {"type": "regex",        "value": "\\d{4}"}
 *   {"type": "llm_rubric",   "value": "Politely declines and offers an alternative."}
 */
class AgentEvalService
{
    public function __construct(private readonly AgentRunner $runner) {}

    /**
     * Execute a suite against its agent and persist the results.
     */
    public function run(AgentEvalSuite $suite, ?User $triggeredBy = null): AgentEvalRun
    {
        $suite->loadMissing(['agent', 'cases']);
        $agent = $suite->agent;

        $run = AgentEvalRun::create([
            'suite_id' => $suite->id,
            'agent_id' => $agent->id,
            'triggered_by' => $triggeredBy?->id,
            'status' => 'running',
            'total' => $suite->cases->count(),
        ]);

        $credentials = $this->loadCredentials($agent);
        $results = [];
        $passed = 0;

        try {
            foreach ($suite->cases as $case) {
                $result = $this->runCase($agent, $case, $credentials);
                $results[] = $result;
                $passed += $result['passed'] ? 1 : 0;
            }

            $run->update([
                'status' => 'completed',
                'passed' => $passed,
                'failed' => count($results) - $passed,
                'results' => $results,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'passed' => $passed,
                'failed' => max(0, count($results) - $passed),
                'results' => $results,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $run;
    }

    /**
     * @param  array<string, array<string, mixed>>  $credentials
     * @return array{case_id: string, name: string, passed: bool, output: string, failures: list<string>}
     */
    private function runCase(Agent $agent, AgentEvalCase $case, array $credentials): array
    {
        try {
            $output = $this->runner->run($case->input, [
                'agent' => $agent,
                'credentials' => $credentials,
            ]);
        } catch (Throwable $e) {
            return [
                'case_id' => $case->id,
                'name' => $case->name,
                'passed' => false,
                'output' => '',
                'failures' => ['Agent errored: '.$e->getMessage()],
            ];
        }

        $failures = [];
        foreach ($case->assertions ?? [] as $assertion) {
            $failure = $this->evaluateAssertion($agent, $case->input, $output, $assertion);
            if ($failure !== null) {
                $failures[] = $failure;
            }
        }

        return [
            'case_id' => $case->id,
            'name' => $case->name,
            'passed' => $failures === [],
            'output' => $output,
            'failures' => $failures,
        ];
    }

    /**
     * Evaluate a single assertion. Returns a failure message, or null on pass.
     *
     * @param  array<string, mixed>  $assertion
     */
    private function evaluateAssertion(Agent $agent, string $input, string $output, array $assertion): ?string
    {
        $type = $assertion['type'] ?? 'contains';
        $value = (string) ($assertion['value'] ?? '');
        $haystack = $output;
        $ci = fn (string $s) => mb_strtolower($s);

        return match ($type) {
            'contains' => str_contains($ci($haystack), $ci($value))
                ? null
                : "Expected output to contain \"{$value}\".",
            'not_contains' => ! str_contains($ci($haystack), $ci($value))
                ? null
                : "Expected output NOT to contain \"{$value}\".",
            'equals' => trim($ci($haystack)) === trim($ci($value))
                ? null
                : "Expected output to equal \"{$value}\".",
            'regex' => $this->regexMatches($value, $haystack)
                ? null
                : "Expected output to match /{$value}/.",
            'llm_rubric' => $this->judgeRubric($agent, $input, $output, $value),
            default => "Unknown assertion type \"{$type}\".",
        };
    }

    private function regexMatches(string $pattern, string $subject): bool
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/i';
        $result = @preg_match($delimited, $subject);

        return $result === 1;
    }

    private function judgeRubric(Agent $agent, string $input, string $output, string $rubric): ?string
    {
        $prompt = <<<PROMPT
        Original input:
        {$input}

        Rubric (what a correct response must do):
        {$rubric}

        Agent's response:
        {$output}
        PROMPT;

        try {
            $verdict = (new EvalJudgeAgent)->prompt(
                $prompt,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable $e) {
            return 'Rubric judge failed: '.$e->getMessage();
        }

        if ((bool) ($verdict['passed'] ?? false)) {
            return null;
        }

        $reason = trim((string) ($verdict['reason'] ?? 'did not satisfy the rubric'));

        return "Rubric not met: {$reason}";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadCredentials(Agent $agent): array
    {
        $agent->loadMissing('toolConfigs');

        $neededTypes = $agent->toolConfigs
            ->where('is_enabled', true)
            ->pluck('node_type')
            ->unique()
            ->values()
            ->all();

        if ($neededTypes === []) {
            return [];
        }

        return Credential::query()
            ->where('workspace_id', $agent->workspace_id)
            ->whereIn('type', $neededTypes)
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first()->getDecryptedData())
            ->all();
    }
}
