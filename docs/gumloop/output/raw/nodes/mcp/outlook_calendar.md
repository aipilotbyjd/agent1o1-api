> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Outlook Calendar

> Manage calendar events and check availability with AI-powered scheduling automation.

Microsoft Outlook Calendar is Microsoft's scheduling and calendar service. The Outlook Calendar MCP server lets you view, create, update, and manage calendar events using natural language.

## What Can It Do?

* **List calendars** and browse events across time ranges
* **Create and update events** with attendees, locations, and Teams meetings
* **Delete events** to keep your schedule clean
* **Check availability** for one or more users to find free time slots

## Where to Use It

### In Agents (Recommended)

Add Outlook Calendar as a tool to any agent. The agent can then manage your calendar conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Outlook Calendar tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List my events for this week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                                                            |
| ---------------------- | ---------------------------------------------------------------------- |
| **List Calendars**     | List all calendars for the authenticated user                          |
| **List Events**        | List events from a calendar for a specified time range                 |
| **Get Event**          | Get details of a specific calendar event                               |
| **Create Event**       | Create a new event with attendees, location, and Teams meeting support |
| **Update Event**       | Update an existing calendar event                                      |
| **Delete Event**       | Delete an event from a calendar                                        |
| **Check Availability** | Check free/busy availability for one or more users                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**List events:**

```text theme={"dark"}
Show me my calendar events for this week
```

**Create an event:**

```text theme={"dark"}
Schedule a team meeting tomorrow at 2pm for 1 hour with john@company.com and jane@company.com, include a Teams link
```

**Check availability:**

```text theme={"dark"}
Check when john@company.com and jane@company.com are both free this Thursday afternoon
```

**Update an event:**

```text theme={"dark"}
Move my 3pm meeting to 4pm and add a conference room
```

**Delete an event:**

```text theme={"dark"}
Cancel my meeting with the design team on Friday
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                 |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific event subjects or date ranges                                                                                               |
| Action not completing            | Check that you've authenticated with Microsoft 365                                                                                       |
| Unexpected results               | The agent may chain multiple tools (e.g., listing events first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                      |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Reschedule my meeting with John to next week" will find the event first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Outlook Calendar MCP server](https://www.gumloop.com/mcp/outlook_calendar) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
