# Agent Architecture

The platform runs two first-class agent types through one engine.

| | User Agents | Internal Agents |
|---|---|---|
| Defined in | `agents` table (workspace-scoped) | Code, `app/Agents/Internal/**` |
| Registered in | Database | `App\Agents\Internal\Registry` |
| Configured by | Customers via API | `config/agents.php` → `internal` |
| Billing | Workspace budgets | Rolled up into the parent run |
| Observability | `agent_runs` + `ai_agent_steps` | `internal_agent_runs` (per LLM call) |

## Layout

```
app/Agents/
├── Contracts/        AgentType enum, AgentDefinition value object
├── Engine/           AgentExecutor, PromptAssembler, ToolResolver, InternalRunRecorder
├── User/             UserAgent (model → definition), ConversationAgent (runtime)
├── Internal/         InternalAgent base, Registry, then by category:
│   ├── Reasoning/    PlannerAgent, ReflectionAgent, ErrorDiagnosisAgent
│   ├── Memory/       MemoryExtractionAgent
│   ├── Safety/       ModerationAgent
│   ├── Evaluation/   EvalJudgeAgent
│   ├── Workflow/     WorkflowBuilder/Description/Enhancement/Naming/Refinement, Node*
│   └── Utility/      Chat, Vision, Sentiment, Summarizer, TextClassifier, StructuredExtract, SkillGenerator
└── Tools/            Tool implementations (unchanged)
```

## Execution flow

Both types compile to an `AgentDefinition` and run through `AgentExecutor`:

1. Resolve — `UserAgent::compile($model, ...)` or `Registry::get('planner')`.
2. Guard — budgets/pause/input moderation (user agents only).
3. Execute — prompt assembly → tool resolution → LLM loop.
4. Hooks — reflection, memory extraction, output moderation (all internal agents).
5. Record — `agent_runs` plus `internal_agent_runs` rows linked by `parent_run_id`.

`App\Agents\AgentRunner` remains as a thin adapter for existing callers
(jobs, `AgentTool`, evals) and delegates to the engine.

## Internal agents

Every internal agent extends `App\Agents\Internal\InternalAgent` and is named
in `Registry::MAP`. Call them with `->run($prompt, $options)` (not `->prompt()`)
to get:

- provider/model resolution: `config('agents.internal.overrides.<name>')`
  → caller's provider/model → `config('agents.internal.defaults')`;
- a recorded `internal_agent_runs` row (tokens, cost, duration, status),
  attributed via `$options['parent_run_id']` / `$options['workspace_id']`.

Adding a new internal agent = one class + one `Registry::MAP` entry.

## Agent settings (user agents)

`agents.type` discriminates future DB-backed types (`user` today). The flat
feature columns remain storage, but the API's canonical shape is the grouped
`settings` object exposed on `AgentResource` and accepted by store/update
requests (flattened in `AdvancedAgentRules::prepareForValidation()`):

```json
{
  "settings": {
    "reasoning": {"planning_enabled": true, "reflection_enabled": false, "reflection_interval": 1, "child_agent_ids": []},
    "memory":    {"auto_extract": true, "semantic_recall": true, "recall_limit": 6},
    "tools":     {"code_execution_enabled": false, "web_browsing_enabled": false, "cache_enabled": false},
    "limits":    {"max_steps": 15, "timeout_seconds": 180, "max_tokens_per_run": null, "daily_token_budget": null, "daily_cost_budget": null},
    "guardrails": {"input": {"enabled": true, "policy": "...", "block": true}}
  }
}
```

## Cost visibility

- `GET .../runs/{run}` now includes `internal_runs[]`, `internal_cost`, `internal_tokens`.
- `GET .../agents/meta/internal-agents` lists the registry with resolved
  models and per-workspace usage aggregates.
- `AgentBudgetService` counts internal-run tokens/cost toward daily budgets,
  so planner/reflection/moderation overhead is no longer invisible.
