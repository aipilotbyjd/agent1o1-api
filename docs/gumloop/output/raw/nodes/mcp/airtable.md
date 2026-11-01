> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Airtable

> Search, create, and manage Airtable records with AI-powered automation.

Airtable is a flexible database platform that combines spreadsheet simplicity with database power. The Airtable MCP server lets you search, filter, create, and update records using natural language.

## What Can It Do?

* **Retrieve records** with filters, sorts, and selected fields
* **Create and update** records to keep your tables current
* **Explore your workspace** by listing bases, tables, and schema
* **Manage structure** by creating and modifying fields
* **Manage comments** on records — create, update, and delete

## Where to Use It

### In Agents (Recommended)

Add Airtable as a tool to any agent. The agent can then interact with your bases conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Airtable tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List records from table X where status is Active")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool               | Description                                                   |
| ------------------ | ------------------------------------------------------------- |
| **List Records**   | Retrieve records with filtering, sorting, and field selection |
| **Create Records** | Create new records in a table                                 |
| **Update Records** | Update existing records                                       |
| **Get Record**     | Get a single record by ID                                     |
| **Delete Records** | Delete one or more records                                    |
| **List Bases**     | List all accessible bases                                     |
| **List Tables**    | List all tables in a base                                     |
| **Base Schema**    | Get detailed schema for all tables                            |
| **Create Table**   | Create a new table in a base with specified fields and types  |
| **Update Table**   | Update a table's name or description                          |
| **Create Field**   | Add a new field to a table                                    |
| **Update Field**   | Update a field's metadata                                     |
| **List Comments**  | List comments for a record                                    |
| **Create Comment** | Create a comment on a specific record                         |
| **Update Comment** | Update an existing comment on a record                        |
| **Delete Comment** | Delete a comment from a record                                |

## Example Prompts

Use these with your agent or in the Agent Node:

**Discover your workspace:**

```text theme={"dark"}
List all my Airtable bases and their tables
```

**Find specific records:**

```text theme={"dark"}
Get all records from the Projects table where Status is "In Progress"
```

**Create new data:**

```text theme={"dark"}
Add a new record to the Tasks table with Name "Review Q4 report" and Due Date "2024-12-15"
```

**Update records:**

```text theme={"dark"}
Update the record for "Project Alpha" to set Status to "Complete"
```

**Explore schema:**

```text theme={"dark"}
Show me all the fields in the Customers table and their types
```

**Add a comment:**

```text theme={"dark"}
Add a comment "Needs review" to the first record in the Tasks table
```

**Delete a comment:**

```text theme={"dark"}
Delete my last comment on the record for "Project Alpha"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                 |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Be more specific with table names and filter conditions                                                                                  |
| Action not completing            | Check that you've authenticated and have write permissions                                                                               |
| Unexpected results               | The agent may chain multiple tools (e.g., listing tables first, then querying). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                      |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find the project named Marketing Campaign" will automatically list bases, find the right table, then search for the record. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Airtable MCP server](https://www.gumloop.com/mcp/airtable) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
