> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sigma Computing

> Manage workbooks, members, teams, and data connections with AI-powered analytics automation.

Sigma Computing is a cloud analytics platform that lets teams explore and visualize data using a spreadsheet-like interface. The Sigma Computing MCP server lets you manage workbooks, members, teams, workspaces, and data connections using natural language.

## What Can It Do?

* **Manage workbooks** including creation, duplication, export, and permissions
* **Organize members and teams** with invitations, role updates, and team assignments
* **Control workspaces** with creation, permissions, and grants
* **Monitor data connections** and test connectivity

## Where to Use It

### In Agents (Recommended)

Add Sigma Computing as a tool to any agent. The agent can then manage your analytics environment conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Sigma Computing tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all workbooks in my workspace")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Workbook Tools

| Tool                             | Description                                                                       |
| -------------------------------- | --------------------------------------------------------------------------------- |
| **Get Current User**             | Get the identity and authentication status of the current user                    |
| **List Workbooks**               | List workbooks with optional pagination                                           |
| **Get Workbook**                 | Get workbook details by ID                                                        |
| **Create Workbook**              | Create a new workbook                                                             |
| **Duplicate Workbook**           | Create a copy of an existing workbook, optionally with a new name and description |
| **List Workbook Pages**          | List pages in a workbook                                                          |
| **List Workbook Elements**       | List all elements in a workbook                                                   |
| **Get Workbook Schema**          | Get the schema of a workbook including columns and element structure              |
| **Get Workbook Sources**         | Get data sources connected to a workbook                                          |
| **List Workbook Queries**        | List SQL queries in a workbook                                                    |
| **Export Workbook**              | Trigger a data export from a workbook as CSV, XLSX, JSON, JSONL, PDF, or PNG      |
| **Download Workbook Export**     | Download a previously exported workbook file to Gumloop storage                   |
| **Send Workbook**                | Email a workbook export, optionally scoped to a single page or element            |
| **Grant Workbook Permission**    | Grant a member or team access to a workbook                                       |
| **Tag Workbook**                 | Apply a version tag to a published workbook version                               |
| **Get Workbook Version History** | Get the version history of a workbook                                             |

### Member Tools

| Tool                  | Description                                          |
| --------------------- | ---------------------------------------------------- |
| **List Members**      | List organization members with search and pagination |
| **Get Member**        | Get member details by ID                             |
| **Create Member**     | Invite a new member to the organization              |
| **Update Member**     | Update a member's account type or profile            |
| **Deactivate Member** | Deactivate a member from the organization            |
| **List Member Teams** | List teams that a member belongs to                  |

### Team Tools

| Tool                    | Description                                      |
| ----------------------- | ------------------------------------------------ |
| **List Teams**          | List all teams in the organization               |
| **Get Team**            | Get team details by ID                           |
| **Create Team**         | Create a new team                                |
| **Update Team**         | Update a team's name, description, or visibility |
| **Delete Team**         | Delete a team permanently                        |
| **List Team Members**   | List members of a team                           |
| **Update Team Members** | Add or remove members from a team                |

### Workspace Tools

| Tool                           | Description                                  |
| ------------------------------ | -------------------------------------------- |
| **List Workspaces**            | List workspaces with optional name filter    |
| **Get Workspace**              | Get workspace details by ID                  |
| **Create Workspace**           | Create a new workspace                       |
| **Update Workspace**           | Update a workspace's name or description     |
| **Delete Workspace**           | Delete a workspace                           |
| **List Workspace Grants**      | List permission grants for a workspace       |
| **Grant Workspace Permission** | Grant a member or team access to a workspace |

### Connection and Template Tools

| Tool                              | Description                                                                            |
| --------------------------------- | -------------------------------------------------------------------------------------- |
| **List Connections**              | List data connections with optional search and archived filter                         |
| **Get Connection**                | Get connection details by ID                                                           |
| **Test Connection**               | Test if a data connection is active and working                                        |
| **Lookup Connection Path**        | Look up a database/schema/table path within a connection                               |
| **List Templates**                | List available workbook templates                                                      |
| **Get Template**                  | Get template details by ID                                                             |
| **Create Workbook From Template** | Create a new workbook from a template, optionally keeping it in sync with the template |
| **List Account Types**            | List all account types in the organization                                             |
| **List User Attributes**          | List custom user attributes and their values                                           |

## Exporting Workbooks

Exports run in two steps: **Export Workbook** queues the export and returns a query ID, then **Download Workbook Export** saves the file to Gumloop storage.

Exports are asynchronous, so a download can return `{"status": "processing"}` if the export isn't ready yet. Retry the download with the same query ID until it returns `{"status": "complete"}` along with the filename.

Format options:

| Option                         | Applies to | Description                                                   |
| ------------------------------ | ---------- | ------------------------------------------------------------- |
| `file_type`                    | All        | `CSV` (default), `XLSX`, `JSON`, `JSONL`, `PDF`, or `PNG`     |
| `layout`                       | PDF        | `portrait` (default) or `landscape`                           |
| `pixel_width` / `pixel_height` | PNG        | Image dimensions                                              |
| `page_id` / `element_id`       | All        | Export a single page or element instead of the whole workbook |

**Send Workbook** takes the same `file_type`, `layout`, `page_id`, and `element_id` options for the attachment it emails.

<Info>
  Tagging uses version tag names rather than tag IDs. Pass the tag name plus the published `workbook_version` to tag, and set `is_default` to make that version the default.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**List workbooks:**

```text theme={"dark"}
Show me all workbooks in my Sigma Computing account
```

**Export data:**

```text theme={"dark"}
Export the Q4 Sales Dashboard workbook as a landscape PDF, then download it
```

**Email an export:**

```text theme={"dark"}
Email the Revenue page of the Q4 Sales Dashboard as an XLSX attachment to finance@company.com
```

**Tag a version:**

```text theme={"dark"}
Tag version 4 of the Q4 Sales Dashboard as "production" and make it the default
```

**Manage teams:**

```text theme={"dark"}
Create a new team called "Data Analysts" and add jane@company.com
```

**Check connections:**

```text theme={"dark"}
Test if the Snowflake data connection is working
```

**Create from template:**

```text theme={"dark"}
Create a new workbook from the Monthly Report template
```

## Troubleshooting

| Issue                                | Solution                                                                                                                                           |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data     | Use specific workbook names or IDs                                                                                                                 |
| Action not completing                | Check that you've authenticated with Sigma Computing                                                                                               |
| Permission denied                    | Ensure your account has the required admin or editor role                                                                                          |
| Export download returns "processing" | The export is still running. Retry the download with the same query ID.                                                                            |
| Unexpected results                   | The agent may chain multiple tools (e.g., listing workbooks first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available                   | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Share the sales dashboard with the analytics team" will find the workbook first, then grant permission. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Sigma Computing MCP server](https://www.gumloop.com/mcp/sigma-computing) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
