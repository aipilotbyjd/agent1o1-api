> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Calendar

> Manage events and scheduling with AI-powered calendar automation.

Google Calendar is Google's scheduling service for managing events and appointments. The Google Calendar MCP server lets you create, update, and search events using natural language.

## What Can It Do?

* **List and search events** for any date or time range with detailed attendee information, filterable by event type
* **Create meetings** with attendees and details, including special event types like Out of Office, Focus Time, and Working Location
* **Auto-generate Google Meet links** when creating events
* **Update or cancel events** without opening your calendar
* **Update attendee responses** for any event
* **Check free slots** for smart scheduling
* **Manage attendees** by adding or removing them from events
* **List calendars** accessible to the user
* **Move events** between calendars
* **View recurring event instances** with date filtering
* **Manage access control** rules for calendar sharing

## Where to Use It

### In Agents (Recommended)

Add Google Calendar as a tool to any agent. The agent can then manage your schedule conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Calendar tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a 30-minute meeting tomorrow")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                               | Description                                                                                                                                                                                                      |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **List Events**                    | Retrieve events for a date range (includes attendee details with response status). Supports filtering by event type: default, Out of Office, Focus Time, Working Location, Birthday, and from Gmail.             |
| **Get Event**                      | Get a single event by ID with full details                                                                                                                                                                       |
| **Create Event**                   | Add a new event to your calendar (optionally auto-generates a Google Meet link via `create_conference`). Supports creating Out of Office, Focus Time, and Working Location events with type-specific properties. |
| **Update Event**                   | Modify an existing event. Supports updating event type properties such as Out of Office, Focus Time, and Working Location.                                                                                       |
| **Delete Event**                   | Remove an event                                                                                                                                                                                                  |
| **Update Attendee Status**         | Change an attendee's response status for an event                                                                                                                                                                |
| **Manage Attendee**                | Add or remove an attendee from an event                                                                                                                                                                          |
| **Check Free Slots**               | Find available time blocks                                                                                                                                                                                       |
| **List Calendars**                 | List all calendars accessible to the user                                                                                                                                                                        |
| **Move Event**                     | Move an event to a different calendar                                                                                                                                                                            |
| **List Recurring Event Instances** | List individual occurrences of a recurring event                                                                                                                                                                 |
| **List ACL Rules**                 | List access control rules for a calendar                                                                                                                                                                         |
| **Manage ACL Rule**                | Add or remove an access control rule on a calendar                                                                                                                                                               |

## Example Prompts

Use these with your agent or in the Agent Node:

**View schedule:**

```text theme={"dark"}
What meetings do I have tomorrow?
```

**Create a meeting:**

```text theme={"dark"}
Schedule a 45-minute meeting with sarah@company.com next Tuesday at 2pm
```

**Create a meeting with a Google Meet link:**

```text theme={"dark"}
Schedule a 30-minute meeting with the eng team tomorrow at 10am and add a Google Meet link
```

**Check availability:**

```text theme={"dark"}
Find free 30-minute slots on Friday between 9am and 5pm
```

**Update a meeting:**

```text theme={"dark"}
Move my 10am meeting to 2pm
```

**Cancel a meeting:**

```text theme={"dark"}
Delete my meeting with John tomorrow
```

**Set Out of Office:**

```text theme={"dark"}
Create an out of office event for next Friday with the auto-decline message "On PTO"
```

**Block focus time:**

```text theme={"dark"}
Block 2 hours of focus time tomorrow morning starting at 9am
```

**Filter by event type:**

```text theme={"dark"}
Show me all out of office events on my calendar this month
```

**Check attendee responses:**

```text theme={"dark"}
Who has accepted the team standup meeting tomorrow?
```

**Add an attendee:**

```text theme={"dark"}
Add jane@company.com to my 3pm meeting tomorrow
```

**List calendars:**

```text theme={"dark"}
Show me all my calendars
```

**Move an event:**

```text theme={"dark"}
Move the project review meeting to my Work calendar
```

**View recurring instances:**

```text theme={"dark"}
Show me all instances of my weekly team standup for this month
```

**Manage calendar access:**

```text theme={"dark"}
Share my calendar with the marketing team as readers
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                        |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify dates and times clearly with timezone                                                                                                   |
| Action not completing            | Check that you've authenticated with Google Calendar                                                                                            |
| Unexpected results               | The agent may chain multiple tools (e.g., checking availability first, then creating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Schedule a meeting when I'm free" will check availability first, then create the event. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Calendar MCP server](https://www.gumloop.com/mcp/gcalendar) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
