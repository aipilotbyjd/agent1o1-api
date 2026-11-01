> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# App Policies

> Govern how your organization uses third-party apps inside Gumloop: block risky tool calls, require corporate email domains, and claim provider workspaces.

App Policies put guardrails around every third-party app that runs through
Gumloop. Organization admins set org-wide policies — block risky tool calls,
require corporate email domains when people connect their accounts, and claim a
specific provider workspace (a Slack workspace, Salesforce org, Notion
workspace, etc.) for your organization. Anyone building an agent can also add
[agent-level App Rules](/enterprise-features/app-policies/app-rules#rule-scopes)
to their own agents.

<Frame>
  <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/app-policies-overview.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=55f4460c6b2c387b506fc8f4f15512da" alt="App Policies page showing the App Rules tab with enforcement stats, an activity histogram, and a list of rules grouped by server" width="2314" height="1632" data-path="images/enterprise-features/app-policies/app-policies-overview.png" />
</Frame>

## Where to find it

Go to **Settings → Organization → App Policies** at
[gumloop.com/settings/organization/app-policies](https://gumloop.com/settings/organization/app-policies).

The page has three tabs that map to the three policy types:

<CardGroup cols={3}>
  <Card title="App Rules" icon="shield-halved" href="/enterprise-features/app-policies/app-rules">
    Block or tag specific tool calls before or after they run.
  </Card>

  <Card title="Domain Restrictions" icon="at" href="/enterprise-features/app-policies/domain-restrictions">
    Require new OAuth connections to use a corporate email domain.
  </Card>

  <Card title="App Claims" icon="building-lock" href="/enterprise-features/app-policies/app-claims">
    Claim a provider workspace so only your org members can connect to it.
  </Card>
</CardGroup>

## Who can use it

<Warning>
  App Policies is an **Enterprise** feature. The page is visible to users with
  the **Admin** or **Security** [organization role](/core-concepts/organization_user_roles).
  Everyone else in your organization will not see it in settings.
</Warning>

Organization-wide policies (App Rules, Domain Restrictions, and App Claims) are
managed from that page by admins. Agent-level App Rules are different: any user
who can configure an agent can create them from the agent's configuration panel
or agent chat, and they only affect that agent's tool calls.

## How it works end-to-end

At a high level, App Policies hook into three different moments:

| Moment                              | Policy type that fires     | What Gumloop checks                                             |
| ----------------------------------- | -------------------------- | --------------------------------------------------------------- |
| A user connects a new OAuth account | Domain Restrictions        | Does the user's email domain match the allowlist for this app?  |
| A user connects a new OAuth account | App Claims                 | Is this provider workspace claimed by a different organization? |
| An agent or pipeline invokes a tool | App Rules (phase `before`) | Does any enabled rule for this app and tool say "block"?        |
| A tool call finishes                | App Rules (phase `after`)  | Does any enabled rule want to block or tag based on the output? |

All three policy types live behind the same page, share the same target model
(see [Target scope](#target-scope) below), and follow the same
fail-closed rule: if Gumloop cannot evaluate a policy cleanly, the request is
treated as blocked.

### Evaluation pipeline for a tool call

When any tool call runs inside Gumloop (from an agent, a pipeline operator, a
user chatting with an agent, or a [Hosted MCP](/enterprise-features/hosted_mcps) server), the platform
calls an internal `check_rules` step both before the tool executes and again
after the result comes back. That check walks every enabled App Rule for the
organization in priority order and applies these steps per rule:

<Steps>
  <Step title="Phase match">
    Skip the rule unless its `check_type` matches the current phase
    (`before` vs. `after`).
  </Step>

  <Step title="Target match">
    Skip the rule unless its target matches the caller. The target can be the
    whole organization, a specific user, or a specific agent (for App Rules,
    agent-level targets are supported).
  </Step>

  <Step title="Scope match">
    Skip the rule if its `scope` restricts it to specific tool names and the
    current tool isn't in that list.
  </Step>

  <Step title="Condition match">
    Evaluate the rule's CEL condition against the call context (see the
    [next section](#how-natural-language-maps-to-code) for what's available).
    If the expression returns `false`, skip the rule. If the expression errors,
    treat it as a match (fail-closed).
  </Step>

  <Step title="Apply the action">
    * **`tag`:** record the rule name against this tool call and keep
      evaluating the remaining rules.
    * **`block`:** stop evaluation immediately and return a blocked decision.
      The tool call is denied with a message that depends on the rule's target
      scope (see [Target scope](#target-scope)).
  </Step>
</Steps>

If no rule matches, the call is allowed and any accumulated tags are attached
to it. Every evaluation (allowed, tagged, or blocked) is recorded and shows up
in the **Enforcement Activity** histogram and the per-rule **Activity** tab.

### How natural language maps to code

You don't have to write CEL or JSON by hand. The AI rule builder on the
**App Rules** tab takes a plain-English description of what you want and
produces a structured rule with:

* **`check_type`** — `"before"` (pre-flight) or `"after"` (post-flight). *"Stop
  people from sending …"* becomes `"before"`; *"Flag calls that returned
  sensitive fields"* becomes `"after"`.
* **`action`** — `"block"` or `"tag"`. *"Don't allow …"* becomes `"block"`;
  *"Just monitor …"* becomes `"tag"`.
* **`tool_names`** — specific tools on the app the rule applies to, if the
  prompt named any (e.g. "sending messages" on Slack becomes `["send_message"]`).
  Omitted means "any tool on this app".
* **`conditions`** — a CEL expression evaluated with these variables:
  * `args` — the arguments passed to the tool (e.g. `args.channel`,
    `args.query`, `args.to`).
  * `tool_name` — the tool the caller invoked.
  * `server_id` — the app the tool belongs to.
  * `output` — the tool's return value (available only on `after` rules).

For example, *"Do not allow users to send messages in the #general channel
(ID C05QG7RF30A)"* compiles to:

```json theme={"dark"}
{
  "check_type": "before",
  "action": "block",
  "tool_names": ["send_message"],
  "conditions": "args.channel == \"C05QG7RF30A\""
}
```

Every time you edit the prompt, the builder re-renders this JSON and re-runs
it against the most recent real tool calls on that app, so you can see exactly
which past calls the rule would have caught before you save it. See
[App Rules](/enterprise-features/app-policies/app-rules) for the full flow.

### How agents and pipelines see a policy decision

Policies are enforced inside Gumloop — an agent or pipeline can't bypass them
from its own runtime. Here's what each side sees:

* **Allowed:** the tool runs normally and returns its result. The caller has
  no idea a check happened.
* **Tagged:** the tool still runs and returns its result normally. The tags
  show up in audit views and in the **Activity** tab of each tagging rule, but
  they don't change what the caller sees.
* **Blocked:** the tool doesn't run. The caller (agent or pipeline
  operator) receives an error carrying the reason *"This action has been
  restricted by your organization's security policy."* Agents handle this the
  same way they handle any other tool error: they surface the failure in the
  conversation and can decide whether to try a different tool, ask the user
  for clarification, or abort the task. Pipeline operators fail their step
  with the same message.

End users never see *which* rule blocked them, just that the action was
denied. Admins can see the full context — including the rule name, the
matched condition, and the arguments — in the rule's **Activity** tab.

## Target scope

Every policy is created against a **target** that decides who the policy
applies to. From most to least broad:

| Target       | Applies to                                  | Where to manage                                                                                                                                |
| ------------ | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Organization | Everyone in your organization (the default) | [App Policies settings page](https://www.gumloop.com/settings/organization/app-policies)                                                       |
| User         | A single user                               | API only                                                                                                                                       |
| Agent        | A single AI agent (App Rules only)          | Agent config panel → app detail → **Rules** tab, or via [agent chat](/enterprise-features/app-policies/app-rules#agent-created-rules-via-chat) |

The target is set when the policy is created. Org-wide policies cover all users
in your organization; more-specific policies layer on top of them. See
[How overlapping rules interact](/enterprise-features/app-policies/app-rules#how-overlapping-rules-interact)
for how rules at different scopes stack.

## Enabled vs. disabled

Every policy has an **Enabled** toggle. Only enabled policies are enforced.
Disabling a policy pauses it without deleting it, so you can re-enable it later
without re-doing the configuration.

## Fail-closed by default

If Gumloop can't evaluate a policy for any reason — a CEL expression errors
out, a scope check fails, an unknown action shows up — the request is
**denied**, not allowed. This keeps a misconfigured policy from silently
letting traffic through. The evaluation errors are still attached to the
call so admins can see what went wrong.

## What end users see when a policy blocks them

* **App Rules:** the tool call fails with a message that depends on the rule's
  target scope:
  * *Organization* rules: *"This action has been restricted by your organization's security policy."*
  * *Agent* rules: *"This action has been restricted by a rule configured for this agent."*
  * *User* rules: *"This action has been restricted by a user-level security rule."*
* **Domain Restrictions:** if someone tries to connect an account whose email
  domain isn't on the allowlist, the OAuth flow ends with an error explaining
  which domain is required.
* **App Claims:** if someone outside your organization tries to connect to a
  provider workspace you've claimed, their connection is rejected with a
  message saying the workspace is claimed by another organization.

Only admins see which specific policy blocked a call. End users only see the
fact that it was blocked.

## Related

<CardGroup cols={2}>
  <Card title="Custom Roles" icon="user-shield" href="/enterprise-features/user_groups">
    Grant granular permissions to non-admin users.
  </Card>

  <Card title="Audit Logs" icon="clipboard-list" href="/enterprise-features/audit_logging">
    Track every policy create, update, enable/disable, and enforcement event.
  </Card>
</CardGroup>
