> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Freshdesk

> Manage support tickets, contacts, and knowledge base with AI-powered helpdesk automation.

Freshdesk is a cloud-based customer support platform for managing tickets, contacts, and self-service content. The Freshdesk MCP server lets you triage tickets, manage contacts and companies, work with the knowledge base, and orchestrate community forums using natural language.

## What Can It Do?

* **Read, create, and triage tickets** with full lifecycle management
* **Manage contacts and companies** with search, merge, and bulk operations
* **Work with the knowledge base** to create and update solution articles
* **Orchestrate community forums** with topics and comments
* **Track time entries** on tickets
* **Handle attachments** with download to Gumloop storage

## Where to Use It

### In Agents (Recommended)

Add Freshdesk as a tool to any agent. The agent can then manage your helpdesk conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Freshdesk tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List open tickets assigned to me")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Tickets

| Tool               | Description                                                                      |
| ------------------ | -------------------------------------------------------------------------------- |
| **List Tickets**   | List tickets with filters, automatic pagination, and optional embedded relations |
| **Get Ticket**     | Get a single ticket by ID, optionally embedding related data                     |
| **Create Ticket**  | Create a new ticket                                                              |
| **Update Ticket**  | Update fields on an existing ticket                                              |
| **Delete Ticket**  | Soft-delete a ticket                                                             |
| **Search Tickets** | Search tickets using the Freshdesk filter DSL                                    |
| **Merge Tickets**  | Merge one or more secondary tickets into a primary ticket                        |
| **Forward Ticket** | Forward a ticket as a new outbound email                                         |
| **Restore Ticket** | Restore a previously soft-deleted ticket                                         |

### Conversations

| Tool                    | Description                                  |
| ----------------------- | -------------------------------------------- |
| **List Conversations**  | List replies and notes on a ticket           |
| **Reply to Ticket**     | Post a public reply to a ticket              |
| **Add Note**            | Add a note (private by default) to a ticket  |
| **Update Conversation** | Update an existing conversation (notes only) |
| **Delete Conversation** | Delete a conversation (reply or note)        |

### Watchers

| Tool                    | Description                                    |
| ----------------------- | ---------------------------------------------- |
| **List Watchers**       | List agents watching a ticket                  |
| **Add Watcher**         | Add an agent as a watcher on a ticket          |
| **Remove Self Watcher** | Remove the API caller from a ticket's watchers |

### Attachments

| Tool                        | Description                                                  |
| --------------------------- | ------------------------------------------------------------ |
| **List Ticket Attachments** | List attachments on a ticket by walking its conversations    |
| **Get Attachment**          | Download a ticket attachment and store it in Gumloop storage |

### Contacts

| Tool                    | Description                                     |
| ----------------------- | ----------------------------------------------- |
| **List Contacts**       | List contacts with optional filters             |
| **Get Contact**         | Get a contact by ID                             |
| **Search Contacts**     | Search contacts using the Freshdesk filter DSL  |
| **Create Contact**      | Create a contact                                |
| **Update Contact**      | Update fields on a contact                      |
| **Delete Contact**      | Soft-delete a contact                           |
| **Hard Delete Contact** | Permanently delete a contact                    |
| **Merge Contacts**      | Merge secondary contacts into a primary contact |
| **Restore Contact**     | Restore a soft-deleted contact                  |

### Companies

| Tool                 | Description                                     |
| -------------------- | ----------------------------------------------- |
| **List Companies**   | List companies with optional filters            |
| **Get Company**      | Get a company by ID                             |
| **Search Companies** | Search companies using the Freshdesk filter DSL |
| **Create Company**   | Create a company                                |
| **Update Company**   | Update fields on a company                      |
| **Delete Company**   | Delete a company                                |

### Directory

| Tool                          | Description                                             |
| ----------------------------- | ------------------------------------------------------- |
| **List Agents**               | List agents with optional filters                       |
| **Get Agent**                 | Get an agent by ID                                      |
| **Get Current Agent**         | Get the agent associated with the authenticated API key |
| **List Groups**               | List agent groups                                       |
| **Get Group**                 | Get an agent group by ID                                |
| **List Skills**               | List agent skills                                       |
| **List Roles**                | List agent roles                                        |
| **List Products**             | List products                                           |
| **List Business Hours**       | List business hours configurations                      |
| **List SLA Policies**         | List SLA policies                                       |
| **List Ticket Fields**        | List ticket fields for schema discovery                 |
| **List Contact Fields**       | List contact fields for schema discovery                |
| **List Company Fields**       | List company fields for schema discovery                |
| **List CSAT Surveys**         | List customer satisfaction surveys                      |
| **List Satisfaction Ratings** | List customer satisfaction ratings across all surveys   |

