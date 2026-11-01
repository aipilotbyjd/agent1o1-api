> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Credits

> How Gumloop bills agent chats and workflow runs

Credits are the currency that powers Gumloop. Every agent conversation spends credits based on the AI model it uses, the tools it calls, and how long it runs.

<Info>
  Agent credit costs are **variable**. The same agent might cost 2 credits for a quick question and 200 for a deep research task. You pay for what the agent actually does, not a fixed per-message price.
</Info>

## What a credit is worth

<CardGroup cols={2}>
  <Card title="1 credit = $0.005" icon="coins">
    \$1 buys 200 credits.
  </Card>

  <Card title="Model calls bill at cost" icon="microchip">
    Divide the model cost by \$0.005: a \$0.03 model call is 6 credits.
  </Card>
</CardGroup>

Everything else on this page is priced in credits directly. Charges are rounded up to whole credits.

## What you pay for

An agent chat is billed in real time, and its total is the sum of five things.

| Component             | What it covers                                                   | Cost                                                      |
| --------------------- | ---------------------------------------------------------------- | --------------------------------------------------------- |
| **Chat & Reasoning**  | The model reading your message, thinking, and writing a response | The model's cost, at \$0.005 per credit                   |
| **Tool Calls**        | Each tool the agent uses                                         | 1 credit per successful call, plus that tool's own charge |
| **Compute**           | Time the agent spends actively working                           | 5 credits per session-minute                              |
| **Orchestration Fee** | Running the agent loop itself                                    | 8% of the three above                                     |
| **Workflows**         | Any workflow the agent runs                                      | The workflow's full cost, added to the chat               |

