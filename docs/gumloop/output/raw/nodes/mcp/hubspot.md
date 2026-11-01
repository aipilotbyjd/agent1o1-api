> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot

> Manage your CRM with AI-powered contact, deal, and ticket automation.

HubSpot is an all-in-one CRM platform for sales, marketing, and customer service. The HubSpot MCP server lets you manage contacts, companies, deals, tickets, and engagements using natural language.

## What Can It Do?

* **Manage contacts and companies** with full CRUD operations
* **Track and search deals** through your sales pipeline
* **Handle support tickets** and customer interactions
* **Log engagements** like calls, emails, and meetings
* **Send transactional emails** using HubSpot email templates
* **Create associations** between records
* **Manage products** in your product catalog
* **Work with lists** for contact segmentation
* **Manage properties** and custom object schemas
* **Work with any CRM object type** using generic CRUD tools
* **Build forms and workflows** for marketing automation
* **Handle files** with upload, download, and management
* **Access conversations** and inbox threads
* **Manage blog posts and landing pages** for content marketing
* **Track email and campaign analytics** for performance insights

## Where to Use It

### In Agents (Recommended)

Add HubSpot as a tool to any agent. The agent can then manage your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with HubSpot tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a contact with email and name")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                           | Description                                                                   |
| ------------------------------ | ----------------------------------------------------------------------------- |
| **List Contacts**              | List contacts with optional filtering                                         |
| **Create Contact**             | Create a new contact                                                          |
| **Get Contact**                | Retrieve a specific contact by ID                                             |
| **Update Contact**             | Update an existing contact                                                    |
| **Search Contacts**            | Search with advanced filters                                                  |
| **Merge Contacts**             | Merge two contact records                                                     |
| **GDPR Delete Contact**        | Permanently delete a contact for GDPR compliance                              |
| **List Companies**             | List companies with filtering                                                 |
| **Create Company**             | Create a new company                                                          |
| **Get Company**                | Retrieve a specific company                                                   |
| **Update Company**             | Update an existing company                                                    |
| **Search Companies**           | Search with advanced filters                                                  |
| **List Deals**                 | List deals with filtering                                                     |
| **Search Deals**               | Search for deals using advanced filters, date ranges, sorting, and pagination |
| **Create Deal**                | Create a new deal                                                             |
| **Get Deal**                   | Retrieve a specific deal by ID                                                |
| **Update Deal**                | Update an existing deal                                                       |
| **List Tickets**               | List tickets with filtering                                                   |
| **Get Ticket**                 | Retrieve a specific ticket by ID                                              |
| **Create Ticket**              | Create a new ticket                                                           |
| **Update Ticket**              | Update an existing ticket                                                     |
| **Delete Ticket**              | Delete a ticket                                                               |
| **Merge Tickets**              | Merge two tickets                                                             |
| **List Products**              | List products in the catalog                                                  |
| **Get Product**                | Retrieve a specific product                                                   |
| **Create Product**             | Create a new product                                                          |
| **Update Product**             | Update an existing product                                                    |
| **Delete Product**             | Delete a product                                                              |
| **Get Engagements**            | Get engagement data for a contact                                             |
| **Get Engagement**             | Get a specific engagement by ID                                               |
| **List Engagements**           | List all engagements                                                          |
| **Get Recent Engagements**     | Get recently created or modified engagements                                  |
| **Get Call Dispositions**      | Get available call disposition options                                        |
| **Create Engagement**          | Create a call, email, meeting, or note                                        |
| **Update Engagement**          | Update an existing engagement                                                 |
| **Delete Engagement**          | Delete an engagement                                                          |
| **Log Email**                  | Log an email activity on a HubSpot contact's timeline                         |
| **Send Transactional Email**   | Send a transactional email to a recipient using a HubSpot email template      |
| **Get Associations**           | Get associations for an object                                                |
| **Create Association**         | Link two objects together                                                     |
| **Delete Association**         | Remove an association between objects                                         |
| **Get Association Types**      | Get available association types                                               |
| **List Lists**                 | List all contact lists                                                        |
| **Get List**                   | Get a specific list                                                           |
| **Create List**                | Create a new contact list                                                     |
| **Delete List**                | Delete a list                                                                 |
| **Get List Memberships**       | Get contacts in a list                                                        |
| **Add List Members**           | Add contacts to a list                                                        |
| **Remove List Members**        | Remove contacts from a list                                                   |
| **List Properties**            | List properties for an object type                                            |
| **Create Property**            | Create a new property                                                         |
| **Update Property**            | Update an existing property                                                   |
| **Delete Property**            | Delete a property                                                             |
| **List Custom Object Schemas** | List custom object schemas                                                    |
| **List Custom Objects**        | List custom object records                                                    |
| **Create Custom Object**       | Create a custom object record                                                 |
| **Update Custom Object**       | Update a custom object record                                                 |
| **List CRM Objects**           | List CRM records for any object type                                          |
| **Get CRM Object**             | Get one or many CRM records for any object type                               |
| **Search CRM Objects**         | Search CRM records for any object type                                        |
| **Create CRM Object**          | Create one or many CRM records for any object type                            |
| **Update CRM Object**          | Update one or many CRM records for any object type                            |
| **Upsert CRM Objects**         | Create or update CRM records by unique property values                        |
| **Archive CRM Object**         | Archive one or many CRM records for any object type                           |
| **List Forms**                 | List all forms                                                                |
| **Get Form Submissions**       | Get submissions for a form                                                    |
| **List Workflows**             | List all workflows                                                            |
| **Enroll In Workflow**         | Enroll a contact in a workflow                                                |
| **Get Events**                 | Get timeline events                                                           |
| **Get Email Statistics**       | Get email campaign statistics                                                 |
| **List Campaigns**             | List marketing campaigns                                                      |
| **List Blog Posts**            | List blog posts                                                               |
| **Create Blog Post**           | Create a new blog post                                                        |
| **Update Blog Post**           | Update a blog post                                                            |
| **Delete Blog Post**           | Delete a blog post                                                            |
| **List Landing Pages**         | List landing pages                                                            |
| **List Files**                 | List files in the file manager                                                |
| **Upload File**                | Upload a file                                                                 |
| **Download File**              | Download a file                                                               |
| **Delete File**                | Delete a file                                                                 |
| **List Conversation Inboxes**  | List conversation inboxes                                                     |
| **List Conversation Threads**  | List conversation threads                                                     |
| **Get Thread Messages**        | Get messages in a thread                                                      |
| **Send Thread Message**        | Send a message in a thread                                                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find contacts:**

