> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Organization Insights

> Dashboards and an analytics agent for adoption, credit spend, and outcomes across your organization

Insights is the reporting home for your organization. It combines **prebuilt dashboards** for adoption and credit spend with an **analytics agent** you can ask about that same usage data in plain language — both on the same page at [Settings > Organization > Insights](https://www.gumloop.com/settings/organization/insights).

<Frame caption="The Overview tab: a plain-language digest, credit spend and volume, and leaderboards for agents, models, and workflows">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-overview.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=2161794dd45e4bc76c283fc565eb2da3" alt="Insights Overview tab showing a credit usage digest, credit spend and volume chart, and top agents, models, and workflows" width="720" data-path="images/insights/insights-overview.png" />
</Frame>

## At a glance

<CardGroup cols={2}>
  <Card title="Dashboards" icon="chart-line">
    Nine tabs covering credits, agents, teams, models, skills, tasks, artifacts, connectors, and a free-form explorer
  </Card>

  <Card title="Analytics agent" icon="sparkles">
    Ask questions in natural language and get tables, charts, and CSV exports — in the app or in Slack
  </Card>

  <Card title="Impact Reports" icon="envelope">
    Schedule a weekly or monthly digest to email and Slack
  </Card>

  <Card title="Drill-downs" icon="magnifying-glass">
    Click any agent, team, model, task, or connector to see the detail behind the number
  </Card>
</CardGroup>

**Who can see it**: Insights is an Enterprise feature, open to members with the **Admin**, **Manager**, or **Analytics** [organization role](/core-concepts/organization_user_roles). See the [FAQ](#faq) for what each role can see and how to grant access.

### Where to start

<Steps>
  <Step title="Set your date range">
    Everything on the page — every KPI, chart, and table — follows the range in the toolbar. It defaults to the last 30 days.
  </Step>

  <Step title="Skim the Overview">
    Read the digest at the top for the headline number, then check the spend trend and the leaderboards for what drove it.
  </Step>

  <Step title="Drill into the tab that explains it">
    Agents, Teams, and Models explain cost; Skills, Tasks, Artifacts, and Connectors explain adoption. Select any row to see the detail behind it.
  </Step>

  <Step title="Ask the agent anything the tabs don't answer">
    Open the **Agent** rail for ad-hoc questions and exports, or use **Explorer** to slice usage yourself.
  </Step>

  <Step title="Put it on a schedule">
    Set up an [Impact Report](#impact-reports) so the summary arrives in email or Slack instead of you checking the page.
  </Step>
</Steps>

## Page anatomy

Every tab shares the same controls at the top of the page:

| Control           | What it does                                                                                                                                                                            |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Date range**    | Sets the period for every metric on the page. Defaults to the last 30 days; other presets are Today, Last 7 days, Month to date, Last 3 months, and Last month, or pick a custom range. |
| **Impact Report** | Opens the scheduled digest settings for the organization. See [Impact Reports](#impact-reports).                                                                                        |
| **Agent**         | Opens the analytics agent in a rail beside the dashboard. See [Ask the analytics agent](#ask-the-analytics-agent).                                                                      |
| **Tabs**          | Overview, Agents, Teams, Models, Skills, Tasks, Artifacts, Connectors, Explorer.                                                                                                        |

<Frame caption="The toolbar: tabs on the left, date range, Impact Report, and the Agent button on the right">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-toolbar.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=c66dc5db2eec55892e39c004213ea681" alt="Insights header showing the tab bar, the credit digest, the Last 30 days date range, the Impact Report button, and the Agent button" width="720" data-path="images/insights/insights-toolbar.png" />
</Frame>

<Tip>The tab, date range, and any selected agent or team live in the URL, so you can bookmark or share the exact view you are looking at.</Tip>

***

## The tabs

### Overview

The starting point: how much you spent, where it went, and who drove it.

* A plain-language digest of credits spent in the period, with the models that consumed most of it
* **Credit spend and volume** over time, with an optional **compare to previous period** toggle
* Leaderboards for **top agents**, **top models**, and **top workflows**
* A per-member table of agent tasks, workflow runs, and credits

<Frame caption="Organization Usage on the Overview tab: activity and credits per member">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-overview-usage.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=8622d34ba1bf69dd9b0335d8a116d25a" alt="Organization Usage table listing members with agent tasks, credits per task, workflow runs, credits per workflow, and total credits" width="720" data-path="images/insights/insights-overview-usage.png" />
</Frame>

### Agents

Every agent in the organization with its usage and cost side by side — team, owner, model, tasks, credits, credits per task, how many members use it, and when it was last active. Search and filter to narrow the list.

<Frame caption="The Agents tab ranks every agent by usage and credit cost">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-agents.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=6389ae7aa93b2e4f1002250d10cba312" alt="Insights Agents tab listing agents with team, owner, model, tasks, credits, credits per task, and last active" width="720" data-path="images/insights/insights-agents.png" />
</Frame>

Select an agent to open its detail view: headline metrics, a usage and credit trend, and breakdowns of its tasks, triggers, models, and members.

<Frame caption="Agent detail: trend, tasks, triggers, models, and members for a single agent">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-agent-detail.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=ed44e68fc9f4b8004d27ce97d6a805d6" alt="Insights agent detail view showing credits, tasks, credits per task, members, and a usage trend chart" width="720" data-path="images/insights/insights-agent-detail.png" />
</Frame>

### Teams

The same view one level up. Compare [teams](/core-concepts/teams) by members, agents, tasks, credits, and credits per task to see which parts of the org are getting value.

<Frame caption="The Teams tab compares teams by members, agents, tasks, and credit spend">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-teams.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=2523dd1f6ad6a4463a497b2bed7bf750" alt="Insights Teams tab listing teams with members, agents, tasks, credits per task, and credits" width="720" data-path="images/insights/insights-teams.png" />
</Frame>

Select a team to drill into its agents, tasks, triggers, models, and members.

<Frame caption="Team detail: the agents, tasks, and members behind a team's usage">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-team-detail.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=744af13b0aabfd4626e5135eefa77cb4" alt="Insights team detail view showing team credits, tasks, agents, members, and a usage trend" width="720" data-path="images/insights/insights-team-detail.png" />
</Frame>

### Models

Where model spend actually goes: your top provider and model, the model mix over time, spend by model and by provider, a full model breakdown table, and a **cost calculator** for estimating the effect of switching models.

<Frame caption="The Models tab breaks credit spend down by model and provider">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-models.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=cf611cbcdf91be8f4010693478a39672" alt="Insights Models tab showing top provider and model, model mix over time, spend by model, and spend by provider" width="720" data-path="images/insights/insights-models.png" />
</Frame>

<Tip>Pair this with [AI Model Control](/enterprise-features/ai_model_control) when you want to steer the org toward cheaper models.</Tip>

### Skills

[Skill](/core-concepts/skills) adoption across the org: **skills used** (and total uses), **used by 2+ members**, **active members**, and **never used** — plus adoption over time, your **top builders**, and a searchable per-skill table with team, owner, how many members use it, activity, and last used.

<Frame caption="The Skills tab: adoption over time, top builders, and per-skill usage">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-skills.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=9d7264802cf67afa468787b25018dbaf" alt="Insights Skills tab showing skills used, used by 2+ members, active members, never used, an adoption over time chart, top builders, and a skills table" width="720" data-path="images/insights/insights-skills.png" />
</Frame>

### Tasks

What agents actually worked on. KPI cards for tasks, members, triggered tasks, and failure rate, a **where work happens** trend by channel, and a **source** ranking. Four views:

| View               | Shows                                                                                                             |
| ------------------ | ----------------------------------------------------------------------------------------------------------------- |
| **Triggers**       | Every [trigger](/core-concepts/agent_triggers) with its type, agent, owner, status, tasks, and credits            |
| **History**        | Individual task runs                                                                                              |
| **Slack channels** | Task volume per Slack channel                                                                                     |
| **Slack apps**     | [Slack apps](/core-concepts/custom_slack_app) deployed from your agents, who deployed them, and their task volume |

<Frame caption="The Tasks tab: where work reached agents, by source and channel">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-tasks.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=a59d8152002e196d554f8dfab74818fa" alt="Insights Tasks tab showing tasks, members, triggered share, failure rate, a where work happens chart, a source ranking, and a triggers table" width="720" data-path="images/insights/insights-tasks.png" />
</Frame>

<Tip>The **Source** ranking shows how work arrives — schedules, workflow nodes, incoming Slack messages, incoming email, subagents, and the API — with failure rate per source.</Tip>

### Artifacts

[Artifacts](/core-concepts/agent_artifacts) agents produced: artifact count, creators, agents, and file types, with an artifacts-over-time trend and a file-type ranking (web pages, PNG, plain text, JSON, CSV, and more). Switch between **artifacts**, **members**, and **agents** views.

<Frame caption="The Artifacts tab: files created over time, by type, agent, and member">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-artifacts.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=ed9912a29559e5e35ad90b5b3d331f81" alt="Insights Artifacts tab showing artifacts, members, agents, file types, an artifacts over time chart, a file type ranking, and an artifacts table" width="720" data-path="images/insights/insights-artifacts.png" />
</Frame>

### Connectors

Which [connectors](/core-concepts/credentials) are actually in use — members connected, active members, an activity trend, and last used per connector. Select a connector to see the members who connected it and the agents using it.

<Frame caption="The Connectors tab: adoption and activity per integration">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-connectors.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=5c89f954ed0b69611e5e8027b7cb6289" alt="Insights Connectors tab listing Slack, Gong, Google BigQuery, GitHub, Gmail and others with members, active members, activity, and last used" width="720" data-path="images/insights/insights-connectors.png" />
</Frame>

### Explorer

Free-form slicing when no tab answers your question. Choose what to **group by**, then expand a row to drill one level deeper. Each row shows its share of credit spend.

| Group by       | Expand a row to see                           |
| -------------- | --------------------------------------------- |
| **Agents**     | The members running tasks on each agent       |
| **Members**    | The agents each member uses                   |
| **Workflows**  | The members running each workflow             |
| **Models**     | The agents using each model                   |
| **Connectors** | The members who have connected each connector |

<Frame caption="Explorer: group by any dimension, then expand a row to drill in">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-explorer.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=bd9e91198d04aa82b3a842864007a687" alt="Insights Explorer tab grouped by agents, showing credit share per agent with expandable rows" width="720" data-path="images/insights/insights-explorer.png" />
</Frame>

***

## Ask the analytics agent

Click **Agent** to open the analytics agent next to your dashboard. Ask a question in natural language, or start from a suggested prompt:

<Frame caption="The Agent button in the toolbar opens the analytics agent">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-agent-button.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=433aa171564839696974c3e5ef29e60b" alt="Impact Report and Agent buttons in the Insights toolbar, with a cursor clicking Agent" width="280" data-path="images/insights/insights-agent-button.png" />
</Frame>

* **Who are my top 3 most active members this week?**
* **What are the most used agents this week?**
* **What are the most used MCP servers this week?**

The agent queries your organization's data and answers with tables, charts, or CSV exports. Generated files open in a new tab.

<Frame caption="The agent answers in the rail, showing the steps it ran and the result as a table">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-agent-answer.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=e531545f66e996b39d3cf994d34b13c9" alt="Analytics agent answering who are my top 3 most active members this week with a table of workflow runs and agent chats per member" width="520" data-path="images/insights/insights-agent-answer.png" />
</Frame>

**In the rail you can**:

* Expand the rail for a wider chat, or collapse it to get the full dashboard back
* Start a new chat, or reopen a previous one from the chat history popover
* Turn on **incognito** for a chat that is not saved to history (Enterprise plans; an admin can disable it for the organization)
* On mobile, the agent opens as a full-screen chat instead of a rail

<Info>Chat history follows your access to Insights. If the analytics role is removed from a member, they also lose access to their past analytics conversations.</Info>

### In Slack

The same agent is available in [Slack](/core-concepts/agents_slack), so you can ask questions where your team already works.

<Steps>
  <Step title="Add the Gumloop bot to your channel">
    Invite the Gumloop bot to the Slack channel where you want to use analytics.
  </Step>

  <Step title="Enable the analytics agent">
    Type `/gummie add analytics` in the channel to activate the analytics agent.

    <Frame>
      <img src="https://mintcdn.com/agenthub/nBp6u3aoECCxWTdm/images/slack-gummie-add-analytics.png?fit=max&auto=format&n=nBp6u3aoECCxWTdm&q=85&s=406cef1d4a047aa46a714d948129ebc2" alt="Running /gummie add analytics in Slack" width="520" data-path="images/slack-gummie-add-analytics.png" />
    </Frame>
  </Step>

  <Step title="Ask your questions">
    Mention **@Gumloop** and ask your question. The agent replies in the thread.

    <Frame>
      <img src="https://mintcdn.com/agenthub/nBp6u3aoECCxWTdm/images/slack-analytics-response.png?fit=max&auto=format&n=nBp6u3aoECCxWTdm&q=85&s=27713a7420e7a06d252760452b52ba04" alt="Analytics agent responding to a query in Slack" width="520" data-path="images/slack-analytics-response.png" />
    </Frame>
  </Step>
</Steps>

### What you can ask about

| Data                    | What it covers                                                                                               |
| ----------------------- | ------------------------------------------------------------------------------------------------------------ |
| **Workflow runs**       | Run history, credit costs, execution counts, completion timestamps                                           |
| **Agent chats**         | Chat sessions with agents, credit costs per chat, chat volume over time                                      |
| **Agents**              | Agent names, descriptions, models used, tools configured, creator info                                       |
| **Workflows**           | Workflow names, descriptions, creator info                                                                   |
| **Members**             | Member emails and activity across your organization                                                          |
| **MCP servers**         | MCP server usage across your organization                                                                    |
| **Triggers**            | Trigger configurations, types (scheduled, email, Slack, webhooks, polling), status                           |
| **Skills**              | Skill names, descriptions, usage counts, which agents have them attached                                     |
| **Skill usage**         | Per-event skill usage log with timestamps, actions (view, use, edit), and which agent or member triggered it |
| **Files**               | Files produced by agents, filenames, scopes, creation timestamps                                             |
| **Evaluations**         | Chat quality evaluations: grade, sentiment, goal completion, and per-agent breakdowns                        |
| **Evaluation settings** | Which agents have evaluations enabled, plus model, frequency, and language                                   |
| **Knowledge sources**   | Connected Company Brain sources: type, status, and scope (personal, team, organization)                      |
| **Credits**             | Authoritative credit-spend ledger by category, member, team, and model charged                               |

### Example questions

<Tabs>
  <Tab title="Credits & Usage">
    ```text theme={"dark"}
    How many credits has our organization used in the last 30 days?
    Break it down by member.
    ```

    ```text theme={"dark"}
    Show me daily credit consumption for the past 3 months as a chart.
    ```

    ```text theme={"dark"}
    Break down credit spend by category for the last 30 days.
    ```

    ```text theme={"dark"}
    Which members have been most active in the last 7 days?
    Show their workflow runs and agent chats separately.
    ```
  </Tab>

  <Tab title="Agents & Workflows">
    ```text theme={"dark"}
    What are our top 10 most-run workflows this month? Show credit cost for each.
    ```

    ```text theme={"dark"}
    How many agent chats happened last week? Which agents are most popular?
    ```

    ```text theme={"dark"}
    What are the most used MCP servers this week?
    ```
  </Tab>

  <Tab title="Triggers">
    ```text theme={"dark"}
    How many active triggers do we have? Break them down by type.
    ```

    ```text theme={"dark"}
    Which triggers fire most frequently this month?
    ```

    ```text theme={"dark"}
    Show me all Slack-based triggers and which agents they belong to.
    ```
  </Tab>

  <Tab title="Skills">
    ```text theme={"dark"}
    What are our most-used skills in the last 30 days?
    ```

    ```text theme={"dark"}
    Which agents have the most skills attached?
    ```

    ```text theme={"dark"}
    Show me skill usage trends over the past month, grouped by action type.
    ```
  </Tab>

  <Tab title="Files">
    ```text theme={"dark"}
    How many files were created by agents this week?
    ```

    ```text theme={"dark"}
    Which agents produce the most files? Show the top 5.
    ```

    ```text theme={"dark"}
    Show me file creation volume per day for the last 30 days.
    ```
  </Tab>

  <Tab title="Evaluations">
    ```text theme={"dark"}
    How many agent chats were graded needs review or needs attention in the last 30 days?
    ```

    ```text theme={"dark"}
    Which agents have the lowest evaluation success rate this month?
    ```

    ```text theme={"dark"}
    How many agents have evaluations enabled? Break it down by model.
    ```
  </Tab>

  <Tab title="Knowledge Sources">
    ```text theme={"dark"}
    How many knowledge sources are connected across the organization?
    ```

    ```text theme={"dark"}
    Break down our knowledge sources by type and status.
    ```

    ```text theme={"dark"}
    Which knowledge sources are currently in an error state?
    ```
  </Tab>
</Tabs>

***

## Impact Reports

Rather than checking the dashboard, have it delivered. Click **Impact Report** in the Insights toolbar to schedule a recurring summary of your organization's agent activity.

| Setting            | Options                                                   |
| ------------------ | --------------------------------------------------------- |
| **Cadence**        | Weekly or monthly                                         |
| **Email**          | Any number of recipient addresses                         |
| **Slack**          | Channels, plus direct messages to specific members        |
| **Send me a test** | Sends the report to you immediately so you can preview it |

Only Slack channels the Gumloop bot has joined can be selected. Use **Disable report** in the same panel to stop delivery.

Reports cover the most recently completed period (weeks and months close in UTC) and go out after it closes — emails arrive at 10:00 in each recipient's local time where that is known. Each report's numbers are snapshotted when it is generated, so a retry delivers the same figures.

<Frame caption="Impact Report settings: cadence plus email and Slack delivery">
  <img src="https://mintcdn.com/agenthub/TvZO4P_6e60Hlu7l/images/insights/insights-impact-report.png?fit=max&auto=format&n=TvZO4P_6e60Hlu7l&q=85&s=912289c463454e995eb5885d72e7b29c" alt="Impact Report panel with weekly frequency, email recipients, and Slack channels or members, plus a Disable report button" width="360" data-path="images/insights/insights-impact-report.png" />
</Frame>

<Info>
  Anyone who can open Insights can view the report settings; **Admins**, **Managers**, and the **Analytics** role can
  enable, edit, and test-send it. The written summary in each report is generated by an AI model and is billed to your
  organization as AI utility credits.
</Info>

## Per-agent Insights

Every [agent](/core-concepts/agents) has its own Insights view at **Agent > Insights**, for owners who only care about their own agent. It uses the same date range picker and shows:

* Headline KPIs and a credit spend and volume chart, with the previous period for comparison
* Breakdowns by task source, skill, artifact type, Slack channel, member, and model
* Its own Impact Report schedule, independent of the organization-wide one

## FAQ

<AccordionGroup>
  <Accordion title="Who can see the Insights page?">
    Access comes from the [organization role](/core-concepts/organization_user_roles) a member holds, assigned under
    [Settings > Organization > Members](https://www.gumloop.com/settings/organization/members). **Admin**, **Manager**,
    and the **Analytics** role can open Insights. **Member**, **Security**, and **Developer** cannot — the page is not in
    their navigation.

    Roles are additive, so if someone needs reporting and nothing else, add **Analytics** to their existing Member role
    rather than promoting them to Manager.
  </Accordion>

  <Accordion title="Do I need Enterprise?">
    Yes. Insights and Impact Reports are Enterprise features — on other plans the analytics roles cannot grant access to
    them.
  </Accordion>

  <Accordion title="Whose data does each person see?">
    Everyone who can open Insights sees the same organization-wide dashboards — the tabs are not filtered per person.

    The analytics agent is different: it answers across the whole organization only for **Admins**. For a **Manager** or
    the **Analytics** role, the agent answers about that person's own activity, because the scope is applied when the
    query is built rather than filtered afterwards. Use the dashboards and Explorer for organization-wide numbers.
  </Accordion>

  <Accordion title="Who can set up an Impact Report?">
    **Admin**, **Manager**, and the **Analytics** role can enable, edit, and test-send the organization's Impact Report.
    Anyone who can open Insights can see the current settings.
  </Accordion>

  <Accordion title="How fresh are the numbers?">
    Completed days come from a rollup that refreshes hourly, and today's activity is read live, so the current day is
    always current. Recent closed days are recomputed for a few days afterwards to pick up late-arriving records, so a
    yesterday figure can still move slightly.

    Days are counted in **UTC** — for both the dashboards and the periods Impact Reports cover — so an Insights "day"
    may not line up with your local day.
  </Accordion>

  <Accordion title="Does using Insights cost credits?">
    Browsing the dashboards costs nothing. Questions you ask the analytics agent are charged on the amount of data the
    query scans, and the written summary in each Impact Report is billed as AI utility credits. See
    [Credits](/core-concepts/credits) for how spend is tracked.

    The agent keeps its own cost low by aggregating instead of returning raw rows and, when your question doesn't name a
    period, limiting time-series tables to the last 90 days. To look further back, state the date range in your question
    — that default applies only to the agent, never to the dashboards, whose range you set in the toolbar.
  </Accordion>

  <Accordion title="Can the agent reach data it shouldn't?">
    Every query is scoped to your organization and built against a fixed set of analytics tables and columns — there is
    no arbitrary SQL, values are passed as query parameters rather than pasted into the SQL text, and the organization
    and user filters are part of the query itself. Asking the agent to ignore those rules, or to role-play around them,
    does not change what it can read.
  </Accordion>
</AccordionGroup>

## Related resources

<CardGroup cols={2}>
  <Card title="Usage Data Export" icon="file-export" href="/enterprise-features/organization_data_export">
    Export raw usage data as CSV for external analysis
  </Card>

  <Card title="AI Model Control" icon="sliders" href="/enterprise-features/ai_model_control">
    Manage which AI models are available in your organization
  </Card>

  <Card title="Custom Roles" icon="user-shield" href="/enterprise-features/user_groups">
    Configure granular permissions and access controls
  </Card>

  <Card title="Audit Logging" icon="clipboard-list" href="/enterprise-features/audit_logging">
    Track user actions and system events for compliance
  </Card>
</CardGroup>
