> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Notion

> Search and query your workspace with AI-powered knowledge management automation.

Notion is an all-in-one workspace for notes, databases, and collaboration. The Notion MCP server lets you search, create, update, and query pages and databases using natural language.

## What Can It Do?

* **Search pages** by keyword across your workspace
* **Create and update pages** as sub-pages or database entries with markdown content
* **Create and update databases** with custom property schemas
* **Query databases** with filters to get specific rows
* **Manage data sources** within databases (list, inspect, and update schemas)
* **Retrieve page content** including blocks and properties
* **List users and databases** for workspace discovery
* **Comment on pages and blocks** with threaded discussions
* **Manipulate blocks** by appending, updating, or deleting content

## Where to Use It

### In Agents (Recommended)

Add Notion as a tool to any agent. The agent can then search and retrieve your Notion data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Notion tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for pages containing 'roadmap'")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Read Tools

| Tool                   | Description                                                                     |
| ---------------------- | ------------------------------------------------------------------------------- |
| **Search Pages**       | Search all pages by keyword (supports pagination for large result sets)         |
| **List All Pages**     | List pages across databases                                                     |
| **Get Page**           | Retrieve a page by ID or URL                                                    |
| **List Databases**     | List all databases                                                              |
| **Get Database**       | Get database properties and schema                                              |
| **Query Database**     | Query with filters                                                              |
| **Get Block Children** | List content blocks                                                             |
| **Get Block**          | Get a single block                                                              |
| **List All Users**     | List workspace users                                                            |
| **Get User**           | Get user details                                                                |
| **List Data Sources**  | List all data sources under a database, each with its own schema and properties |
| **Get Data Source**    | Retrieve a specific data source by ID to inspect its schema                     |
| **List Comments**      | List comments on a page or block                                                |

### Write Tools

| Tool                   | Description                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------ |
| **Create Page**        | Create a new page as a sub-page or database entry with properties, markdown content, or block children |
| **Update Page**        | Update page properties, title, icon, cover, or archive/trash status                                    |
| **Create Database**    | Create a new database with custom property schema under an existing page                               |
| **Update Database**    | Update database title, description, icon, cover, or trash/lock status                                  |
| **Update Data Source** | Update a data source's property schema, title, description, icon, or trash status                      |
| **Create Comment**     | Create a comment on a page, block, or reply to an existing discussion thread                           |
| **Append Blocks**      | Append child blocks to a page or block (supports up to 100 blocks and two levels of nesting)           |
| **Update Block**       | Update an existing block's content (paragraph text, to-do status, callout icons, etc.)                 |
| **Delete Block**       | Archive (soft-delete) a block by ID                                                                    |

### File Tools

| Tool                  | Description                                                                                                                       |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **Upload File**       | Upload a file from Gumloop storage to Notion and get a file upload ID you can attach to blocks, page properties, icons, or covers |
| **Download File**     | Download the file attached to a file, image, PDF, audio, or video block into Gumloop storage                                      |
| **List File Uploads** | List file uploads created by this integration, most recent first                                                                  |

<Info>
  Upload File takes a file that already lives in Gumloop storage, so pair it with a file input or an earlier node that writes the file. The upload ID it returns is what you pass to Create Page, Update Page, or Append Blocks to actually attach the file. Notion enforces its own upload size limit based on your workspace plan.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Search pages:**

```text theme={"dark"}
Find pages containing "product roadmap"
```

**Query a database:**

```text theme={"dark"}
Get all tasks from the Sprint database where status is "In Progress"
```

**Get page content:**

```text theme={"dark"}
What's in the Q4 Planning page?
```

**List databases:**

```text theme={"dark"}
Show me all databases in my Notion workspace
```

**Check database schema:**

```text theme={"dark"}
What properties does the Projects database have?
```

**Create a page:**

```text theme={"dark"}
Create a new page called "Meeting Notes" under the Team Wiki page with today's meeting agenda in markdown
```

**Create a database entry:**

```text theme={"dark"}
Add a new task to the Sprint database with title "Fix login bug", status "In Progress", and priority "High"
```

**Create a database:**

```text theme={"dark"}
Create a new database called "Project Tracker" with columns for Name, Status, Priority, Due Date, and Assignee
```

**Update a page:**

```text theme={"dark"}
Archive the Q3 Planning page and remove its cover image
```

**List data sources:**

```text theme={"dark"}
List all data sources under the Projects database and show their schemas
```

**Update a data source schema:**

```text theme={"dark"}
Add a "Priority" select column with options High, Medium, and Low to this data source
```

**Add a comment:**

```text theme={"dark"}
Add a comment saying "Looks good to me!" on the Q4 Planning page
```

**List comments:**

```text theme={"dark"}
Show me all comments on the Product Roadmap page
```

**Append blocks:**

```text theme={"dark"}
Add a callout block with a lightbulb emoji and text "Remember to update the timeline" to the Sprint Planning page
```

**Update a block:**

```text theme={"dark"}
Mark the first to-do item as completed on my Tasks page
```

**Delete a block:**

```text theme={"dark"}
Remove the outdated note block from the Meeting Notes page
```

**Upload a file:**

```text theme={"dark"}
Upload report.pdf to Notion and attach it to the Q4 Planning page
```

**Download a file:**

```text theme={"dark"}
Download the PDF attached to this Notion block into Gumloop storage
```

## Troubleshooting

| Issue                               | Solution                                                                                                                                                                                        |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data    | Use specific page titles or database names                                                                                                                                                      |
| Action not completing               | Check that you've authenticated and the page is shared with the integration                                                                                                                     |
| Unexpected results                  | The agent may chain multiple tools (e.g., searching first, then getting content). Review the agent's reasoning to understand its approach.                                                      |
| Tool not available                  | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                                                             |
| File upload rejected                | The file exceeds the maximum upload size for your Notion plan, or it isn't in the Gumloop storage location the tool is reading from (user storage by default, workspace storage when specified) |
| Uploaded file not visible in Notion | Uploading only stages the file. Attach the returned file upload ID to a block, page property, icon, or cover.                                                                                   |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the meeting notes from last week" will search first, then retrieve the content. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Notion MCP server](https://www.gumloop.com/mcp/notion) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