```text theme={"dark"}
Find all contacts at Microsoft with Director in their title
```

**Create a deal:**

```text theme={"dark"}
Create a deal called "Enterprise License - Acme" for $75,000 in the proposal stage
```

**Search deals:**

```text theme={"dark"}
Find all deals closing this quarter with amount over $50,000
```

**Update a contact:**

```text theme={"dark"}
Update john@company.com to lifecycle stage "customer"
```

**Check tickets:**

```text theme={"dark"}
Show me all high-priority open tickets
```

**Log an activity:**

```text theme={"dark"}
Log a 15-minute call with the Acme contact about their renewal
```

**Log an email on the timeline:**

```text theme={"dark"}
Log an email on john@acme.com's timeline with subject "Q4 renewal" and the body of my follow-up
```

**Send a transactional email:**

```text theme={"dark"}
Send the "Welcome Email" transactional template to jane@company.com
```

<Info>
  The legacy **Send Email** tool is deprecated. Use **Send Transactional Email** for outbound sends (requires the `transactional-email` scope and a configured HubSpot email template) and **Log Email** to record an email activity on a contact's timeline.
</Info>

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific names, emails, or IDs                                                                                                  |
| Action not completing            | Check that you've authenticated with HubSpot                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the Acme deal to closed-won" will find the deal first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [HubSpot MCP server](https://www.gumloop.com/mcp/hubspot) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
