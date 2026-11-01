> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Luma

> Manage events, guests, tickets, and calendars on Lu.ma with AI-powered event management.

Luma (lu.ma) is an event management platform for hosting in-person and virtual events, managing guest lists, selling tickets, and building community calendars. The Luma MCP server lets you create and manage events, handle guest registrations, configure ticket types, manage memberships, and organize your calendar using natural language.

## What Can It Do?

* **Create and manage events** with details like location, time, visibility, cover images, and custom URLs
* **Handle guest lists** including adding guests, updating approval status, sending invitations, and filtering by status
* **Configure ticket types** for free or paid events with capacity limits and approval requirements
* **Manage memberships** with tiers, member additions, and status updates
* **Organize your calendar** with event tags, person tags, people lists, coupon management, and contact imports
* **Manage event hosts** with configurable access levels and visibility

## Where to Use It

### In Agents (Recommended)

Add Luma as a tool to any agent. The agent can manage your events and calendar conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Luma API key

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Luma tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a new event on my Luma calendar")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Event Management

| Tool                   | Description                                                                                                                 |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **List Events**        | List events from your calendar with date filtering and sorting, or look up a specific event by API ID or URL                |
| **Get Event**          | Get full details of a specific event including its hosts                                                                    |
| **Create Event**       | Create a new event with name, time, timezone, description, location, visibility, cover image, custom URL slug, and capacity |
| **Update Event**       | Update any event property with an option to suppress notifications                                                          |
| **Manage Event Hosts** | Add a host to an event with configurable access level (none, check-in, manager) and visibility                              |

### Guest Management

| Tool                    | Description                                                                                                        |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------ |
| **Get Guests**          | List guests for an event with filtering by approval status and sorting, or look up a specific guest by ID or email |
| **Add Guests**          | Add guests to an event with optional ticket type assignment                                                        |
| **Update Guest Status** | Approve or decline a guest with optional refund                                                                    |
| **Send Invites**        | Send event invitations to a list of guests with an optional custom message (max 200 characters)                    |

### Calendar & People

| Tool                   | Description                                                                                       |
| ---------------------- | ------------------------------------------------------------------------------------------------- |
| **List People**        | List people from your calendar with search, tag filtering, membership tier filtering, and sorting |
| **Import People**      | Bulk import people to your calendar by email and name                                             |
| **Manage Event Tags**  | List, create, update, delete, apply, or remove event tags                                         |
| **Manage Person Tags** | List, create, update, delete, apply, or remove person tags                                        |

### Tickets & Memberships

| Tool                    | Description                                                                                                                                              |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Manage Ticket Types** | List, get, create, update, or delete ticket types for an event. Supports free and paid tickets with pricing, capacity, approval settings, and visibility |
| **Manage Memberships**  | List membership tiers, add members, or update member status (approve/decline) with optional payment skip                                                 |
| **Manage Coupons**      | List, create, or update discount coupons for events or the calendar. Supports percentage and fixed-amount discounts                                      |

### System

| Tool         | Description                                  |
| ------------ | -------------------------------------------- |
| **Get User** | Get the current authenticated user's profile |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create an event:**

```text theme={"dark"}
Create a public event called "AI Workshop" on March 15th at 2pm EST in New York with a max capacity of 50
```

**Manage guests:**

```text theme={"dark"}
Show me all pending guests for my latest event and approve them
```

**Send invitations:**

```text theme={"dark"}
Send invitations to alice@example.com and bob@example.com for my AI Workshop event
```

**Set up tickets:**

```text theme={"dark"}
Create a paid ticket type called "Early Bird" for $25 USD with a capacity of 20 for my workshop event
```

**Manage your calendar:**

```text theme={"dark"}
List all people tagged as "VIP" in my calendar
```

**Create a coupon:**

```text theme={"dark"}
Create a 20% off coupon code "EARLYBIRD" for my workshop event
```

## Troubleshooting

| Issue                    | Solution                                                                                                                                           |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication failed    | Verify your Luma API key is connected and valid. You can generate an API key from your Luma account settings.                                      |
| Event not found          | Use the event API ID or the full lu.ma URL for lookups                                                                                             |
| Guest operations failing | Ensure you're using the correct event API ID (not the event ID) for guest management                                                               |
| Cover image rejected     | Cover image URLs must be Luma CDN URLs (`https://images.lumacdn.com/...`). Upload images through Luma first.                                       |
| Tool not available       | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |
| Unexpected results       | The agent may chain multiple tools (e.g., finding the event first, then managing guests). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Approve all pending guests for my workshop" will list events, find the right one, get pending guests, then approve each one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Luma MCP server](https://www.gumloop.com/mcp/luma) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
