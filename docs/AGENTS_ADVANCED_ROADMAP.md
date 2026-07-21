# Agents — Advanced Roadmap

Goal: make the user-facing **Agents** feature (`app/Models/Agent.php`, `AgentRunner`, `ProcessAgentMessageJob`) more advanced than Gumloop's agent offering. Scope: agent intelligence/reasoning, tooling/integrations, ops/reliability. UX (visual builder) is already covered and out of scope here.

Phases are ordered by leverage vs. effort; suggested build order is at the bottom.

## Phase 1 — Agent Intelligence & Reasoning

1. **Planner/Executor split** — `AgentRunner`/`WorkflowRefinementAgent` currently run a single tool-calling loop up to `max_steps`. Add a planning step before execution: the agent drafts a plan (sub-goals), stores it on the run, executes step-by-step against it, and revises the plan if a tool result contradicts an assumption.
2. **Reflection/self-correction loop** — After each tool call (or every N steps), let the agent critique its own result via a structured-output call (reuse the `HasStructuredOutput` + `schema()` pattern from `SentimentAgent`) before continuing — catches bad API responses, malformed data, premature completion.
3. **Multi-agent delegation ("sub-agents")** — Extend the `default_workflow_id` pattern: let an `Agent` have `child_agent_ids` it can invoke as tools (mirrors `WorkflowTool` but for agents). E.g. a "Support Agent" delegates billing questions to a "Billing Agent" with its own knowledge/memory. Biggest differentiator vs. Gumloop, which doesn't do agent-to-agent orchestration.
4. **Long-horizon memory upgrade** — `agent_memories` is flat key/value today. Add automatic memory extraction (agent proposes memories at end of a run, approved automatically or by a human) and semantic retrieval (embed memories, pull top-K relevant ones into context instead of dumping all of them).

## Phase 2 — Tooling & Integrations

5. **Code execution sandbox tool** — generic "run Python/JS" tool (sandboxed, e.g. a container or Piston/E2B-style service) for data transforms/calculations/parsing that a single API call can't do.
6. **Browsing / computer-use tool** — a `WebBrowseTool` (fetch + read page, or full computer-use) so agents aren't limited to pre-registered APIs.
7. **Connector templates on top of the generic API node** — keep the flexible AnyAPI-style node, but ship presets (Slack, Sheets, Stripe, Gmail — pre-filled auth/schema, user just plugs in a key) so it feels like Gumloop's one-click connectors without losing the underlying genericity.
8. **Tool result caching/dedup** — cache repeated read-only tool calls with identical args within a run to cut latency/cost on chatty runs.

## Phase 3 — Ops & Reliability

9. **Agent eval/testing framework** — before publishing an agent (or after editing instructions), run it against a saved set of test conversations/expected behaviors and get a pass/fail + diff report. Gumloop has no real eval story here.
10. **Versioning & rollback** — snapshot `instructions`/tool config/skills on every save (`agent_versions` table), allow diffing and rollback.
11. **Cost & rate guardrails** — per-agent budget caps (max tokens/run, max spend/day), automatic pause + alert on breach. `agent_runs` already tracks token usage; this adds enforcement.
12. **Structured observability** — dashboard over existing `agent_runs`/`ai_agent_steps`: latency percentiles, tool failure rates, most-triggered tools, cost per conversation.
13. **Guardrails/safety layer** — input/output moderation hook (reuse the `HasStructuredOutput` classification pattern) before/after the main agent call, configurable per agent (PII leakage, profanity, off-topic drift).

## Suggested build order

1. Planner/reflection loop (items 1–2) — biggest reasoning upgrade, touches `AgentRunner` only.
2. Connector templates (item 7) — fastest visible "advanced" feel for users, no core architecture change.
3. Sub-agent delegation (item 3) — standout differentiator vs. Gumloop.
4. Eval framework (item 9) + versioning (item 10) — makes agents trustworthy enough to actually deploy, where Gumloop is weakest.
5. Code execution + browsing tools (items 5–6) — expands what agents can do.
6. Guardrails, cost caps, observability (items 11–13) — hardening once the above is live.
