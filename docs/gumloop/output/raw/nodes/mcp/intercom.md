> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Intercom

> Manage customer conversations and support with AI-powered automation.

Intercom is a customer messaging platform for sales, marketing, and support. The Intercom MCP server lets you manage contacts, conversations, tickets, and help center content using natural language.

## What Can It Do?

* **Search and manage contacts** by name or email
* **Handle conversations** with replies and tags
* **Create and update tickets** for support workflows
* **Work with companies** and help center articles

## Where to Use It

### In Agents (Recommended)

Add Intercom as a tool to any agent. The agent can then manage your customer data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Intercom tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a ticket for a billing issue")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                      | Description                          |
| ------------------------- | ------------------------------------ |
| **Search Contacts**       | Search for contacts by name or email |
| **Update Contact**        | Update, archive, or block a contact  |
| **Create Contact**        | Create a new contact                 |
| **Create Conversation**   | Start a new conversation             |
| **Reply To Conversation** | Reply to an existing conversation    |
| **Update Conversation**   | Add or remove tags                   |
| **Search Conversations**  | Search conversations by criteria     |
| **Search Companies**      | Search for companies                 |
| **Create Company**        | Create a new company                 |
| **List Articles**         | List help center articles            |
| **Search Tickets**        | Search for tickets                   |
| **Create Ticket**         | Create a new support ticket          |
| **Update Ticket**         | Update ticket status                 |
| **Add Comment To Ticket** | Add a comment to a ticket            |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find a contact:**

```text theme={"dark"}
Find the contact with email john@company.com
```

**Create a ticket:**

```text theme={"dark"}
Create a support ticket for billing discrepancy for this contact
```

**Search conversations:**

```text theme={"dark"}
Find all open conversations tagged "urgent"
```

**Update a ticket:**

```text theme={"dark"}
Set the billing ticket status to in progress
```

**Reply to conversation:**

```text theme={"dark"}
Reply to the conversation saying we're looking into it
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                             |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific emails or conversation IDs                                                                                                              |
| Action not completing            | Check that you've authenticated with Intercom                                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a contact first, then creating a ticket). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                  |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a ticket for [john@company.com](mailto:john@company.com)" will find the contact first, then create the ticket. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Intercom MCP server](https://www.gumloop.com/mcp/intercom) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