<AccordionGroup>
  <Accordion title="Chat & Reasoning — the biggest part of most chats" icon="comments">
    Priced on the tokens each message uses, converted from the model's cost at \$0.005 per credit. Three things drive it:

    * **The model you pick.** Faster, smaller models cost less per token than frontier models. See [AI Models](/core-concepts/ai_models).
    * **Conversation length.** Every message carries the earlier conversation as context, so longer chats cost more per message.
    * **How many tools are connected.** Each tool adds its definition to the prompt, which adds tokens.

    One message can also trigger several AI steps: when an agent uses tools it loops — decide which tool to call, read the result, then respond — and each step is billed separately.
  </Accordion>

  <Accordion title="Tool Calls — 1 credit minimum, some tools charge more" icon="wrench">
    Every **successful** tool call costs at least 1 credit. Failed calls are not charged.

    * Reading and writing to apps like [Slack](https://www.gumloop.com/mcp/slack), [Google Sheets](https://www.gumloop.com/mcp/gsheets), and [Gmail](https://www.gumloop.com/mcp/gmail) costs that 1 credit and nothing more.
    * Tools that enrich or fetch external data, such as [Apollo](https://www.gumloop.com/mcp/apollo) lead enrichment or [Firecrawl](https://www.gumloop.com/mcp/firecrawl) web scraping, add their own charge on top.

    Each tool's [MCP catalog](https://www.gumloop.com/mcp) page lists its cost.
  </Accordion>

  <Accordion title="Compute — active processing time only" icon="clock">
    5 credits per session-minute of active processing, with a minimum of 1 credit per response.

    Waiting on you to reply costs nothing — only the time the agent spends working counts. Enterprise customers running Gumloop in their own [VPC](/enterprise-features/static_egress_ips) are not charged for compute at all, since it runs on their infrastructure.
  </Accordion>

  <Accordion title="Orchestration Fee — 8% of the rest of the run" icon="percent">
    ```text theme={"dark"}
    Orchestration Fee = 8% x (Chat & Reasoning + Compute + Tool Calls)
    ```

    A chat that spent 400 credits on Chat & Reasoning, 10 on Compute, and 15 on Tool Calls pays a 34 credit fee (8% of 425, rounded up), for 459 credits in total.

    Enterprise plans can negotiate a different orchestration fee. If your organization has a negotiated rate, that rate applies instead of the 8% default everywhere it appears on this page.
  </Accordion>

  <Accordion title="Workflows — billed at their own node costs" icon="diagram-project">
    When an agent runs a workflow, the workflow's cost is added to the chat as a separate line. Workflows are billed on node costs alone — no compute, no orchestration fee. See [Workflow Credits](#workflow-credits).
  </Accordion>
</AccordionGroup>

### Example: researching a lead

You ask an agent to research a sales lead with Apollo, then summarize whether they are a good fit:

| What the agent does                                                                | Credit type                                              |
| ---------------------------------------------------------------------------------- | -------------------------------------------------------- |
| Pulls the lead's data with [Apollo](https://www.gumloop.com/mcp/apollo) enrichment | **Tool Call** — 1 credit plus Apollo's enrichment charge |
| Reads that data and writes the summary                                             | **Chat & Reasoning** — your model's tokens               |
| Spends \~40 seconds of active processing                                           | **Compute** — 3 credits                                  |
| The run itself                                                                     | **Orchestration Fee** — 8% of the three above            |

Had the agent only read a [Google Sheet](https://www.gumloop.com/mcp/gsheets) and summarized it, the tool side would be just the 1 base credit, since Google Sheets charges nothing extra.

<Tip>To lower this chat's cost, run the agent on a cheaper model or add your own API key (BYOK). Both reduce the Chat & Reasoning portion. The Apollo charge stays the same, since it is a third-party cost passed straight through.</Tip>

## Plans and included credits

| Plan           | Price        | Included credits                                                                                |
| -------------- | ------------ | ----------------------------------------------------------------------------------------------- |
| **Pro**        | \$37 / month | **20,000 credits / month** — 7,400 credits at the \$0.005 list price, plus 12,600 bonus credits |
| **Enterprise** | Custom       | Custom, and unused credits **roll over**                                                        |

Every new account starts with a **14-day free trial of Pro**. The trial requires a card, is a one-time offer per customer, and rolls into a paid Pro subscription when it ends unless you cancel first.

<Warning>**There is no free plan.** Accounts created on the old free tier keep whatever credit balance they had left, but that balance no longer renews each month. To keep running agents, start a Pro trial or subscribe from the [pricing page](https://www.gumloop.com/pricing).</Warning>

<Warning>**Credits don't roll over** month to month, except on Enterprise plans.</Warning>

### Running out of credits

Enable **credit overage** on your [Subscription page](https://www.gumloop.com/settings/organization/subscription) to keep running past your monthly credits, billed at **\$0.005 per credit**.

**Pro overage is always capped.** The default ceiling is **1,000,000 overage credits per billing period** (\$5,000), and you can set a lower cap on your subscription page. Agents stop once the cap is reached, so overage can never run away unbounded. Enterprise plans set their own cap, or can choose to run uncapped.

Need consistently more? Upgrade from the [pricing page](https://www.gumloop.com/pricing) or [talk to us about Enterprise](https://www.gumloop.com/contact).

### Billing questions

For anything about the money side of your account — invoices, receipts, payment methods, purchase orders and procurement paperwork, tax or VAT details, or a charge you do not recognize — email [accounting@gumloop.com](mailto:accounting@gumloop.com).

<Note>Enterprise billing — custom credit allocations, negotiated rates, annual invoicing — goes to the same address. For product or technical issues, use [support@gumloop.com](mailto:support@gumloop.com) or [open a ticket](https://portal.usepylon.com/gumloop/forms/help) instead.</Note>

## Where to see what a chat cost

The running cost of a conversation appears as a badge with a coins icon in the **chat header**, next to the agent's name, and updates live.

For the full breakdown, open the **Chat Details** panel from the chat header. It lists one row per credit type the chat was actually charged for — **Chat & Reasoning**, **Tool Calls**, **Workflows**, **Compute**, **Orchestration Fee**, **Evaluation & Self-Improvement**, **Subagents**, or **Other** — alongside the model and source of the chat.

<Note>Credits that were waived rather than charged (BYOK model calls, and compute for VPC organizations) still appear, struck through and labelled **Free**, so you can see what the run would have cost.</Note>

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/chat_details.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=61b91b993147ffe5cd114ce4d4887d47" alt="Chat Details panel showing source, model, participants, and a credit breakdown split into Chat and Reasoning and Tool Calls" width="420" data-path="images/agents/chat_details.webp" />
</Frame>

## Tracking usage over time

<AccordionGroup>
  <Accordion title="Insights dashboard" icon="chart-line" defaultOpen={true}>
    The [Insights dashboard](https://www.gumloop.com/settings/organization/insights) (Settings > Organization > Insights) is the best place to see credit spend across all your agents and workflows. It shows total credits spent for the selected period, a credit spend and volume chart, and leaderboards of your top agents, models, and workflows.

    The tabs let you dig deeper: **Models** breaks spend down by AI model, and **Credit Explorer** lets you slice usage.

    <Note>The Insights dashboard is an **Enterprise** feature.</Note>

    <Frame>
      <img src="https://mintcdn.com/agenthub/t6dpA6NPO3IoZB48/images/insights_dashboard.webp?fit=max&auto=format&n=t6dpA6NPO3IoZB48&q=85&s=3598321047b5e96852d521e419f168ef" alt="Insights dashboard showing total credits spent, a credit spend and volume chart, and leaderboards for top agents, models, and workflows" width="2564" height="1560" data-path="images/insights_dashboard.webp" />
    </Frame>
  </Accordion>

  <Accordion title="Credit logs" icon="table-list">
    For a transaction-level view, open [Settings > Profile > Usage & Limits](https://www.gumloop.com/settings/profile/usage-limits):

    * **Grouped view** (default): one row per agent conversation or workflow run, showing its total. Expand a row to see the individual charges inside it.
    * **Detailed view**: one row per individual charge, with the exact type and amount.

    Filter by category to focus on **Agent Chats** or **Workflow Runs**. Many rows include a **View** link that jumps to the source conversation or run. Organization admins and managers can see usage across all users at [Settings > Organization > Usage & Limits](https://www.gumloop.com/settings/organization/limits), which adds a **User** column.
  </Accordion>

  <Accordion title="Ask the analytics agent" icon="message-question">
    You can also ask about credit usage in plain language. The [analytics agent](/enterprise-features/organization_insights) answers questions like "how many credits did we spend in the last 30 days, broken down by user?" and returns tables, charts, or CSV exports. It is available on the Insights page (Settings > Organization > Insights) and in Slack.
  </Accordion>

  <Accordion title="Export" icon="file-csv">
    Export credit logs as CSV from [Settings > Organization > Data Export](https://www.gumloop.com/settings/organization/data_export) for external analysis or compliance.
  </Accordion>
</AccordionGroup>

## Reducing credit costs

* **Pick the right model.** Start with the **Recommended** preset in Agent Preferences. Use **Fastest** for simple, high-volume tasks, and only reach for **Smartest** when a task needs deep reasoning. See [AI Models](/core-concepts/ai_models).
* **Bring your own key (BYOK).** Run model calls on your own provider key and the **Chat & Reasoning** portion drops to **0 credits** (Pro plan or higher).
* **Start fresh conversations.** A new chat for a new topic avoids carrying old context that inflates token costs.
* **Stay inside the short context window.** GPT-5.6 and Grok 4.5/4.6 charge higher long-context rates once a request's input tokens pass the short-window boundary (272K and 200K), and the higher rates apply to the whole request. Picking the smaller **Context Window** in [AI Advanced Settings](/core-concepts/agents#short-vs-long-context-windows) keeps chats on those models under the boundary.
* **Connect only the tools you need.** Every tool definition adds tokens to the prompt.

<Accordion title="How BYOK works" icon="key" defaultOpen={true}>
  Gumloop runs your model calls on your key and **charges you 0 credits for them** — you pay your provider directly for the tokens instead.

  |                                                       | With BYOK                                                              |
  | ----------------------------------------------------- | ---------------------------------------------------------------------- |
  | Chat & Reasoning (agents)                             | **0 credits** — shown struck through and labelled Free in Chat Details |
  | AI nodes, image generation, transcription (workflows) | **0 credits**                                                          |
  | Tool Calls, Compute, non-AI node costs                | Charged as usual                                                       |
  | Orchestration Fee (agents)                            | **16% instead of 8%**                                                  |

  Because the model spend leaves your credit balance entirely, agent chats on BYOK carry a **16% orchestration fee instead of 8%** (your Enterprise rate plus 8 points, if you have one), calculated on what the run would have been worth. Workflows have no orchestration fee at all, so a BYOK AI node costs nothing.

  In practice BYOK is still far cheaper. Taking the chat from the example above — 400 credits of model usage, 10 of compute, 15 of tool calls:

  |                      | Standard | With BYOK |
  | -------------------- | -------- | --------- |
  | Chat & Reasoning     | 400      | 0         |
  | Compute + Tool Calls | 25       | 25        |
  | Orchestration Fee    | 34 (8%)  | 68 (16%)  |
  | **Total credits**    | **459**  | **93**    |

  You cover the model tokens directly with your provider instead.

  Requires Pro plan or higher and your own OpenAI, Anthropic, Google AI, Perplexity, SpaceXAI, or Fireworks AI account. Each key only waives the models that provider serves — a Fireworks key covers the open models Gumloop runs on Fireworks (DeepSeek V4 Flash and Pro, Kimi K3, Kimi K2.7 Code, GLM-5.2, MiniMax M3, Qwen3.8 Max).

  Add a key under **personal credentials** at [Connectors](https://www.gumloop.com/personal/connectors), or add a **shared team key** for the whole team. Enterprise admins can set organization-level keys. See [AI Models](/core-concepts/ai_models#bring-your-own-key-byok).
</Accordion>

## Credit Notification Preferences

Gumloop can email you when your organization's credit usage crosses key thresholds. Manage these on your [Subscription page](https://www.gumloop.com/settings/organization/subscription).

* **Out of Credits Notification**: an email when credits reach zero. On by default.
* **Credit Usage Notifications**: an email when usage crosses a threshold. Defaults to **75%** and **90%**. Add, remove, or reset thresholds as needed.

<Frame>
  <img src="https://mintcdn.com/agenthub/5j7_6MWAeY7rv44A/images/credit_notification_preferences.png?fit=max&auto=format&n=5j7_6MWAeY7rv44A&q=85&s=ca0b41f2d6c2134854509a2cb0e0325c" alt="Credit Notification Preferences showing Out of Credits Notification toggle and Credit Usage Notifications with configurable thresholds at 75% and 90%" width="2448" height="1678" data-path="images/credit_notification_preferences.png" />
</Frame>

<Tip>Set these up early so you have time to top up before running out.</Tip>

### Per-Chat Credit Warnings

Admins and Security role holders can also set **per-chat credit warnings** through [Custom Roles](/enterprise-features/user_groups#per-chat-credit-warnings). When a single chat's spend crosses a configured threshold (e.g. 5,000 or 10,000 credits), the agent pauses and creates an [Action Request](/core-concepts/human_in_the_loop) for approval before continuing, so no single conversation can consume an unexpected amount of credits.

***

## Workflow Credits

Workflows are billed differently from agent chats:

```text theme={"dark"}
Workflow cost = 1 credit (base) + the cost of each node that runs
```

Most nodes are free. AI nodes are the exception — like agents, they are billed by **token usage**, so a short prompt costs far less than a long-context one.

<Note>**Compute and the orchestration fee apply to agent chats only.** Workflow runs are billed on node costs alone, whether the workflow runs on its own or is called by an agent.</Note>

<AccordionGroup>
  <Accordion title="Node credit costs" icon="diagram-project" defaultOpen={true}>
    <Tabs>
      <Tab title="Free nodes (0 credits)">
        Most native Gumloop nodes cost **nothing**:

        * Text manipulation (Combine Text, Text Formatter, Find & Replace)
        * Logic (If/Else, Switch, Router)
        * Loops (For Each, Loop Mode)
        * Data transformation (Filter, Join, Split)
        * Most integrations (Google Sheets, Slack, Gmail, Airtable, Salesforce, etc.)
        * Input/Output nodes
      </Tab>

      <Tab title="AI nodes">
        AI nodes (such as Ask AI, Analyze Image, and Generate Report) are billed by **token usage**: the model you pick and how many input and output tokens the call uses. There are no fixed per-call tiers.

        * **Pick the right model.** Smaller, faster models cost less per token than frontier models. See [AI Models](/core-concepts/ai_models).
        * **Keep inputs lean.** Fewer tokens in and out means a lower cost.
        * **Bring your own key (BYOK).** Run AI node calls on your own provider key for **0 credits** (Pro plan or higher).

        <Note>**Image generation** is a flat **30 credits per image** regardless of size, quality, or model. **Audio transcription** is billed by audio length at roughly 1 to 2 credits per minute depending on the model. Both are free with BYOK.</Note>
      </Tab>

      <Tab title="Data & web nodes">
        **Data enrichment:**

        * Enrich Contact Information: 60 credits
        * Enrich Company Information: 60 credits
        * Search Companies: 30 credits
        * Email Validator: 10 credits

        **Web scraping:**

        * Web Agent Scraper: 10 credits
        * Website Crawler: 10 credits
        * Advanced Web Search: 5 credits
        * Advanced Website Scraper: 2 credits
        * Web Search: 2 credits
        * Website Scraper: 1 credit
      </Tab>

      <Tab title="Custom & code nodes">
        **Custom and MCP nodes:** 3 credits each.

        These run in isolated virtual environments for security, which incurs infrastructure costs.
      </Tab>
    </Tabs>

    <Tip>Hover over any node's '?' icon in the builder to see its credit cost.</Tip>
  </Accordion>

  <Accordion title="Example workflow costs" icon="calculator">
    | Workflow                                                   | Cost                                     |
    | ---------------------------------------------------------- | ---------------------------------------- |
    | Read Google Sheet → filter rows → send Slack message       | **1 credit** (all three nodes are free)  |
    | Read emails → categorize with an AI node → update Airtable | 1 credit + the AI node's token cost      |
    | Combine text → AI node → custom node                       | 1 + 3 credits + the AI node's token cost |
    | Read Airtable → enrich 2 contacts → update Salesforce      | **121 credits** (1 + 60 x 2)             |

    <Warning>Enrichment nodes get expensive in loops. Enriching 100 contacts costs 6,001 credits (1 + 60 x 100).</Warning>
  </Accordion>

  <Accordion title="Failed runs and loop mode" icon="triangle-exclamation">
    **Failed workflows:** if a workflow stops partway, you are only charged for the nodes that ran before the failure.

    **Loop mode:** nodes in loop mode run once per item, so multiply the node cost by the number of items (a 60 credit Enrich Contact node over 10 contacts = 600 credits).
  </Accordion>
</AccordionGroup>

## Interactive artifact costs

When you open an [interactive artifact](/core-concepts/agent_artifacts#interactive-artifacts-live-data) that pulls live data, a Python script runs in a secure sandbox. This is billed at roughly **1 credit per 55 seconds** of runtime, with a minimum of 1 credit per run. The **viewer** pays, not the creator. Most scripts finish in seconds, so the typical cost is 1 credit per load.

***

## Learn more

<CardGroup cols={3}>
  <Card title="AI Models" icon="microchip" href="/core-concepts/ai_models">
    Choose the right model and set up BYOK
  </Card>

  <Card title="Pricing plans" icon="tag" href="https://www.gumloop.com/pricing">
    Current plans, included credits, and overage
  </Card>

  <Card title="Why we moved to credits" icon="book-open" href="https://blog.gumloop.com/gumloop-credits/">
    The reasoning behind the model
  </Card>

  <Card title="Billing questions" icon="envelope" href="mailto:accounting@gumloop.com">
    Email [accounting@gumloop.com](mailto:accounting@gumloop.com) about invoices, payment, and procurement
  </Card>
</CardGroup>
