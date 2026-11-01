> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Pipedrive

> Manage your sales pipeline with AI-powered CRM automation.

Pipedrive is a sales CRM designed to help teams manage deals and close more business. The Pipedrive MCP server lets you manage deals, contacts, organizations, activities, and more using natural language.

## What Can It Do?

* **Manage deals** through your sales pipeline
* **Create and update contacts** and organizations
* **Schedule activities** and tasks
* **Track email threads** and communications
* **Manage projects** and notes

## Where to Use It

### In Agents (Recommended)

Add Pipedrive as a tool to any agent. The agent can then manage your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Pipedrive tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a deal for Acme Corp")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                 |
| ------------------------ | --------------------------- |
| **List Deals**           | Get deals with filtering    |
| **Search Deals**         | Search deals by title       |
| **Create Deal**          | Create a new deal           |
| **Update Deal**          | Update deal properties      |
| **Delete Deal**          | Delete a deal               |
| **Search Organizations** | Search organizations        |
| **Create Organization**  | Create an organization      |
| **Update Organization**  | Update organization details |
| **List Persons**         | List contacts               |
| **Search Persons**       | Search contacts             |
| **Create Person**        | Create a contact            |
| **Update Person**        | Update contact details      |
| **List Activities**      | Get activities              |
| **Create Activity**      | Schedule an activity        |
| **Get Mail Threads**     | List email threads          |
| **Create Note**          | Add a note to a record      |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find deals:**

```text theme={"dark"}
Show me all open deals over $50,000
```

**Create a deal:**

```text theme={"dark"}
Create a deal called "Enterprise License" for $75,000 linked to Acme Corp
```

**Search contacts:**

```text theme={"dark"}
Find the contact with email john@company.com
```

**Schedule activity:**

```text theme={"dark"}
Schedule a call with the Acme team for tomorrow at 2pm
```

**Add a note:**

```text theme={"dark"}
Add a note to the Acme deal saying "Meeting went well, following up next week"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                                 |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific deal titles or contact emails                                                                                                               |
| Action not completing            | Check that you've authenticated with Pipedrive                                                                                                           |
| Unexpected results               | The agent may chain multiple tools (e.g., finding an organization first, then creating a deal). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                      |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a deal for Acme Corp" will find the organization first, then create the deal. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Pipedrive MCP server](https://www.gumloop.com/mcp/pipedrive) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
