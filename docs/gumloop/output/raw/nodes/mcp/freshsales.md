> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Freshsales

> Manage your CRM with AI-powered sales automation for contacts, accounts, deals, and more.

Freshsales is a CRM platform built for sales teams to manage contacts, accounts, deals, and the full sales pipeline. The Freshsales MCP server lets you manage records, track activities, handle CPQ products and documents, and work with custom modules using natural language.

## What Can It Do?

* **Manage contacts, accounts, and deals** with full CRUD, upsert, and bulk operations
* **Track sales activities** including tasks, appointments, calls, and notes
* **Handle CPQ products and documents** with pricing and deal associations
* **Work with marketing lists** for segmentation and outreach
* **Search and lookup records** across multiple entity types
* **Manage custom modules** with custom fields and records

## Where to Use It

### In Agents (Recommended)

Add Freshsales as a tool to any agent. The agent can then manage your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Freshsales tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List deals closing this month")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Contacts

| Tool                          | Description                                                                |
| ----------------------------- | -------------------------------------------------------------------------- |
| **List Contact Filters**      | List available contact views and filters                                   |
| **Create Contact**            | Create a contact using any current contact fields, including custom fields |
| **Get Contact**               | Get one contact by ID                                                      |
| **List Contacts**             | List contacts from a view with optional sorting and includes               |
| **Update Contact**            | Update a contact using any current contact fields                          |
| **Update Contact Team**       | Replace or update team members for a contact                               |
| **Upsert Contact**            | Create or update a contact using the upsert API                            |
| **Bulk Upsert Contacts**      | Bulk upsert contacts                                                       |
| **Bulk Assign Contact Owner** | Bulk assign contacts to an owner                                           |
| **Clone Contact**             | Clone a contact                                                            |
| **Delete Contact**            | Delete a contact                                                           |
| **Forget Contact**            | Permanently forget a contact                                               |
| **Bulk Delete Contacts**      | Bulk delete contacts                                                       |
| **List Contact Fields**       | List all contact fields, including custom fields                           |
| **List Contact Activities**   | List activities for one contact                                            |

### Accounts

| Tool                     | Description                                           |
| ------------------------ | ----------------------------------------------------- |
| **List Account Filters** | List available account views and filters              |
| **Create Account**       | Create a sales account                                |
| **Get Account**          | Get one sales account by ID                           |
| **List Accounts**        | List sales accounts from a view                       |
| **Update Account**       | Update a sales account                                |
| **Update Account Team**  | Replace or update team members for a sales account    |
| **Upsert Account**       | Create or update a sales account using the upsert API |
| **Bulk Upsert Accounts** | Bulk upsert sales accounts                            |
| **Clone Account**        | Clone a sales account                                 |
| **Delete Account**       | Delete a sales account                                |
| **Forget Account**       | Permanently forget a sales account                    |
| **Bulk Delete Accounts** | Bulk delete sales accounts                            |
| **List Account Fields**  | List all account fields, including custom fields      |

### Deals

| Tool                  | Description                                   |
| --------------------- | --------------------------------------------- |
| **List Deal Filters** | List available deal views and filters         |
| **Create Deal**       | Create a deal                                 |
| **Get Deal**          | Get one deal by ID                            |
| **List Deals**        | List deals from a view                        |
| **Update Deal**       | Update a deal                                 |
| **Update Deal Team**  | Replace or update team members for a deal     |
| **Upsert Deal**       | Create or update a deal using the upsert API  |
| **Bulk Upsert Deals** | Bulk upsert deals                             |
| **Clone Deal**        | Clone a deal                                  |
| **Delete Deal**       | Delete a deal                                 |
| **Forget Deal**       | Permanently forget a deal                     |
| **Bulk Delete Deals** | Bulk delete deals                             |
| **List Deal Fields**  | List all deal fields, including custom fields |

### Marketing Lists

| Tool                                      | Description                                      |
| ----------------------------------------- | ------------------------------------------------ |
| **Create Marketing List**                 | Create a marketing list                          |
| **List Marketing Lists**                  | List marketing lists                             |
| **Update Marketing List**                 | Update a marketing list                          |
| **List Contacts in Marketing List**       | List contacts in a marketing list                |
| **Copy Contacts to Marketing List**       | Copy specific contacts into a marketing list     |
| **Remove Contacts from Marketing List**   | Remove contacts from a marketing list            |
| **Move Contacts Between Marketing Lists** | Move contacts from one marketing list to another |

### Notes, Tasks, and Appointments

| Tool                   | Description                 |
| ---------------------- | --------------------------- |
| **Create Note**        | Create a note               |
| **Update Note**        | Update a note               |
| **Delete Note**        | Delete a note               |
| **Create Task**        | Create a task               |
| **Get Task**           | Get one task by ID          |
| **List Tasks**         | List tasks by filter        |
| **Update Task**        | Update a task               |
| **Mark Task Done**     | Mark a task as done         |
| **Delete Task**        | Delete a task               |
| **Create Appointment** | Create an appointment       |
| **Get Appointment**    | Get one appointment by ID   |
| **List Appointments**  | List appointments by filter |
| **Update Appointment** | Update an appointment       |
| **Delete Appointment** | Delete an appointment       |

