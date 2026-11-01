> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gmail

> Read, send, and organize emails with AI-powered inbox automation.

Gmail is Google's email service used by billions worldwide. The Gmail MCP server lets you search, read, send, and organize emails using natural language.

## What Can It Do?

* **Search and read emails** with any filter, label, or timeframe
* **Send emails and replies** from within workflows
* **Manage drafts** — list, create, update, send, or delete drafts
* **Work with threads** — retrieve full email conversations
* **Organize your inbox** by starring, archiving, labeling, or batch-updating
* **Manage labels** — create, update, and delete custom labels
* **Send emails with file attachments** from Gumloop storage
* **Download attachments** for automated processing

## Where to Use It

### In Agents (Recommended)

Add Gmail as a tool to any agent. The agent can then interact with your inbox conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Gmail tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Find emails from Stripe about invoices")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description                                                                                                               |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| **Read Emails**         | Search and read emails (supports `body_format`: `text`, `html`, or `raw`)                                                 |
| **Send Email**          | Send new emails or replies (supports optional `sender` for Send As aliases and file attachments from Gumloop storage)     |
| **Update Email**        | Update labels (read/unread, folders)                                                                                      |
| **Create Draft**        | Prepare emails without sending (supports optional `sender` for Send As aliases and file attachments from Gumloop storage) |
| **List Drafts**         | List email drafts in the mailbox with optional search query                                                               |
| **Update Draft**        | Update an existing email draft with new content (supports file attachments from Gumloop storage)                          |
| **Delete Draft**        | Permanently delete an email draft                                                                                         |
| **Send Draft**          | Send an existing email draft                                                                                              |
| **Forward Email**       | Forward to other recipients (supports optional `sender` for Send As aliases)                                              |
| **Get Thread**          | Get a full email thread with all messages                                                                                 |
| **Create Label**        | Create new Gmail labels                                                                                                   |
| **Update Label**        | Update a label's name, colors, or visibility settings                                                                     |
| **Delete Label**        | Delete a custom Gmail label (system labels cannot be deleted)                                                             |
| **List Labels**         | List all Gmail labels with their IDs                                                                                      |
| **Archive Email**       | Move emails out of inbox                                                                                                  |
| **Trash Email**         | Move emails to trash                                                                                                      |
| **Star/Unstar Email**   | Manage starred emails                                                                                                     |
| **Batch Update Emails** | Modify labels on multiple emails at once                                                                                  |
| **Get Attachment**      | Download and access email attachments                                                                                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search emails:**

```text theme={"dark"}
Find emails from Stripe with "invoice" in the subject from this month
```

**Send an email:**

```text theme={"dark"}
Send an email to sarah@company.com about the project kickoff
```

**Organize inbox:**

```text theme={"dark"}
Archive all newsletters older than 30 days
```

**Download attachments:**

```text theme={"dark"}
Download the PDF attachments from the latest expense report email
```

**Send with attachments:**

```text theme={"dark"}
Send an email to john@company.com with the file "report.pdf" from my storage attached
```

**Create a draft:**

```text theme={"dark"}
Create a draft reply to the latest email from my manager
```

**Manage drafts:**

```text theme={"dark"}
List my recent drafts and send the one about the project proposal
```

**View a thread:**

```text theme={"dark"}
Show me the full conversation thread for the latest email from Sarah
```

**Batch update:**

```text theme={"dark"}
Add the "Reviewed" label to all emails from the hiring team this week
```

## Troubleshooting

| Issue                            | Solution                                                                                                                               |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific sender addresses or date ranges                                                                                           |
| Action not completing            | Check that you've authenticated with Gmail                                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then downloading). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                    |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Forward the invoice from Stripe to accounting" will find the email first, then forward it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Gmail MCP server](https://www.gumloop.com/mcp/gmail) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
