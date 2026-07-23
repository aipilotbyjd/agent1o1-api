<?php

namespace App\Http\Requests\Api\V1\Agent;

/**
 * Validation rules for the advanced-agent settings from
 * docs/AGENTS_ADVANCED_ROADMAP.md, shared by the store and update requests so
 * the two never drift apart.
 */
trait AdvancedAgentRules
{
    /**
     * Dual-read: clients may send the flat fields below or the grouped
     * `settings` object (the canonical API shape going forward). A nested
     * payload is flattened onto the flat fields before validation, so one set
     * of rules covers both.
     */
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (is_array($settings)) {
            $this->merge(\App\Models\Agent::settingsToAttributes($settings));
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function advancedRules(): array
    {
        return [
            // Canonical grouped shape (flattened in prepareForValidation).
            'settings' => ['nullable', 'array'],

            // Phase 1 — intelligence & reasoning
            'planning_enabled' => ['nullable', 'boolean'],
            'reflection_enabled' => ['nullable', 'boolean'],
            'reflection_interval' => ['nullable', 'integer', 'min:1', 'max:10'],
            'child_agent_ids' => ['nullable', 'array', 'max:10'],
            'child_agent_ids.*' => ['uuid', 'exists:agents,id'],
            'memory_auto_extract' => ['nullable', 'boolean'],
            'memory_semantic_recall' => ['nullable', 'boolean'],
            'memory_recall_limit' => ['nullable', 'integer', 'min:1', 'max:50'],

            // Phase 2 — tooling & integrations
            'code_execution_enabled' => ['nullable', 'boolean'],
            'web_browsing_enabled' => ['nullable', 'boolean'],
            'tool_cache_enabled' => ['nullable', 'boolean'],

            // Phase 3 — ops & reliability
            'guardrails' => ['nullable', 'array'],
            'guardrails.input' => ['nullable', 'array'],
            'guardrails.input.enabled' => ['nullable', 'boolean'],
            'guardrails.input.policy' => ['nullable', 'string', 'max:2000'],
            'guardrails.input.block' => ['nullable', 'boolean'],
            'guardrails.output' => ['nullable', 'array'],
            'guardrails.output.enabled' => ['nullable', 'boolean'],
            'guardrails.output.policy' => ['nullable', 'string', 'max:2000'],
            'guardrails.output.block' => ['nullable', 'boolean'],
            'max_tokens_per_run' => ['nullable', 'integer', 'min:1'],
            'daily_token_budget' => ['nullable', 'integer', 'min:1'],
            'daily_cost_budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