### Sales Activities

| Tool                           | Description                    |
| ------------------------------ | ------------------------------ |
| **Create Sales Activity**      | Create a sales activity        |
| **Get Sales Activity**         | Get one sales activity by ID   |
| **List Sales Activities**      | List sales activities          |
| **List Sales Activity Fields** | List all sales activity fields |
| **Update Sales Activity**      | Update a sales activity        |
| **Delete Sales Activity**      | Delete a sales activity        |

### Search and Lookup

| Tool                      | Description                                |
| ------------------------- | ------------------------------------------ |
| **Search Records**        | Search records across selected entities    |
| **Lookup Records**        | Lookup records by one field and entity set |
| **Create Phone Call Log** | Create a manual phone call log             |

### CPQ Products

| Tool                          | Description                      |
| ----------------------------- | -------------------------------- |
| **Create Product**            | Create a CPQ product             |
| **Get Product**               | Get one CPQ product by ID        |
| **Update Product**            | Update a CPQ product             |
| **Bulk Update Products**      | Bulk update CPQ products         |
| **Bulk Assign Product Owner** | Bulk assign CPQ product owners   |
| **Delete Product**            | Delete a CPQ product             |
| **Restore Product**           | Restore a deleted CPQ product    |
| **Bulk Delete Products**      | Bulk delete CPQ products         |
| **Bulk Restore Products**     | Bulk restore CPQ products        |
| **Add Product Prices**        | Add prices to a CPQ product      |
| **Update Product Prices**     | Update prices on a CPQ product   |
| **Delete Product Prices**     | Delete prices from a CPQ product |
| **Add Products to Deal**      | Set products on a deal           |
| **Update Products on Deal**   | Replace products on a deal       |
| **Delete Products from Deal** | Delete all products from a deal  |

### CPQ Documents

| Tool                              | Description                             |
| --------------------------------- | --------------------------------------- |
| **Create Document**               | Create a CPQ document                   |
| **Get Document**                  | Get one CPQ document by ID              |
| **Update Document**               | Update a CPQ document                   |
| **Bulk Update Documents**         | Bulk update CPQ documents               |
| **Bulk Assign Document Owner**    | Bulk assign CPQ document owners         |
| **Delete Document**               | Delete a CPQ document                   |
| **Restore Document**              | Restore a deleted CPQ document          |
| **Bulk Delete Documents**         | Bulk delete CPQ documents               |
| **Bulk Restore Documents**        | Bulk restore CPQ documents              |
| **Forget Document**               | Permanently forget a CPQ document       |
| **Add Products to Document**      | Set products on a CPQ document          |
| **Update Products on Document**   | Replace products on a CPQ document      |
| **Delete Products from Document** | Delete all products from a CPQ document |
| **Get Related Products**          | Get related products for a CPQ document |

### Files and Links

| Tool                             | Description                                                |
| -------------------------------- | ---------------------------------------------------------- |
| **Create File**                  | Upload a file to Freshsales and associate it with a record |
| **Create Link**                  | Create a document link and associate it with a record      |
| **List Contact Files and Links** | List files and links associated with a contact             |
| **Get Job Status**               | Get background job status by ID                            |

### Custom Modules

| Tool                                  | Description                                           |
| ------------------------------------- | ----------------------------------------------------- |
| **Create Custom Module**              | Create a custom module                                |
| **Get Custom Module**                 | Get one custom module definition                      |
| **Update Custom Module**              | Update a custom module definition                     |
| **Delete Custom Module**              | Delete a custom module definition                     |
| **Create Custom Field**               | Create a field on a standard or custom module form    |
| **List Custom Module Fields**         | List forms and fields for a custom module entity type |
| **List Custom Module Filters**        | List available views and filters for a custom module  |
| **Create Custom Module Record**       | Create a record in a custom module                    |
| **Get Custom Module Record**          | Get one custom module record                          |
| **List Custom Module Records**        | List custom module records                            |
| **Update Custom Module Record**       | Update a custom module record                         |
| **Delete Custom Module Record**       | Delete a custom module record                         |
| **Forget Custom Module Record**       | Permanently forget a custom module record             |
| **Clone Custom Module Record**        | Clone a custom module record                          |
| **Bulk Delete Custom Module Records** | Bulk delete custom module records                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search contacts:**

```text theme={"dark"}
Find all contacts at Acme Corp
```

**Create a deal:**

```text theme={"dark"}
Create a deal called "Enterprise License" for $50,000 associated with the Acme account
```

**List tasks:**

```text theme={"dark"}
Show me all open tasks due this week
```

**Update a contact:**

```text theme={"dark"}
Update the contact with email john@acme.com to set the lifecycle stage to "customer"
```

**Manage marketing lists:**

```text theme={"dark"}
Add all contacts from the "Q4 Leads" list to the "Newsletter" marketing list
```

**Search across entities:**

```text theme={"dark"}
Search for "renewal" across contacts, deals, and accounts
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific names, emails, or record IDs                                                                                           |
| Action not completing            | Check that you've authenticated with Freshsales                                                                                     |
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

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Freshsales MCP server](https://www.gumloop.com/mcp/freshsales) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
