> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Outlook

> Manage email with AI-powered inbox automation.

Microsoft Outlook is Microsoft's email and calendar service. The Outlook MCP server lets you read, send, organize, and manage emails using natural language.

## What Can It Do?

* **Read and search emails** by sender, subject, or date
* **Send and forward emails** without opening Outlook
* **Create drafts** for later review
* **Archive or delete emails** to keep your inbox organized

## Where to Use It

### In Agents (Recommended)

Add Outlook as a tool to any agent. The agent can then manage your email conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Outlook tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Read unread emails from today")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool              | Description                                                                         |
| ----------------- | ----------------------------------------------------------------------------------- |
| **Read Emails**   | Fetch emails with filters, including To, CC, and BCC recipients                     |
| **Send Email**    | Send a new email, or send an existing draft by ID                                   |
| **Update Email**  | Change read/unread or flagged status                                                |
| **Create Draft**  | Create a new draft, or create a threaded reply/reply-all draft to an existing email |
| **Forward Email** | Forward an email                                                                    |
| **Archive Email** | Move to Archive folder                                                              |
| **Trash Email**   | Move to Deleted Items                                                               |

## Example Prompts

Use these with your agent or in the Agent Node:

**Read emails:**

```text theme={"dark"}
Show me unread emails from this week
```

**Send an email:**

```text theme={"dark"}
Send an email to john@company.com with subject "Project Update" and body "Here's the latest status..."
```

**Create a draft:**

```text theme={"dark"}
Create a draft to the sales team about the Q4 forecast
```

**Reply as a threaded draft:**

```text theme={"dark"}
Create a reply-all draft to the latest email from John with the message "Thanks, will review today"
```

**Send an existing draft:**

```text theme={"dark"}
Send the draft I just created
```

**Archive emails:**

```text theme={"dark"}
Archive all newsletters older than 30 days
```

**Forward an email:**

```text theme={"dark"}
Forward the budget approval email to sarah@company.com
```

## Troubleshooting

| Issue                            | Solution                                                                                                                              |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific sender emails or subject lines                                                                                           |
| Action not completing            | Check that you've authenticated with Microsoft 365                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then forwarding). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                   |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Forward the latest email from John" will find the email first, then forward it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Outlook MCP server](https://www.gumloop.com/mcp/outlook) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
