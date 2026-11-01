> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# App Rules

> Block or tag specific tool calls for third-party apps at the organization or agent level.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1189596327?h=26844f8a64" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="App policies and rules" />
</div>

App Rules let you intercept individual tool calls for any third-party app your organization uses and either **block** them or **tag** them for review. Each rule is a lightweight policy with a phase, an action, a scope, and a CEL condition that Gumloop evaluates automatically every time a tool call runs.

<CardGroup cols={2}>
  <Card title="Organization Rules" icon="building">
    Apply to **every user and agent** in your org. Manage at [App Policies settings](https://gumloop.com/settings/organization/app-policies).
  </Card>

  <Card title="Agent Rules" icon="robot">
    Apply to a **specific agent's** tool calls only. Manage via agent config or agent chat.
  </Card>
</CardGroup>

***

## Rule Scopes

<Tabs>
  <Tab title="Organization-Level">
    Organization-level rules are the default. They apply to **every user and agent** in your organization.

    ### Where to find it

    Go to **Settings → Organization → App Policies** at [gumloop.com/settings/organization/app-policies](https://gumloop.com/settings/organization/app-policies) and open the **App Rules** tab.

    <Frame>
      <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/app-policies-overview.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=55f4460c6b2c387b506fc8f4f15512da" alt="App Rules tab with Search and time range filter, Rule matches / Allowed / Tagged / Blocked stat cards, an Enforcement Activity histogram, and a Rules by Server list" style={{ maxWidth: '550px' }} width="2314" height="1632" data-path="images/enterprise-features/app-policies/app-policies-overview.png" />
    </Frame>

    ### What you see

    * **Stat cards** for the selected time range: *Rule matches*, *Allowed*, *Tagged*, *Blocked*
    * **Enforcement Activity** histogram: allowed vs. blocked tool calls over time
    * **Rules by Server**: every app with its rules listed. Toggle each rule on or off from here
    * **+ App Rule** button to create a new rule

    ### Creating a rule

    Click **+ App Rule**, pick the app, and the AI rule builder opens (see [Building a rule](#building-a-rule-with-the-ai-rule-builder) below). The rule is scoped to the entire organization by default.

    ### Active rules

    Rules appear under their app in the **Rules by Server** list with a name, description, and toggle.

    <Frame>
      <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/active-rule.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=76701ff3c1b74b94e9075af39a5ce8c3" alt="Rules by Server panel for Slack showing a rule named Block Messages to Restricted Slack Channel with a description and an enabled toggle" style={{ maxWidth: '500px' }} width="1844" height="396" data-path="images/enterprise-features/app-policies/active-rule.png" />
    </Frame>

    ### What users see when blocked

    The tool call fails with: *"This action has been restricted by your organization's security policy."*

    The user doesn't see the rule's name, its condition, or which field tripped it. Admins can see the full context in the rule's **Activity** tab.
  </Tab>

  <Tab title="Agent-Level">
    Agent-level rules target a specific agent, so they only apply to tool calls made by that agent. There are two ways to create them.

    <AccordionGroup>
      <Accordion title="Via the agent configuration panel" icon="gear" defaultOpen>
        When configuring an agent, open the detail view of any connected app. The **Rules** tab shows all rules targeting this agent for that app.

        <Frame>
          <img src="https://mintcdn.com/agenthub/9nMCqStA3hBmSDKe/images/app-rules/agent-rule-in-app.png?fit=max&auto=format&n=9nMCqStA3hBmSDKe&q=85&s=079e7ff913befed41d2d3c39f4ab3c30" alt="Agent configuration panel showing the Rules tab for the Linear app with an active rule" style={{ maxWidth: '380px' }} width="1100" height="616" data-path="images/app-rules/agent-rule-in-app.png" />
        </Frame>

        You can toggle rules on or off and click through to the rule detail sheet.
      </Accordion>

      <Accordion title="Via agent chat (agent-created rules)" icon="message">
        Agents can propose their own rules during a conversation. To enable this:

        1. Open the agent's configuration panel and find the **Abilities** section.
        2. Toggle **App Rules Creation** to **ON**.

        <Frame>
          <img src="https://mintcdn.com/agenthub/9nMCqStA3hBmSDKe/images/app-rules/agent-app-rules-creation-toggle.png?fit=max&auto=format&n=9nMCqStA3hBmSDKe&q=85&s=4beae98c24f0ee868c6afe3ce7270add" alt="Agent Abilities section showing the App Rules Creation toggle set to ON" style={{ maxWidth: '380px' }} width="1558" height="944" data-path="images/app-rules/agent-app-rules-creation-toggle.png" />
        </Frame>

        Ask the agent something like *"Create a linear app rule to never create a ticket without having at least two labels"* and it will propose a rule for your review.

        All rule mutations require your explicit approval. You'll see a proposal card with **Accept** / **Reject** buttons.

        <Frame>
          <img src="https://mintcdn.com/agenthub/9nMCqStA3hBmSDKe/images/app-rules/agent-rule-proposal-chat.png?fit=max&auto=format&n=9nMCqStA3hBmSDKe&q=85&s=63c6e35fd7ea92b39bd6c81e225fcdd0" alt="Agent chat showing a rule proposal card with the rule name, description, CEL condition, target tools, and Accept/Reject buttons" style={{ maxWidth: '500px' }} width="1620" height="902" data-path="images/app-rules/agent-rule-proposal-chat.png" />
        </Frame>
      </Accordion>
    </AccordionGroup>

    ### What the agent sees when blocked

    The tool call fails with: *"This action has been restricted by a rule configured for this agent."*

    <Info>Agent-created rules only apply to that specific agent's tool calls. They do not affect other agents, other users, or organization-wide policies.</Info>
  </Tab>
</Tabs>

***

## How a Rule Works

Every rule has four core pieces of configuration:

<Steps>
  <Step title="Phase (check_type)">
    Pick **before** to check the call before it runs, or **after** to check it once the result comes back. Use *before* to block risky actions and *after* to tag calls based on what was returned.
  </Step>

  <Step title="Action">
    Pick **block** to deny the call, or **tag** to let it through while labeling it with your rule's name for later review.
  </Step>

  <Step title="Scope">
    Limit the rule to specific **tool names** on the app (e.g. only `send_message` in Slack, or only `create_event` in Google Calendar). Leave empty to apply to every tool on that app.
  </Step>

  <Step title="Condition">
    A CEL expression that decides whether the rule fires. You have access to:

    * `args`: the arguments the caller passed to the tool
    * `tool_name`: the tool being called
    * `server_id`: the app the tool belongs to
    * `output`: the tool's return value (only in *after* rules)

    Example: `args.channel == "C05QG7RF30A"` fires whenever Slack's `send_message` targets that specific channel.
  </Step>
</Steps>

***

## Building a Rule with the AI Rule Builder

Clicking **+ App Rule** (from either scope) opens the rule builder. The left panel is a chat with the AI assistant, and the right panel shows the live rule configuration and simulation results.

<Frame>
  <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/rule-builder-chat.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=aec64cfe76fa2e361d8d25ff7e0195f3" alt="Rule builder: left side is a chat with the AI assistant, right side shows the generated Rule Configuration JSON and a Simulation tab" style={{ maxWidth: '550px' }} width="2562" height="1390" data-path="images/enterprise-features/app-policies/rule-builder-chat.png" />
</Frame>

<Steps>
  <Step title="Describe the rule in plain English">
    For example: *"Do not allow users to send messages in the general channel. Channel ID: C05QG7RF30A."*

    The assistant will ask for any missing details. You can `@mention` tools on the app to pull them into the conversation.
  </Step>

  <Step title="Review the generated configuration">
    The top-right panel shows the `check_type`, `action`, `tool_names`, and `conditions` the assistant produced.
  </Step>

  <Step title="Check the simulation">
    Every time the assistant changes the rule, it re-runs it against recent tool calls and shows the verdict for each. Confirm it catches what you want (no false negatives) and doesn't catch anything unexpected (no false positives).
  </Step>

  <Step title="Save">
    Click **Save** in the top right. The rule starts enforcing immediately once enabled.
  </Step>
</Steps>

<Tip>The assistant also accepts follow-ups like *"Change this to block before execution instead of tagging after"* or *"Expand the conditions to cover more edge cases."* You don't have to edit the JSON by hand.</Tip>

<Accordion title="Suggested prompts to get started" icon="sparkles">
  * *Help me create a new rule for this server*
  * *What types of rules can I create?*
  * *Show me examples of common security rules*
  * *Help me set up a rule to block sensitive operations*
  * *Explain how MCP rules work*
</Accordion>

***

## Editing a Rule

Opening a rule takes you back into the rule builder. The right panel has three tabs:

<CardGroup cols={3}>
  <Card title="Simulation" icon="flask">
    Re-runs the rule against recent tool calls so you can see the impact of any edit before saving.
  </Card>

  <Card title="Activity" icon="chart-line">
    Shows actual tool calls this rule has evaluated, with verdicts (allowed, tagged, blocked), the user, and the call source.
  </Card>

  <Card title="Settings" icon="gear">
    Rename, toggle enabled/disabled, view metadata, or delete the rule.
  </Card>
</CardGroup>

<Accordion title="Settings tab details" icon="gear">
  <Frame>
    <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/rule-settings.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=1e3f610745e5a1a662548aa5d7703cfc" alt="Rule Settings tab showing Name, Description, Enabled toggle, metadata, and Delete Rule button" style={{ maxWidth: '450px' }} width="1278" height="1126" data-path="images/enterprise-features/app-policies/rule-settings.png" />
  </Frame>

  On the **Settings** tab you can:

  * Rename the rule or change its description (changes save on blur)
  * Toggle **Enabled** on or off
  * See who created the rule, when, and last updated
  * **Delete** the rule (cannot be undone)
</Accordion>

***

## Enforcement Activity

Every evaluated tool call is recorded for auditing. You can view activity in two places:

* **App Rules tab** at the top of the App Policies page: enforcement across every rule in the org, with stat cards and a histogram
* **Activity tab** inside a specific rule: only that rule's history

Tool calls appear with one of three statuses:

| Status      | Meaning                                                                                                  |
| ----------- | -------------------------------------------------------------------------------------------------------- |
| **Allowed** | The call ran normally, no rule matched.                                                                  |
| **Tagged**  | The call ran, but one or more `tag`-action rules matched. Matched rule names shown in the *Rule* column. |
| **Blocked** | A `block`-action rule matched and the call was denied.                                                   |

You can click any tool call to expand it and inspect the arguments and output.

***

## How Overlapping Rules Interact

Rules at different scopes stack. When a tool call is evaluated, Gumloop checks **all** enabled rules across every scope that matches the caller:

* An **organization-wide** rule is always checked for every caller
* An **agent-scoped** rule is checked only if the caller is that specific agent

If *any* matching rule has action `block` and its condition fires, the call is blocked, regardless of what other rules say. There is no "allow" override.

<Tip>Think of it as layering: organization rules set the baseline, and agent rules add agent-specific restrictions. Each layer can only make things *more* restrictive, never less.</Tip>

***

## App Rules & Human in the Loop

App Rules are one part of a broader **Human in the Loop** system that keeps humans in control of AI agent actions. While App Rules use CEL conditions to automatically block or tag tool calls, the approval system gives you the option to **pause and ask** before a tool call executes.

### How they work together

App Rules and approval settings operate at different layers but complement each other:

| Layer                 | What it does                                                                                                                | Example                                                                    |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Approval Settings** | Simple toggles: always allow, ask each time, ask for writes/deletes, or custom per-tool. Based on the tool's risk category. | "Ask me before any Gmail write operation."                                 |
| **App Rules**         | Conditional CEL-based policies that evaluate tool call arguments at runtime. Can block or tag.                              | "Block emails to external domains" or "Tag any Slack message to #general." |

Both layers are evaluated for every tool call. Approval settings act as the baseline, and App Rules add conditional overrides on top. If an App Rule blocks a call, it is denied regardless of approval settings.

### Using App Rules for conditional approvals

A common pattern is combining **"Always allow"** approval settings with App Rules that **tag** specific calls for review. This lets most tool calls flow freely while flagging the ones that match your conditions for later auditing.

For example:

* Set Gmail approval mode to **Always allow**
* Create an App Rule that **tags** any email sent to recipients outside your company domain
* Your agent sends internal emails without interruption, while external emails are tagged and visible in the enforcement activity view

For more granular approval controls (where the agent pauses and waits for you to approve or reject), see the [Human in the Loop documentation](/core-concepts/human_in_the_loop).

<AccordionGroup>
  <Accordion title="Example: conditional approval via agent chat" icon="message">
    You can ask your agent to create an App Rule in natural language. For instance: *"Create a human approval rule for any email sent to users without the gumloop.com domain."*

    The agent translates this into a CEL condition and shows you a proposal card:

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/app-rules-creation-chat.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=f73bb0424c8c5c16f9d75295fe718d25" alt="Agent creating an approval rule for non-gumloop.com email recipients via chat, showing the CEL condition" style={{ maxWidth: '450px' }} width="1598" height="1068" data-path="images/human-in-the-loop/app-rules-creation-chat.png" />
    </Frame>

    Once approved, the rule appears in the app's config:

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/gmail-app-rules-config.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=926918136874f45431009345dead007a" alt="Gmail app configuration showing the Rules section with an active approval rule for external email recipients" style={{ maxWidth: '450px' }} width="1218" height="878" data-path="images/human-in-the-loop/gmail-app-rules-config.png" />
    </Frame>
  </Accordion>

  <Accordion title="Example: approval card in the web chat" icon="browser">
    When a tool call triggers an approval requirement, the agent pauses and shows an approval card directly in the conversation:

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/approval-card-web-email.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=406f8efe0b43aa7d623f50dbb9adc305" alt="Approval card in the web chat showing an email send request with To, Subject, and Body fields, plus Reject and Approve buttons" style={{ maxWidth: '450px' }} width="1664" height="1130" data-path="images/human-in-the-loop/approval-card-web-email.png" />
    </Frame>

    You can review the tool name, intent, and arguments, then **Approve** or **Reject**. Check **"Don't ask again for this tool"** to auto-approve future calls to the same tool.
  </Accordion>
</AccordionGroup>

<Info>To set up approval settings, the Ask Question ability, and notification channels for approvals, see the full [Human in the Loop guide](/core-concepts/human_in_the_loop).</Info>

***

## FAQ

<AccordionGroup>
  <Accordion title="Can an agent rule override an organization-wide block?">
    No. Rules only add restrictions. If an org-wide rule blocks a tool call, an agent rule cannot un-block it. To allow the call, you would need to disable or modify the org-wide rule.
  </Accordion>

  <Accordion title="Do agent rules apply when the agent runs inside a pipeline?">
    Yes. Agent-scoped rules are evaluated whenever that agent makes a tool call, regardless of whether the agent is running in a direct conversation, through Slack, or inside a pipeline.
  </Accordion>

  <Accordion title="Can I create tag rules at the agent level?">
    Yes. Both `block` and `tag` actions are supported at every scope level. Tagged calls show up in the enforcement activity views just like org-wide tagged calls.
  </Accordion>

  <Accordion title="How do App Rules differ from Human in the Loop approval settings?">
    Approval settings are simple mode-based controls (always allow, ask each time, ask for writes/deletes, or custom per-tool). They apply based on the tool's risk category.

    App Rules are conditional. They use CEL expressions to inspect the actual arguments of a tool call. For example, "only block when the email recipient is outside my domain" is something only App Rules can do. Both systems work together: approval settings set the baseline, App Rules add conditional overrides. See the [Human in the Loop guide](/core-concepts/human_in_the_loop) for the full picture.
  </Accordion>
</AccordionGroup>