### Time Entries

| Tool                  | Description                             |
| --------------------- | --------------------------------------- |
| **List Time Entries** | List time entries with filters          |
| **Create Time Entry** | Create a time entry on a ticket         |
| **Update Time Entry** | Update a time entry                     |
| **Delete Time Entry** | Delete a time entry                     |
| **Toggle Timer**      | Start or stop the timer on a time entry |

### Knowledge Base

| Tool                             | Description                                    |
| -------------------------------- | ---------------------------------------------- |
| **List Solution Categories**     | List solution categories in the knowledge base |
| **Get Solution Category**        | Get a solution category by ID                  |
| **List Solution Folders**        | List folders inside a solution category        |
| **Get Solution Folder**          | Get a solution folder by ID                    |
| **List Solution Articles**       | List articles inside a solution folder         |
| **Get Solution Article**         | Get a solution article by ID                   |
| **Search Solutions**             | Full-text search across solution articles      |
| **Create Solution Article**      | Create a solution article                      |
| **Update Solution Article**      | Update a solution article                      |
| **Delete Solution Article**      | Delete a solution article                      |
| **List Canned Responses**        | List canned responses visible to the agent     |
| **Get Canned Response**          | Get a canned response by ID                    |
| **List Canned Response Folders** | List canned response folders                   |
| **Get Canned Response Folder**   | Get a canned response folder by ID             |

### Forums

| Tool                      | Description                                      |
| ------------------------- | ------------------------------------------------ |
| **List Forum Categories** | List forum categories                            |
| **Get Forum Category**    | Get a forum category by ID                       |
| **List Forums**           | List forums under a category                     |
| **Get Forum**             | Get a forum by ID                                |
| **List Forum Topics**     | List topics within a forum                       |
| **Get Forum Topic**       | Get a forum topic by ID                          |
| **Create Forum Topic**    | Create a new topic in a forum                    |
| **Update Forum Topic**    | Update a forum topic                             |
| **Delete Forum Topic**    | Delete a forum topic                             |
| **Follow Forum Topic**    | Subscribe a user to updates on a forum topic     |
| **Unfollow Forum Topic**  | Unsubscribe a user from updates on a forum topic |
| **List Topic Comments**   | List comments on a forum topic                   |
| **Create Forum Comment**  | Post a comment to a forum topic                  |
| **Update Forum Comment**  | Update a forum comment                           |
| **Delete Forum Comment**  | Delete a forum comment                           |

### Threads (Pro+)

| Tool                      | Description                               |
| ------------------------- | ----------------------------------------- |
| **Get Thread**            | Get a collaboration thread by ID          |
| **Create Thread**         | Create a collaboration thread on a ticket |
| **Update Thread**         | Update a collaboration thread             |
| **Delete Thread**         | Delete a collaboration thread             |
| **Get Thread Message**    | Get a collaboration thread message by ID  |
| **Create Thread Message** | Post a message to a collaboration thread  |
| **Update Thread Message** | Update a collaboration thread message     |
| **Delete Thread Message** | Delete a collaboration thread message     |

### Custom Objects (Enterprise)

| Tool                            | Description                            |
| ------------------------------- | -------------------------------------- |
| **List Custom Object Records**  | List records of a custom object schema |
| **Get Custom Object Record**    | Get a custom object record by ID       |
| **Create Custom Object Record** | Create a custom object record          |
| **Update Custom Object Record** | Update a custom object record          |
| **Delete Custom Object Record** | Delete a custom object record          |

### Other

| Tool                            | Description                                    |
| ------------------------------- | ---------------------------------------------- |
| **Get Outbound Message Status** | Get the delivery status of an outbound message |
| **Get Job Status**              | Poll the status of an async Freshdesk job      |

## Example Prompts

Use these with your agent or in the Agent Node:

**List open tickets:**

```text theme={"dark"}
Show me all open tickets assigned to the support team
```

**Triage a ticket:**

```text theme={"dark"}
Update ticket #1234 to high priority and assign it to the billing group
```

**Search for a contact:**

```text theme={"dark"}
Find the contact with email jane@acme.com
```

**Reply to a ticket:**

```text theme={"dark"}
Reply to ticket #5678 saying we've escalated the issue to engineering
```

**Search the knowledge base:**

```text theme={"dark"}
Search for articles about password reset
```

**Merge duplicate tickets:**

```text theme={"dark"}
Merge tickets #1001 and #1002 into ticket #1000
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific ticket IDs or exact email addresses                                                                                    |
| Action not completing            | Check that you've authenticated with Freshdesk                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Reply to the latest ticket from Acme" will search for the ticket first, then post the reply. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Freshdesk MCP server](https://www.gumloop.com/mcp/freshdesk) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
