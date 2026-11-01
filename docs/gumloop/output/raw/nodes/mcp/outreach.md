> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Outreach

> Manage sales engagement with AI-powered outreach automation.

Outreach is a sales engagement platform that helps revenue teams manage prospects, sequences, emails, and tasks. The Outreach MCP server lets you manage your entire sales engagement workflow using natural language.

## What Can It Do?

* **Manage prospects** — list, create, update, and delete prospect records
* **Handle accounts** — create and manage company records
* **Run sequences** — create, activate, and manage multi-step outreach sequences
* **Send emails** — send one-off emails or use templates through connected mailboxes
* **Manage tasks** — create, assign, complete, and track sales tasks
* **Log calls** — record call activities against prospects
* **Track opportunities** — manage sales deals through pipeline stages
* **Monitor engagement** — view email opens, clicks, replies, and other activity events

## Where to Use It

### In Agents (Recommended)

Add Outreach as a tool to any agent. The agent can then manage your sales engagement conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Outreach tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Add prospect to outbound sequence")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Prospects

| Tool                     | Description                                                                                            |
| ------------------------ | ------------------------------------------------------------------------------------------------------ |
| **List Prospects**       | List prospects with filtering and pagination support                                                   |
| **Get Prospect**         | Get a single prospect by ID, including contact details and engagement state                            |
| **Create Prospect**      | Create a new prospect with contact details and optional links to an account, owner, stage, and persona |
| **Update Prospect**      | Update an existing prospect's contact details or linked records                                        |
| **Delete Prospect**      | Permanently delete a prospect by ID                                                                    |
| **List Prospect Notes**  | List notes attached to prospects, optionally filtered to a single prospect                             |
| **Create Prospect Note** | Create a note on a prospect                                                                            |

### Accounts

| Tool                    | Description                                                              |
| ----------------------- | ------------------------------------------------------------------------ |
| **List Accounts**       | List accounts with filtering and pagination support                      |
| **Get Account**         | Get a single account by ID                                               |
| **Create Account**      | Create a new account (company record)                                    |
| **Update Account**      | Update an existing account                                               |
| **Delete Account**      | Permanently delete an account by ID                                      |
| **List Account Notes**  | List notes attached to accounts, optionally filtered to a single account |
| **Create Account Note** | Create a note on an account                                              |

### Opportunities

| Tool                        | Description                                                                 |
| --------------------------- | --------------------------------------------------------------------------- |
| **List Opportunities**      | List opportunities (sales deals) with filtering and pagination support      |
| **Get Opportunity**         | Get a single opportunity by ID                                              |
| **Create Opportunity**      | Create a new opportunity, optionally linked to an account, stage, and owner |
| **Update Opportunity**      | Update an existing opportunity                                              |
| **Delete Opportunity**      | Permanently delete an opportunity by ID                                     |
| **List Opportunity Stages** | List opportunity stages (pipeline stages for sales deals)                   |

### Sequences

| Tool                              | Description                                                                 |
| --------------------------------- | --------------------------------------------------------------------------- |
| **List Sequences**                | List sequences with filtering, sorting, and pagination support              |
| **Get Sequence**                  | Get a single sequence by ID                                                 |
| **Create Sequence**               | Create a new sequence (interval- or date-based)                             |
| **Update Sequence**               | Update an existing sequence's attributes                                    |
| **Update Sequence Status**        | Activate or deactivate a sequence                                           |
| **Delete Sequence**               | Delete a sequence by ID                                                     |
| **List Sequence Steps**           | List sequence steps, optionally filtered by sequence                        |
| **Create Sequence Step**          | Add a step (auto email, manual email, call, or task) to a sequence          |
| **Update Sequence Step**          | Update an existing sequence step                                            |
| **Add Template to Sequence Step** | Attach an email template to a sequence step                                 |
| **Add Prospect to Sequence**      | Add a prospect to a sequence by creating a sequence state                   |
| **List Sequence States**          | List sequence states (prospects in sequences) with filtering and pagination |
| **Get Sequence State**            | Get a single sequence state by ID                                           |
| **Update Sequence State**         | Pause, resume, or finish a prospect's sequence state                        |
| **Remove Prospect from Sequence** | Remove a prospect from a sequence                                           |

### Emails

| Tool               | Description                                                                         |
| ------------------ | ----------------------------------------------------------------------------------- |
| **List Emails**    | List emails with filtering and pagination, including delivery and engagement state  |
| **Get Email**      | Get a single email by ID, including content, delivery state, and engagement details |
| **Send Email**     | Send a one-off email to a prospect through a connected mailbox                      |
| **List Mailboxes** | List connected email accounts with filtering and pagination                         |

### Templates & Snippets

| Tool                | Description                                                       |
| ------------------- | ----------------------------------------------------------------- |
| **List Templates**  | List reusable email templates with filtering and pagination       |
| **Get Template**    | Get a single email template by ID                                 |
| **Create Template** | Create a reusable email template for one-off emails and sequences |
| **Update Template** | Update an existing email template                                 |
| **List Snippets**   | List reusable email snippets with filtering and pagination        |
| **Get Snippet**     | Get a single email snippet by ID                                  |

### Tasks

| Tool              | Description                                                |
| ----------------- | ---------------------------------------------------------- |
| **List Tasks**    | List tasks with filtering and pagination support           |
| **Get Task**      | Get a single task by ID                                    |
| **Create Task**   | Create a new task, optionally tied to a prospect           |
| **Update Task**   | Update an existing task's action, due date, note, or owner |
| **Complete Task** | Mark a task as complete                                    |
| **Delete Task**   | Delete a task by ID                                        |

### Calls

| Tool                       | Description                                         |
| -------------------------- | --------------------------------------------------- |
| **List Calls**             | List logged calls with filtering and pagination     |
| **Get Call**               | Get a single logged call by ID                      |
| **Log Call**               | Log a call against a prospect                       |
| **List Call Dispositions** | List call dispositions used to categorize call logs |
| **List Call Purposes**     | List call purposes used to categorize call logs     |

### Users & Teams

| Tool              | Description                                                      |
| ----------------- | ---------------------------------------------------------------- |
| **List Users**    | List Outreach users (seat holders) with filtering and pagination |
| **Get User**      | Get a single Outreach user by ID                                 |
| **List Teams**    | List teams (groups of users) with filtering and pagination       |
| **List Stages**   | List prospect stages (pipeline categories)                       |
| **List Personas** | List personas (categories describing types of prospects)         |

### Events

| Tool            | Description                                                                       |
| --------------- | --------------------------------------------------------------------------------- |
| **List Events** | List activity events (email opens, clicks, replies) with filtering and pagination |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find prospects:**

```text theme={"dark"}
List all prospects at acme.com
```

**Add to sequence:**

```text theme={"dark"}
Add john@acme.com to the "Enterprise Outbound" sequence
```

**Send email:**

```text theme={"dark"}
Send a follow-up email to the prospect asking about their timeline
```

**Manage tasks:**

```text theme={"dark"}
Create a task to call the prospect tomorrow at 2pm
```

**Track engagement:**

```text theme={"dark"}
Show me all email opens and clicks from the last 7 days
```

**Pipeline management:**

```text theme={"dark"}
Create an opportunity for Acme Corp at $50,000 in the "Proposal" stage
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                               |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific emails or prospect IDs                                                                                                                    |
| Action not completing            | Check that you've authenticated with Outreach                                                                                                          |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a prospect first, then adding to sequence). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                    |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add [john@company.com](mailto:john@company.com) to the outbound sequence" will find the prospect first, then add to the sequence. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Outreach MCP server](https://www.gumloop.com/mcp/outreach) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
