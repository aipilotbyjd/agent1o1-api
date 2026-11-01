> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Cal.com

> Manage scheduling and bookings with AI-powered calendar automation.

Cal.com is an open-source scheduling platform for managing availability and bookings. The Cal MCP server lets you check availability, create bookings, and manage schedules using natural language.

## What Can It Do?

* **Check availability** for event types over date ranges
* **Create and manage bookings** with timing and participants
* **Reschedule, confirm, or cancel** existing bookings
* **Retrieve schedules and event types** for automation

## Where to Use It

### In Agents (Recommended)

Add Cal.com as a tool to any agent. The agent can then interact with your calendar conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Cal.com tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get available time slots for next week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                                |
| ---------------------- | ------------------------------------------ |
| **Get Me**             | Get your Cal.com user profile              |
| **Get Event Types**    | List all event types                       |
| **Get Schedules**      | Get all schedules with working hours       |
| **Get Availability**   | Get available time slots for an event type |
| **Get Bookings**       | List bookings within a date range          |
| **Get Booking**        | Get a specific booking by ID               |
| **Create Booking**     | Create a new booking                       |
| **Reschedule Booking** | Move a booking to a new time               |
| **Confirm Booking**    | Confirm a pending booking                  |
| **Decline Booking**    | Decline a pending booking                  |
| **Cancel Booking**     | Cancel an existing booking                 |

## Example Prompts

Use these with your agent or in the Agent Node:

**Check availability:**

```text theme={"dark"}
What time slots are available for a 30-minute meeting next week?
```

**Create a booking:**

```text theme={"dark"}
Book a meeting with john@example.com on Tuesday at 2pm
```

**Reschedule a meeting:**

```text theme={"dark"}
Move my meeting with Sarah to Thursday at 10am
```

**Get upcoming bookings:**

```text theme={"dark"}
Show me all my bookings for this week
```

**Cancel a booking:**

```text theme={"dark"}
Cancel my meeting tomorrow with the reason "scheduling conflict"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                                   |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify the event type or date range clearly                                                                                                               |
| Action not completing            | Check that you've authenticated and the event type exists                                                                                                  |
| Unexpected results               | The agent may chain multiple tools (e.g., getting event types first, then checking availability). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Book a meeting" will find available slots first, then create the booking. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Cal.com MCP server](https://www.gumloop.com/mcp/cal) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
