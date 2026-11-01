> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Loops

> Manage mailing lists, contacts, and email automation with AI-powered workflows.

Loops is a modern email platform for SaaS companies. The Loops MCP server lets you manage contacts, mailing lists, and send transactional or event-triggered emails using natural language.

## What Can It Do?

* **Manage contacts** including create, update, find, and delete
* **Organize mailing lists** and subscriber segments
* **Send event-triggered emails** to automate communication flows
* **Send transactional emails** with dynamic data variables
* **Retrieve contact properties** for audience insights

## Where to Use It

### In Agents (Recommended)

Add Loops as a tool to any agent. The agent can then interact with your email platform conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Loops account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Loops tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Find a contact by email address")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                          | Description                                                                                               |
| ----------------------------- | --------------------------------------------------------------------------------------------------------- |
| **List Mailing Lists**        | Retrieve all mailing lists with name, description, and privacy settings                                   |
| **Find Contact**              | Find a contact by email address or user ID                                                                |
| **Create Contact**            | Create a new contact with email and optional properties like name, source, and mailing list subscriptions |
| **Update Contact**            | Update or create a contact with new properties (requires email or userId)                                 |
| **Delete Contact**            | Delete a contact by email address or user ID                                                              |
| **List Contact Properties**   | Retrieve contact properties, optionally filtered to custom properties only                                |
| **Send Event**                | Send events to trigger emails in Loops, identified by email or userId                                     |
| **Send Transactional Email**  | Send a transactional email with data variables and optional attachments                                   |
| **List Transactional Emails** | Retrieve transactional emails with automatic pagination support                                           |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find a contact:**

```text theme={"dark"}
Find the contact with email sarah@company.com
```

**Create a contact:**

```text theme={"dark"}
Create a new contact with email john@startup.io, first name John, last name Doe, and subscribe them to the Product Updates mailing list
```

**Send an event:**

```text theme={"dark"}
Send a "signup_completed" event for the contact with email new_user@example.com
```

**Send a transactional email:**

```text theme={"dark"}
Send the welcome email template to alex@business.com with the data variable company_name set to "Acme Inc"
```

**List mailing lists:**

```text theme={"dark"}
Show me all my mailing lists and their privacy settings
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                    |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Contact not found     | Verify the email address or userId is correct                                                                                               |
| Action not completing | Check that you've authenticated with your Loops API key                                                                                     |
| 409 Conflict error    | The contact already exists; use Update Contact instead of Create Contact                                                                    |
| Unexpected results    | The agent may chain multiple tools (e.g., finding a contact first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                         |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Subscribe [john@example.com](mailto:john@example.com) to the Newsletter list" will find the contact first, then update their mailing list subscriptions. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Loops MCP server](https://www.gumloop.com/mcp/loops) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
