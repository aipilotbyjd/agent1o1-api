> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# PagerDuty

> Manage incidents and on-call schedules with AI-powered response automation.

PagerDuty is an incident management platform for operational reliability. The PagerDuty MCP server lets you retrieve incidents, manage schedules, and track on-call coverage using natural language.

## What Can It Do?

* **List and filter incidents** by status or priority
* **Manage on-call schedules** and coverage
* **Get service information** and escalation policies
* **Track notifications** and alert history

## Where to Use It

### In Agents (Recommended)

Add PagerDuty as a tool to any agent. The agent can then manage your incident response conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with PagerDuty tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List open incidents")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                  |
| ---------------------- | ---------------------------- |
| **Get User**           | Fetch user details           |
| **List Incidents**     | List incidents with filters  |
| **List Services**      | Get all services             |
| **List Schedules**     | Get all on-call schedules    |
| **Create Schedule**    | Create a new schedule        |
| **Get Schedule**       | Get schedule details         |
| **Delete Schedule**    | Remove a schedule            |
| **List Oncalls**       | List current on-call entries |
| **List Notifications** | List recent notifications    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Check incidents:**

```text theme={"dark"}
Show me all open incidents
```

**On-call coverage:**

```text theme={"dark"}
Who is on call for the API team right now?
```

**List services:**

```text theme={"dark"}
Show me all PagerDuty services and their escalation policies
```

**Create a schedule:**

```text theme={"dark"}
Create an on-call schedule called "Weekend Support" starting next Monday
```

**Check notifications:**

```text theme={"dark"}
Show me notifications for the last 24 hours
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                             |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific schedule or service names                                                                                                               |
| Action not completing            | Check that you've authenticated with PagerDuty                                                                                                       |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a schedule first, then listing on-calls). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                  |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Who is on call for the database schedule?" will find the schedule first, then list on-call entries. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [PagerDuty MCP server](https://www.gumloop.com/mcp/pagerduty) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
