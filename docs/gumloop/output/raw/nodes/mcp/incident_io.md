> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Incident.io

> Streamline incident response with AI-powered incident and alert management.

Incident.io is an incident management platform for modern engineering teams. The Incident.io MCP server lets you create, update, and search incidents and alerts using natural language.

## What Can It Do?

* **Search incidents** by status, severity, type, or date range
* **Create new incidents** with details and Slack integration
* **Update incident status** and severity as response progresses
* **List and link alerts** to incidents for better context

## Where to Use It

### In Agents (Recommended)

Add Incident.io as a tool to any agent. The agent can then manage your incidents conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Incident.io tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all critical incidents from this week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                       |
| ------------------------ | --------------------------------- |
| **List Users**           | List users in your account        |
| **List Incidents**       | List incidents with filtering     |
| **Create Incident**      | Create a new incident             |
| **Edit Incident**        | Update status or severity         |
| **List Alerts**          | List alerts with filtering        |
| **List Incident Alerts** | List alerts linked to an incident |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find incidents:**

```text theme={"dark"}
Show me all critical incidents from this week
```

**Create an incident:**

```text theme={"dark"}
Create a new incident titled "Database connection issues" with severity critical
```

**Update status:**

```text theme={"dark"}
Set the API outage incident to resolved
```

**List alerts:**

```text theme={"dark"}
Show me all firing alerts from today
```

**Check incident details:**

```text theme={"dark"}
What's the status of the payment processing incident?
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific incident names or IDs                                                                                                  |
| Action not completing            | Check that you've authenticated with Incident.io                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Resolve the database incident" will find the incident first, then update its status. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Incident.io MCP server](https://www.gumloop.com/mcp/incident-io) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
