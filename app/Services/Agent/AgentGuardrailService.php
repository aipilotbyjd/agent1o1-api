<?php

namespace App\Services\Agent;

use App\Agents\Internal\ModerationAgent;
use App\Models\Agent;
use Throwable;

/**
 * Guardrails / safety layer (roadmap item 13).
 *
 * Runs a configurable moderation classifier on the agent's input (before the
 * main call) and/or output (after). Configuration lives on the agent's
 * `guardrails` JSON:
 *
 *   {
 *     "input":  {"enabled": true, "policy": "No requests for medical advice.", "block": true},
 *     "output": {"enabled": true, "policy": "Never reveal internal pricing.",  "block": true}
 *   }
 *
 * When a stage is disabled or unconfigured, checks are skipped. Failures never
 * throw — a broken moderation call must not take down the whole run, so it
 * fails open (allows) by default.
 */
class AgentGuardrailService
{
    /**
     * @return array{flagged: bool, categories: list<string>, reason: string, block: bool}|null
     */
    public function checkInput(Agent $agent, string $text): ?array
    {
        return $this->check($agent, 'input', $text);
    }

    /**
     * @return array{flagged: bool, categories: list<string>, reason: string, block: bool}|null
     */
    public function checkOutput(Agent $agent, string $text): ?array
    {
        return $this->check($agent, 'output', $text);
    }

    /**
     * @return array{flagged: bool, categories: list<string>, reason: string, block: bool}|null
     */
    private function check(Agent $agent, string $stage, string $text): ?array
    {
        $config = $agent->guardrails[$stage] ?? null;

        if (! is_array($config) || ! ($config['enabled'] ?? false) || trim($text) === '') {
            return null;
        }

        $policy = trim((string) ($config['policy'] ?? ''));

        if ($policy === '') {
            return null;
        }

        $prompt = <<<PROMPT
        Policy:
        {$policy}

        Text ({$stage}):
        {$text}
        PROMPT;

        try {
            $response = (new ModerationAgent)->prompt(
                $prompt,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable) {
            return null; // fail open
        }

        $flagged = (bool) ($response['flagged'] ?? false);

        return [
            'flagged' => $flagged,
            'categories' => array_values((array) ($response['categories'] ?? [])),
            'reason' => trim((string) ($response['reason'] ?? '')),
            'block' => $flagged && (bool) ($config['block'] ?? true),
        ];
    }

    /**
     * The message shown to the caller when a guardrail blocks a request/response.
     */
    public function blockedMessage(string $stage, array $result): string
    {
        $reason = $result['reason'] !== '' ? " {$result['reason']}" : '';

        return $stage === 'input'
            ? "This request was blocked by a safety guardrail.{$reason}"
            : "The response was withheld by a safety guardrail.{$reason}";
    }
}
