> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Agents

Agents are AI-powered assistants that use tools to solve open-ended tasks. Unlike workflows that follow a fixed path, an agent decides which tools to use and when, adapting its approach to the task in front of it.

<iframe src="https://player.vimeo.com/video/1190842603?h=2e0e018503&badge=0&autopause=0&player_id=0&app_id=58479" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" title="Building your first agent" allowFullScreen />

## What agents do

You give an agent a goal and a set of tools, and it figures out how to get there: which tools to call, in what order, and when to ask you for input.

* **Adaptive**: takes different approaches for different situations
* **Tool-driven**: uses your apps and workflows as needed
* **Conversational**: works through tasks in a back-and-forth chat
* **Context-aware**: considers your instructions, skills, and conversation history

When you give it a task, the agent analyzes the request, decides which tools to use and in what order, runs them, adapts based on the results, and asks for confirmation when your instructions call for it.

## Configure an agent in three moves

You do not need to write a long system prompt up front. The fastest way to a useful agent is to let it do real work, then have it write its own instructions.

<Steps>
  <Step title="Connect your apps">
    Open the **Connectors** section and add the integrations the agent needs (Gmail, Salesforce, Slack, and so on). The connectors you add define what the agent can see and do, so start with the two or three it actually needs.
  </Step>

  <Step title="Start chatting and give it a real task">
    Use the built-in chat to run an actual task end to end. Watch where it guesses, asks the wrong thing, or misses a step.
  </Step>

  <Step title="Ask it to write its own instructions and skills">
    Once it completes a task the way you want, tell it: *"Update your system prompt so you always do it this way"* or *"Turn this into a skill."* The agent writes its own [instructions](#self-improving-instructions) and [skills](#skills), getting you 90% of the way without hand-authoring anything.
  </Step>
</Steps>

<Tip>This loop is the whole point: correct the agent once, have it capture the correction in its prompt or a skill, and it stops making that mistake. Every conversation makes the agent better.</Tip>

## The agent builder

The configuration panel has two tabs:

* **Agent**: everything the agent is made of. **Agent Preferences** holds the model and the system prompt (its instructions), and below that sit **Triggers**, **Connectors**, **Skills**, **Subagents**, and **Abilities**.
* **Settings**: how the agent presents and behaves outside its core logic: **Personalization**, **Agent Details**, **Chat Preferences**, **Slack Preferences**, **Secrets**, and the **Danger Zone**.

The rest of this page covers each part, starting with the ones you will reach for most.

***

## Connectors

**Connectors** are the integrations your agent connects to, such as Gmail, Salesforce, Slack, Notion, and 150+ more. The connectors you add determine what the agent can access, so this is the most important part of configuration.

<Frame>
  <img src="https://mintcdn.com/agenthub/sXitjqkp0I4EVM8B/images/agents/connectors_section.png?fit=max&auto=format&n=sXitjqkp0I4EVM8B&q=85&s=7f1004c3c6adcc5cf8dc5db34224abf3" alt="Connectors section showing connected connector icons, an Add Connector row, and the AI Discovery toggle set to ON" width="440" data-path="images/agents/connectors_section.png" />
</Frame>

Click **+ Connector** to open the picker. The **All** tab lists every available connector (Gumloop-managed and your own), and the **Custom** tab filters to [custom MCP servers](/nodes/mcp/custom_mcp_servers) you have added. Pick the few your agent actually needs rather than connecting everything.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/add_app_modal.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=e56662f995b13a5b274e6ae770c76694" alt="Add a connector modal with a search box, All and Custom tabs, a Connected list with checkmarks, and an All connectors list" width="360" data-path="images/agents/add_app_modal.webp" />
</Frame>

The **AI Discovery** toggle on the Connectors header is the same setting as [Tool Discovery](#abilities) in Abilities. It lets the agent load tool schemas on demand instead of all at once, which keeps context lean when you connect many connectors.

### Account selection

Click any connected app to open its detail view. The **Account** selector controls which login the agent uses to call that app's tools.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/app_detail_account.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=47bb846535923da409da176b00c11145" alt="Google Docs app detail view showing Activity, Tools Enabled, and Rules tiles, with an Account selector offering Use Personal Default and Use Specific Account" width="460" data-path="images/agents/app_detail_account.webp" />
</Frame>

* **Use Personal Default**: each person who runs the agent uses their own default account. This is the default.
* **Use Specific Account**: pin one account for the agent, useful when you have multiple accounts for the same service.
* **Use Team Default** (team agents only): everyone on the team uses the same shared account.

<Card title="Credentials" icon="key" href="/core-concepts/credentials#in-agents" arrow>
  How agents authenticate, personal vs. team credentials, and the account selection flow.
</Card>

### Tool Management and approvals

The same detail view has a **Tool Management** panel that controls which of an app's tools the agent can call, and which ones need your approval first. Tools are grouped into **Read-only tools** and **Write/delete tools**.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/tool_management.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=d2fabb9e3a91f2f6ca7debcbc6da85d5" alt="Tool Management panel with an approval preset dropdown, read-only and write/delete tool groups, and per-tool allow, ask, and deny controls" width="460" data-path="images/agents/tool_management.webp" />
</Frame>

Set a preset for the whole app, or control each tool individually:

| Preset                     | Behavior                                                                              |
| -------------------------- | ------------------------------------------------------------------------------------- |
| **Always allow**           | Every tool runs without asking.                                                       |
| **Ask each time**          | The agent pauses for approval before any tool call.                                   |
| **Ask for writes/deletes** | Read-only tools run freely; write, delete, and unknown-risk tools pause for approval. |
| **Custom**                 | Set the mode per tool: allow always, ask each time, or never allow (deny).            |

When a tool needs approval, the agent pauses mid-task and shows you an approval card. This is the human-in-the-loop guardrail for sensitive actions.

<Card title="Human in the Loop" icon="hand" href="/core-concepts/human_in_the_loop" arrow>
  How approvals work, what the agent shows you, and how to tune when it pauses.
</Card>

***

## Skills

**Skills** teach your agent how to do specific work *your way*: multi-step processes, templates, and domain knowledge that load only when relevant. You rarely write one by hand. The easiest path is to prompt the agent in a chat: get it to do a task well, then say *"turn this into a skill."* When it gets something wrong later, give it feedback in the chat and it updates the skill. The **AI Skill Editing** toggle is what lets it create and edit skills on its own.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/skills_menu.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=dab85b1a7a16e6407652e1abb33658cb" alt="Skills section with AI Skill Editing toggle and the Skill menu showing Create With AI, Upload Files, Write Skill Instructions, and Add Existing Skill" width="460" data-path="images/agents/skills_menu.webp" />
</Frame>

You can also add a skill yourself with **+ Skill**:

* **Create With AI**: describe the skill and the agent generates it.
* **Upload Files**: turn a document or `.zip` into a skill.
* **Write Skill Instructions**: enter a name and description yourself.
* **Add Existing Skill**: attach a skill you already created.

<Card title="Agent Skills Guide" icon="book-open" href="/core-concepts/skills" arrow>
  Create skills, attach them to agents, and build a library that improves over time.
</Card>

***

## Knowledge Sources

Give your agent a searchable memory of what your company knows. In the **Knowledge Sources** section, attach [Brain](/core-concepts/brain) sources (Google Drive, Notion, Slack, GitHub, Confluence, or uploaded files) so the agent answers from your real documents and messages, with citations, instead of guessing.

<Frame>
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/agent-knowledge-sources.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=dfdac584a55129348eb0ead7b817e81f" alt="Knowledge Sources section in agent configuration with the prompt Give your agent knowledge: Attach Company Brain sources so this agent can search them, down to the exact files or folders within" width="460" data-path="images/brain/agent-knowledge-sources.webp" />
</Frame>

Click **+ Source** to attach whole sources, or drill into the exact files and folders that matter. When you ask about internal knowledge, the agent searches automatically (shown as *Searching Company Brain* in chat) and can open a full document for more context.

<Card title="Brain Guide" icon="brain" href="/core-concepts/brain" arrow>
  Connect knowledge sources, keep them synced, and give your agents company knowledge to search.
</Card>

***

## Triggers

Agents can run on their own, without you starting the chat. The **Triggers** header shows **AI Managed**: when on, the agent can create, edit, and manage its own triggers and schedules during a conversation.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/triggers_menu.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=ef33a52fd88ef7227b76cf749180623e" alt="Triggers section with AI Managed toggle and the Trigger menu showing Create With AI, App Trigger, Scheduled Trigger, and One-Time Trigger" width="460" data-path="images/agents/triggers_menu.webp" />
</Frame>

Click **+ Trigger** to add one of four kinds:

| Option                | What it does                                                                                          |
| --------------------- | ----------------------------------------------------------------------------------------------------- |
| **App Trigger**       | Runs the agent when an event happens in another app (new email, new Slack message, a record changes). |
| **Scheduled Trigger** | Runs the agent on a recurring schedule, for example every weekday at 9 AM.                            |
| **One-Time Trigger**  | Runs the agent once at a specific time.                                                               |
| **Create With AI**    | Describe what you want and the agent builds a custom trigger for you.                                 |

<Card title="Agent Triggers Guide" icon="bolt" href="/core-concepts/agent_triggers" arrow>
  Set up app and scheduled triggers, write prompt templates, and manage active triggers.
</Card>

***

## Subagents

Subagents let your agent delegate to other agents. Instead of doing everything in one conversation, it can spin up focused helpers that work in parallel, then collect the results and continue.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1189083032?h=d2642f4b8e&badge=0&autopause=0&player_id=0&app_id=58479" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" title="Subagents" allowFullScreen />
</div>

There are two ways an agent delegates, both through the **invoke\_agent** tool:

<Tabs>
  <Tab title="Self-cloning">
    The agent clones itself, keeping the same tools and instructions. Useful for parallel work: spawn several clones, each handling a different subtask. The clone shows up as **"(Me)"** in the list and is enabled by default. Clones cannot clone themselves again (depth limit of 1), and you can scope a clone to a subset of apps.
  </Tab>

  <Tab title="Invoking other agents">
    The agent calls a different, specialized agent by name. Add agents to the **Subagents** list to allow this. Cross-agent chains are not depth-limited, so a coordinator can delegate to a chain of purpose-built agents.
  </Tab>
</Tabs>

Each subagent runs as its own conversation with its own context and sandbox, visible in your chat history. How many can run at once depends on your subscription tier.

Behind the scenes, subagents run as queued background tasks with their own time budget (about half the parent's). For batch invocations a shared progress board tracks each one, and the parent can hand specific files to a subagent before it starts. The parent reads each subagent's results when it finishes.

***

## Abilities

**Abilities** are the agent's built-in capabilities. Most are on by default, and each can be toggled from the **Abilities** section.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/abilities_section.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=d81ccc270564599f8127fe79f1c3aa2f" alt="Abilities section listing Web Search, Web Fetch, Image Generation, Search Past Conversations, Ask Question, Tool Discovery, and App Rules Creation, with a Workflow button" width="440" data-path="images/agents/abilities_section.webp" />
</Frame>

| Ability                       | Default            | What it does                                                                                                                                                                      |
| ----------------------------- | ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Web Search**                | On                 | Searches the web for current information. Choose the provider (Exa, Parallel, Firecrawl, or the model's native search).                                                           |
| **Web Fetch**                 | On                 | Reads the content of a specific URL. Choose the provider (Firecrawl, Parallel, Exa, or Gumloop).                                                                                  |
| **Image Generation**          | On                 | Creates images from text prompts.                                                                                                                                                 |
| **Search Past Conversations** | On                 | Searches and retrieves earlier conversations for context. The backbone of an agent that learns over time.                                                                         |
| **Ask Question**              | On                 | Lets the agent pause and ask you a structured, multiple-choice question when it needs a decision.                                                                                 |
| **Tool Discovery**            | Auto               | Loads tool schemas on demand. In **Auto**, the agent loads tools directly when they are small (roughly 10% of context) and switches to on-demand discovery when they grow larger. |
| **App Rules Creation**        | On where available | Lets the agent propose [App Rules](/enterprise-features/app-policies/app-rules) for your review during a chat. Enabled by default on plans that include App Rules.                |

Use **+ Workflow** to attach a Gumloop workflow as a tool. The agent decides when to call it, fills in the inputs, and reads the outputs.

<Tip>When building workflows for agents to call, use clear Input and Output nodes, descriptive names ("Enrich Lead from LinkedIn Profile", not "Workflow 1"), and keep each one focused on a single job.</Tip>

***

## Code sandbox

Every agent has a built-in **code sandbox** for running Python and shell commands in a secure, isolated environment. It is always on, so the agent can analyze data, generate files, and run scripts automatically. You do not need to configure anything. The sandbox persists installed packages and workspace files across conversations and ships with 80+ Python packages preinstalled.

<Card title="Code Sandbox & Secrets" icon="terminal" href="/core-concepts/agent_sandbox_and_secrets" arrow>
  Sandbox capabilities, persistence, execution limits, preinstalled packages, and Agent Secrets.
</Card>

***

## Evaluations and reflections

Two features help you measure and improve agents over time.

<CardGroup cols={2}>
  <Card title="Evaluations" icon="clipboard-check" href="/core-concepts/evaluations">
    Define test cases and grade your agent's responses so you can catch regressions and compare changes before they ship.
  </Card>

  <Card title="Reflections" icon="brain" href="/core-concepts/reflections">
    Let your agent review its own recent conversations on a schedule and propose improvements to its instructions and skills.
  </Card>
</CardGroup>

Agents can also produce rich outputs such as documents, spreadsheets, and interactive charts. See [Agent Artifacts](/core-concepts/agent_artifacts) for how those are generated, shared, and versioned.

***

## Embedding agents in workflows

Creating an agent is the 0 to 1. Embedding it in a workflow is the 1 to 100. The **Agent node** runs any of your configured agents inside a workflow, so you can chain it with other nodes and run it in batch.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1148777926?h=fb7fe4ffbb" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" allowFullScreen />
</div>

| Capability                       | Standalone Agent | Agent in Workflow |
| -------------------------------- | ---------------- | ----------------- |
| **Manual chat**                  | Yes              | Yes               |
| **Scheduled and event triggers** | Yes              | Yes               |
| **Chain with other nodes**       | No               | Yes               |

<Card title="Agent Node" icon="diagram-project" href="/core-concepts/agent_node" arrow>
  Embed agents in workflows for chaining and batch processing.
</Card>

***

## Working in chat

### Chat input menu

The **+** menu in the chat input bar gives you quick actions while you work:

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/chat_input_menu.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=6b013be03e488fc657272bf242b74ac2" alt="Chat input plus menu showing Add photos and files, Use skill, Mention integration, Mention secret, and an Incognito toggle" width="520" data-path="images/agents/chat_input_menu.webp" />
</Frame>

* **Add photos & files**: attach files and images to your message.
* **Use skill**: manually point the agent at a specific skill.
* **Mention integration**: reference a connected app directly in your message.
* **Mention secret**: reference a stored secret so the agent can use it without you pasting the value.
* **Incognito**: toggle a private conversation (covered below).

### Voice input

You can send audio instead of typing. Gumloop transcribes it on the server and sends the text to the agent, so the conversation flows the same whether you type or talk.

<Frame>
  <img src="https://mintcdn.com/agenthub/INsUni3bdOal4yXv/images/voice_input.png?fit=max&auto=format&n=INsUni3bdOal4yXv&q=85&s=9cf97f98bb858ecdb4cd64fa8e03c8ea" alt="Agent chat input showing the microphone button for voice input" width="480" data-path="images/voice_input.png" />
</Frame>

Supported formats include mp3, mp4, m4a, wav, and webm, up to 25 MB. Transcription runs on Gumloop's servers using OpenAI transcription models (Whisper and GPT-4o Transcribe), so your agent only ever receives the text transcript, never the raw audio.

### Message queue and steering

You do not have to wait for the agent to finish before sending your next message. The **message queue** lets you line up follow-ups while the agent works, and it picks them up between steps.

<Frame>
  <img src="https://mintcdn.com/agenthub/INsUni3bdOal4yXv/images/message_queue.png?fit=max&auto=format&n=INsUni3bdOal4yXv&q=85&s=3a41e87ea6658c83c4a041bfc18022d6" alt="Message queue showing multiple queued messages in different states" width="520" data-path="images/message_queue.png" />
</Frame>

Because queued messages are injected at natural breakpoints, they act as **steering**: redirect the agent ("focus on the Q3 numbers instead"), add missing context ("the deadline is Friday"), or stack a sequence of tasks. You can edit, reorder, or remove a queued message before it is delivered.

### Context usage meter

The circular meter in the bottom-right of the chat input shows how much of the agent's usable context window is in use — for models where you picked a [short context window](#short-vs-long-context-windows), that smaller window is the denominator. Hover it for a breakdown across System, AI Instructions, Abilities, Tools, Skills, Subagents, and Conversation.

<Frame>
  <img src="https://mintcdn.com/agenthub/eiDmZhoAkXtyDtXr/images/context_usage_meter.png?fit=max&auto=format&n=eiDmZhoAkXtyDtXr&q=85&s=bfa3c2740477fb2d78e678454365ba9d" alt="Context Usage Meter showing a token breakdown by category" width="520" data-path="images/context_usage_meter.png" />
</Frame>

<Tip>If context fills up, reduce the tools or skills attached, switch to a model with a larger context window, or rely on auto summarization to compress older messages.</Tip>

### Incognito mode

Incognito conversations are **not saved to the database**. They are held in temporary memory and auto-deleted after 24 hours. Toggle **Incognito** from the chat input menu before sending.

| Behavior                          | Standard chat      | Incognito chat              |
| --------------------------------- | ------------------ | --------------------------- |
| **Message storage**               | Saved permanently  | Not saved                   |
| **Visible in history and search** | Yes                | No                          |
| **Included in data exports**      | Yes                | No                          |
| **Files and artifacts**           | Stored permanently | Auto-deleted after 24 hours |
| **Used for reflections**          | Yes                | No, excluded                |

<Warning>Incognito applies to the whole conversation, including any subagents it spawns. Once it expires, messages and files are permanently gone.</Warning>

***

## Understanding credit costs

Agents consume credits for AI model usage, tool calls, the compute time they spend working, an orchestration fee on top of those, and any workflows they run. Cost depends on the model, message length, conversation history, and the number of tools available.

Open **Chat Details** on any conversation to see the credit breakdown, along with the model and source of the chat. It lists a row for each credit type the chat was actually charged for: **Chat & Reasoning**, **Tool Calls**, **Workflows**, **Compute**, **Orchestration Fee**, and **Evaluation & Self-Improvement**.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/chat_details.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=61b91b993147ffe5cd114ce4d4887d47" alt="Chat Details panel showing source, model, participants, and a credit breakdown split into Chat and Reasoning and Tool Calls" width="420" data-path="images/agents/chat_details.webp" />
</Frame>

<Card title="Credits" icon="coins" href="/core-concepts/credits" arrow>
  Full model pricing, workflow and integration costs, and how to track usage.
</Card>

***

## Settings

The **Settings** tab covers how the agent presents and behaves outside its core logic.

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/settings_tab.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=4eea198511e6ac7752f3375f67ae9028" alt="Settings tab showing Personalization, Agent Details, Chat Preferences with Smart Suggestions and File Sharing Behavior, Slack Preferences, Secrets, and Danger Zone" width="280" data-path="images/agents/settings_tab.webp" />
</Frame>

| Section               | What it controls                                                                                                                                                       |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Personalization**   | The agent's icon, name, and description.                                                                                                                               |
| **Agent Details**     | Metadata about the agent, plus **Make a Copy** to duplicate it.                                                                                                        |
| **Chat Preferences**  | **Smart Suggestions** (suggested next actions in chat) and **File Sharing Behavior** (Default, Organization, or Anyone with the link) for files the agent generates.   |
| **Slack Preferences** | How the agent behaves in [Slack](/core-concepts/agents_slack), including thread responses and attribution.                                                             |
| **Secrets**           | Environment variables and secrets the agent can use. These are injected into the [code sandbox](/core-concepts/agent_sandbox_and_secrets) at runtime and never logged. |
| **Danger Zone**       | Delete the agent.                                                                                                                                                      |

***

## Deploying agents

An agent is not limited to the Gumloop chat. Deploy it where your team already works.

<CardGroup cols={2}>
  <Card title="Slack" icon="slack" href="/core-concepts/agents_slack">
    Deploy agents to Slack channels for team-wide access.
  </Card>

  <Card title="Microsoft Teams" icon="microsoft" href="/core-concepts/agents_teams">
    Deploy agents to Microsoft Teams channels.
  </Card>

  <Card title="Email" icon="envelope" href="/core-concepts/agents_email">
    Give your agent an inbox it can read and reply from.
  </Card>

  <Card title="Hosted pages" icon="globe" href="/core-concepts/hosted_pages">
    Share a public or private hosted chat page for your agent.
  </Card>
</CardGroup>

***

## Finding agents

The **Agents** page lists every agent you can access. Use the tabs to switch views:

| Tab                | What it shows                                                        |
| ------------------ | -------------------------------------------------------------------- |
| **Mine**           | Agents you created.                                                  |
| **Shared with me** | Agents others shared with you directly or through your organization. |
| **Organization**   | All agents visible to your organization.                             |

<Frame>
  <img src="https://mintcdn.com/agenthub/aLkNVzSb33_L2qU1/images/agents_shared_with_me.png?fit=max&auto=format&n=aLkNVzSb33_L2qU1&q=85&s=f8ad9b0a6ed508470d624f4da41e8a0d" alt="Agents page showing the Shared with me tab with agent cards" width="700" data-path="images/agents_shared_with_me.png" />
</Frame>

Each card shows the agent name, connected apps, creator, and last activity. You can search by name and switch between grid and list views.

***

## Usage stats

Open an agent in the builder and a line under its description summarises how it has been used over the **last 31 days**:

| Metric           | What it counts                                                                                                 |
| ---------------- | -------------------------------------------------------------------------------------------------------------- |
| **Tasks**        | Conversations started with the agent in the window.                                                            |
| **Active days**  | Distinct days the agent was used.                                                                              |
| **Actions**      | Tool calls the agent made.                                                                                     |
| **Unique users** | Distinct people who used the agent.                                                                            |
| **Top users**    | The three people with the most tasks, ordered by task count, shown as avatars you can hover for their profile. |

Which line you see depends on who has been using the agent:

<CardGroup cols={2}>
  <Card title="Team agent" icon="users">
    A project agent used by more than one person reads **Used by Ana, Ben and 4 others · 62 tasks in the last month**, with avatars for the top users.
  </Card>

  <Card title="Personal agent" icon="user">
    Everything else reads **Worked on 62 tasks in the last month**.
  </Card>
</CardGroup>

<Note>Stats respect chat visibility. If you can only see your own conversations with an agent, the numbers cover just your usage and no users are named. Subagent runs are excluded for the general agent, and [incognito](#incognito-mode) chats never count.</Note>

<Tip>Organization-wide reporting lives elsewhere: see [Insights](/enterprise-features/organization_insights) for credit spend, leaderboards, and per-agent and team breakdowns.</Tip>

***

## Managing chats

Every conversation appears in the sidebar. Right-click a chat or open its three-dot menu to manage it.

<Frame>
  <img src="https://mintcdn.com/agenthub/K_k-tD3TARMHQhGo/images/chat-rename/chat-context-menu.png?fit=max&auto=format&n=K_k-tD3TARMHQhGo&q=85&s=4db2474e9175908a30118242da7d9e7b" alt="Chat context menu showing Share, Rename, and Delete options" width="320" data-path="images/chat-rename/chat-context-menu.png" />
</Frame>

| Action     | What it does                                          |
| ---------- | ----------------------------------------------------- |
| **Share**  | Share the conversation as a read-only link.           |
| **Rename** | Give the chat a custom name so you can find it later. |
| **Delete** | Permanently remove the conversation.                  |

<Tip>Rename chats to keep your sidebar organized. "Q2 Marketing Plan" is easier to find than an auto-generated title.</Tip>

***

## Guardrails

For agents that take real actions, layer on guardrails so they stay within bounds.

<CardGroup cols={2}>
  <Card title="Human in the Loop" icon="hand" href="/core-concepts/human_in_the_loop">
    Make the agent pause for approval before write, delete, and other sensitive tool calls.
  </Card>

  <Card title="App Rules" icon="shield-halved" href="/enterprise-features/app-policies/app-rules">
    Set conditions that block or flag specific tool calls at the agent or organization level.
  </Card>
</CardGroup>

***

## Self-improving instructions

Your agent can update its own system prompt during a conversation. Correct it once ("always check Salesforce first," "keep emails under 100 words") and it edits its instructions so the same mistake does not happen again. Changes take effect on the next step and persist across future conversations. The toggle sits below the system prompt editor and is on by default; there is no version history, so revert by editing the prompt manually.

***

## AI advanced settings

Click **Advanced** in Agent Preferences to fine-tune how the model behaves. This is an advanced area: the defaults are optimized for a good balance of performance, cost, and reliability, so most people never need to touch it.

<Frame>
  <img src="https://mintcdn.com/agenthub/fhr7WBNBrGYpSeR_/images/ai_advanced_settings.png?fit=max&auto=format&n=fhr7WBNBrGYpSeR_&q=85&s=43bfbf73faeae246382e81bf63bff4b2" alt="AI Advanced Settings panel" width="440" data-path="images/ai_advanced_settings.png" />
</Frame>

Settings are organized into three tabs.

<Tabs>
  <Tab title="Model">
    Per-provider model parameters. Settings are stored per provider, so switching models preserves your preferences for each.

    <Frame>
      <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/ai_advanced_settings_model.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=f068b9c342bac9fbe13db52bd89f0504" alt="The Model tab of AI Advanced Settings showing Max Steps, extended thinking, temperature, and max tokens" width="320" data-path="images/agents/ai_advanced_settings_model.webp" />
    </Frame>

    | Parameter                | Notes                                                                                                                                                                                                    |
    | ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
    | **Max Steps**            | How many tool calls the agent can make before it has to respond. Default 100, range 1 to 200. Increase for tasks that chain many tools.                                                                  |
    | **Context Window**       | How much of the model's context the agent uses before older messages are summarized. Only shown for models that offer two windows. See [Short vs. long context windows](#short-vs-long-context-windows). |
    | **Reasoning / thinking** | How much the model deliberates before answering. OpenAI uses Reasoning Effort, Claude uses Extended Thinking with a token budget, Gemini uses Thinking Level.                                            |
    | **Temperature**          | Output randomness. Lower is more focused, higher is more creative. Claude forces this to 1.0 when extended thinking is on.                                                                               |
    | **Max Tokens**           | Upper bound on generated tokens. Defaults to Auto.                                                                                                                                                       |
    | **Top P / Top K**        | Sampling controls. Adjust Temperature or Top P, not both.                                                                                                                                                |
    | **Parallel tool calls**  | Whether the model can call multiple tools at once. Disable for strict sequential execution.                                                                                                              |
  </Tab>

  <Tab title="Summarization">
    When a conversation approaches the model's context limit, older messages are compacted into a structured recap (Goal, Actions Taken, Key Data, Status, Next Steps) while recent messages are kept in full.

    The defaults are optimized: summarization kicks in at **80%** of the context window, roughly the most recent 40,000 tokens are protected from compaction, and summaries are capped around 30,000 tokens.

    Turn on **Override Auto Summarization** to tune it per agent:

    | Setting                       | Notes                                                                                                                            |
    | ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
    | **Summarization Trigger (%)** | How full the context window gets before summarization starts. Default **80%**, adjustable from **50%** to **95%** in steps of 5. |
    | **Summary Model**             | Which model writes the summary. Leave empty to use the recommended preset chain.                                                 |
    | **Protected Context Tokens**  | How much recent conversation is kept in full. Default 40,000 tokens.                                                             |
    | **Max Summary Tokens**        | Upper bound on the generated summary. Default 30,000 tokens.                                                                     |

    A **lower trigger** summarizes sooner and more often: each request carries less history, so it costs less per turn but the agent leans on recaps earlier. A **higher trigger** keeps more raw history in context before compacting, which preserves detail at higher per-request cost.

    <Note>The trigger is a ceiling, not a guarantee. Gumloop also reserves room for the model's response, so on a model with a large reserve summarization can start a little before your chosen percentage.</Note>
  </Tab>

  <Tab title="Fallback">
    If your primary model is unavailable, the agent retries and then switches to a fallback model. Models from the same provider are excluded so you get real redundancy. Auto mode picks fallbacks based on your primary model; override mode lets you pick up to two.
  </Tab>
</Tabs>

### Short vs. long context windows

Some models ship two context windows: a smaller one and a much larger one. When your agent is on one of those models, you choose which one it uses.

**Where to find it:** in Agent Preferences, click **Advanced** — the link above the model picker — to open **AI Advanced Settings**, then stay on the **Model** tab. **Context Window** is the dropdown below the thinking controls, and each option is labeled with its token count.

<Frame>
  <img src="https://mintcdn.com/agenthub/8IlaSXxcT_CMJ6eh/images/agents/context_window_setting.png?fit=max&auto=format&n=8IlaSXxcT_CMJ6eh&q=85&s=c3e590d508e76bbb81a9dba3319c664f" alt="The Model tab of AI Advanced Settings with the Context Window dropdown open, showing 300K and 1M options" width="520" data-path="images/agents/context_window_setting.png" />
</Frame>

| Choice                          | What it does                                                                                                  |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| **The larger window** (default) | The agent uses the model's full context window. Keeps the most history in context.                            |
| **The smaller window**          | The agent uses the model's short window instead. Summarization starts earlier, which lowers cost and latency. |

The models that offer the choice, and the two windows each one exposes:

| Model                                                                                                | Short       | Long         |
| ---------------------------------------------------------------------------------------------------- | ----------- | ------------ |
| GPT-5.6 Sol, Terra, Luna                                                                             | 272K tokens | 1.05M tokens |
| Claude 5 Opus, Claude 4.8 Opus, Claude 4.7 Opus, Claude 4.6 Opus, Claude 5 Sonnet, Claude 4.6 Sonnet | 300K tokens | 1M tokens    |
| Grok 4.6, Grok 4.5                                                                                   | 200K tokens | 500K tokens  |

Every other model has a single context window and never shows the setting.

<Info>Like the other Model tab parameters, the choice is saved **per provider**, so switching between two models from the same provider keeps it. It is not sent to the model provider — it changes the context budget Gumloop enforces, so it drives when [summarization](#ai-advanced-settings) compacts older messages and what the [context usage meter](#context-usage-meter) measures against.</Info>

<Tip>**Cost angle.** GPT-5.6 and Grok 4.5/4.6 charge higher long-context rates once a request's input tokens cross the short window (272K and 200K respectively), and the higher rates apply to the whole request. Choosing the smaller window keeps conversations under that boundary. Claude's 1M-token models bill at one flat rate, so there the choice is about how much history to keep, not price.</Tip>

<Note>If you set an explicit **context limit** in the **Summarization** tab, that limit wins and the Context Window choice no longer applies.</Note>

<Card title="AI Models" icon="microchip" href="/core-concepts/ai_models" arrow>
  Browse the full model catalog by tier, vision models, and bring-your-own-key (BYOK).
</Card>

***

## Best practices and troubleshooting

<Tabs>
  <Tab title="Best practices">
    <AccordionGroup>
      <Accordion title="Start simple, add complexity" icon="seedling">
        Begin with two or three apps and short instructions. Test, watch how the agent behaves, then add tools and rules based on real usage. Avoid launching with 15 tools and a 2,000-word prompt.
      </Accordion>

      <Accordion title="Treat the agent as a work in progress" icon="arrows-rotate">
        When the agent makes a mistake, ask it: "What could I add to your instructions to prevent this?" Then have it update its own prompt or a skill. Review conversation history for patterns.
      </Accordion>

      <Accordion title="Set clear boundaries" icon="shield">
        Be explicit about what the agent should never do without approval: delete records, send emails, make purchases, or modify production data. Back this up with [Tool Management](#tool-management-and-approvals) approvals.
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Troubleshooting">
    <AccordionGroup>
      <Accordion title="Agent uses the wrong tool" icon="wrench">
        Make tool and workflow names more descriptive, add "when to use" guidance in the system prompt, and reduce similar tools that might confuse it.
      </Accordion>

      <Accordion title="Authentication errors" icon="key">
        Check the app's [account selection](#account-selection), authenticate on your [Connectors page](https://www.gumloop.com/personal/connectors), and set it as your personal default. For team agents, contact your team admin.
      </Accordion>

      <Accordion title="Asks for too many (or too few) confirmations" icon="hand">
        Tune the app's [Tool Management](#tool-management-and-approvals) preset, or list in the system prompt which actions need approval and which can proceed automatically.
      </Accordion>

      <Accordion title="High credit costs" icon="coins">
        Switch to a cheaper model for simple tasks, add your own provider API key (BYOK) to waive model credits entirely, start new conversations instead of long threads, reduce the tool count, and write clearer prompts to cut back-and-forth.
      </Accordion>

      <Accordion title="Agent stops before finishing" icon="circle-xmark">
        Check that every required app is connected and authenticated, review the conversation for the specific tool error, and raise **Max Steps** in AI Advanced Settings if the task needs many tool calls in sequence.
      </Accordion>
    </AccordionGroup>
  </Tab>
</Tabs>

***

## Next steps

<CardGroup cols={2}>
  <Card title="Agent Triggers" icon="bolt" href="/core-concepts/agent_triggers">
    Run agents automatically on a schedule or in response to events.
  </Card>

  <Card title="Agent Skills" icon="book-open" href="/core-concepts/skills">
    Build reusable knowledge packs that teach agents how to do specific work.
  </Card>

  <Card title="Brain" icon="database" href="/core-concepts/brain">
    Give agents company knowledge to search from your connected sources.
  </Card>

  <Card title="Evaluations" icon="clipboard-check" href="/core-concepts/evaluations">
    Grade agent responses and catch regressions before they ship.
  </Card>

  <Card title="Reflections" icon="brain" href="/core-concepts/reflections">
    Let agents review their own work and propose improvements.
  </Card>

  <Card title="Code Sandbox & Secrets" icon="terminal" href="/core-concepts/agent_sandbox_and_secrets">
    Run code securely and manage agent secrets.
  </Card>

  <Card title="Agent Node" icon="diagram-project" href="/core-concepts/agent_node">
    Embed agents in workflows for chaining and batch processing.
  </Card>
</CardGroup>
