> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Zendesk

> Manage support tickets and customers with AI-powered helpdesk automation.

Zendesk is a leading customer service platform for support teams. The Zendesk MCP server lets you search tickets, manage users, and work with triggers and automations using natural language.

## What Can It Do?

* **Search and manage tickets** with advanced filters
* **Batch create and update tickets** for bulk operations
* **Look up users** and groups for routing
* **Work with views** and macros
* **Manage triggers** and automations
* **Audit ticket history** and review change events
* **Track ticket metrics** including response times and SLA data
* **Manage tags** for ticket and user categorization
* **Browse Help Center** articles and community posts
* **Access Talk/Voice data** including calls, call legs, and recordings

## Where to Use It

### In Agents (Recommended)

Add Zendesk as a tool to any agent. The agent can then manage your support operations conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Zendesk tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List open high-priority tickets")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                                                                                 |
| --------------------------- | ----------------------------------------------------------------------------------------------------------- |
| **List Tickets**            | Search or list tickets                                                                                      |
| **Get Ticket**              | Get ticket details                                                                                          |
| **Create Ticket**           | Create a new ticket                                                                                         |
| **Update Ticket**           | Update ticket fields                                                                                        |
| **Merge Tickets**           | Merge duplicate tickets                                                                                     |
| **Create Comment**          | Add a comment                                                                                               |
| **List Comments**           | List ticket comments                                                                                        |
| **List Ticket Attachments** | List attachments on a ticket                                                                                |
| **Get Attachment**          | Get attachment details                                                                                      |
| **List Ticket Fields**      | List available ticket fields                                                                                |
| **List Ticket Forms**       | List available ticket forms                                                                                 |
| **List Users**              | List agents or end-users                                                                                    |
| **Get User**                | Get user details                                                                                            |
| **Search Users**            | Search by email or name                                                                                     |
| **List Groups**             | List agent groups                                                                                           |
| **Get Group**               | Get group details                                                                                           |
| **List Tags**               | List all tags                                                                                               |
| **Search Tags**             | Search for tags                                                                                             |
| **Add Tags**                | Add tags to a ticket, user, or organization                                                                 |
| **Remove Tags**             | Remove tags from a ticket, user, or organization                                                            |
| **List Views**              | List available views                                                                                        |
| **Get View**                | Get view details                                                                                            |
| **Get Tickets In View**     | Get tickets from a view                                                                                     |
| **List Triggers**           | List all triggers                                                                                           |
| **Search Triggers**         | Search for triggers                                                                                         |
| **Get Trigger**             | Get trigger details                                                                                         |
| **List Trigger Categories** | List trigger categories                                                                                     |
| **List Automations**        | List all automations                                                                                        |
| **Search Automations**      | Search for automations                                                                                      |
| **Get Automation**          | Get automation details                                                                                      |
| **List Macros**             | List available macros                                                                                       |
| **Search Macros**           | Search for macros                                                                                           |
| **Get Macro**               | Get macro details                                                                                           |
| **Preview Macro**           | Preview what changes a macro would make to a ticket without actually applying them                          |
| **Apply Macro**             | Apply a macro to a ticket, executing all macro actions including field changes and comments                 |
| **List Articles**           | List Help Center articles                                                                                   |
| **Get Article**             | Get a Help Center article                                                                                   |
| **Search Help Center**      | Search across Help Center content                                                                           |
| **List Posts**              | List community posts                                                                                        |
| **Get Post**                | Get a community post                                                                                        |
| **List Ticket Audits**      | List all audits for a ticket including field updates, status changes, and assignments                       |
| **Get Ticket Audit**        | Get a specific audit record for a ticket                                                                    |
| **Get Ticket Metrics**      | Get performance metrics for a ticket including response times, resolution times, and SLA data               |
| **Batch Create Tickets**    | Create up to 100 tickets at once with full ticket options including custom fields, tags, and assignments    |
| **Batch Update Tickets**    | Update up to 100 tickets at once including status, priority, tags, custom fields, assignments, and comments |
| **List Talk Calls**         | List Zendesk Talk voice calls                                                                               |
| **List Talk Call Legs**     | List call legs for a Talk call                                                                              |
| **Download Talk Recording** | Download a Talk call recording                                                                              |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find tickets:**

```text theme={"dark"}
Show me all open high-priority tickets from this week
```

**Create a ticket:**

```text theme={"dark"}
Create a ticket with subject "Login issue" for customer@example.com
```

**Update a ticket:**

```text theme={"dark"}
Set ticket 12345 to solved and add a comment "Issue resolved"
```

**Search users:**

```text theme={"dark"}
Find the user with email john@acme.com
```

**Check views:**

```text theme={"dark"}
How many tickets are in the "Unassigned" view?
```

**Preview a macro:**

```text theme={"dark"}
Preview what macro 456 would do to ticket 12345
```

**Apply a macro:**

```text theme={"dark"}
Apply macro 456 to ticket 12345
```

**Audit ticket history:**

```text theme={"dark"}
Show me all the changes made to ticket 12345
```

**Check ticket metrics:**

```text theme={"dark"}
What are the response times and SLA metrics for ticket 12345?
```

**Batch create tickets:**

```text theme={"dark"}
Create 3 tickets for the onboarding team: "Setup account", "Configure SSO", and "Schedule training"
```

**Batch update tickets:**

```text theme={"dark"}
Set tickets 101, 102, and 103 to solved with a comment "Resolved in bulk"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific ticket IDs or user emails                                                                                              |
| Action not completing            | Check that you've authenticated with Zendesk                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Resolve the billing issue ticket" will find the ticket first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Zendesk MCP server](https://www.gumloop.com/mcp/zendesk) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
