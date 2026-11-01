> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Excel

> Manage Microsoft 365 Excel workbooks with AI-powered spreadsheet automation.

Microsoft Excel in Microsoft 365 is a powerful spreadsheet application for data management and analysis. The Excel MCP server lets you read, write, and manage workbooks in OneDrive and SharePoint using natural language.

## What Can It Do?

* **Create and search workbooks** in OneDrive and SharePoint, including SharePoint document libraries
* **Manage worksheets** by adding, listing, and updating
* **Read and write data** to cells, rows, and tables
* **Copy workbooks** into another folder or drive
* **Download workbooks** for sharing and backup

## Where to Use It

### In Agents (Recommended)

Add Excel as a tool to any agent. The agent can then interact with your spreadsheets conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Excel tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Read data from the Sales worksheet")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                                                                |
| ------------------------ | -------------------------------------------------------------------------- |
| **Create Workbook**      | Create a new Excel workbook                                                |
| **Search Workbooks**     | Search workbooks in OneDrive/SharePoint                                    |
| **Copy File**            | Copy a workbook to a new file, optionally into a different folder or drive |
| **Download Workbook**    | Get a download URL                                                         |
| **List Worksheets**      | List all worksheets                                                        |
| **Add Worksheet**        | Add a new worksheet                                                        |
| **Read Worksheet**       | Read data from a range                                                     |
| **Update Cells**         | Update cell values                                                         |
| **Search Data**          | Get data from a range                                                      |
| **Add Row**              | Append a row                                                               |
| **Find Row**             | Find a row by value                                                        |
| **Find Or Create Row**   | Find or create a row                                                       |
| **Update Row**           | Update a row                                                               |
| **Delete Worksheet Row** | Delete a row                                                               |
| **List Tables**          | List all tables                                                            |
| **Get Table**            | Get table metadata                                                         |
| **Add Table**            | Create a table                                                             |
| **List Table Rows**      | List rows in a table                                                       |
| **Add Table Row**        | Add a row to a table                                                       |
| **Add Table Column**     | Add a column                                                               |
| **Update Table Column**  | Update column data                                                         |
| **Delete Table**         | Delete a table                                                             |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a workbook:**

```text theme={"dark"}
Create a new workbook called "Q4 Budget" in my OneDrive
```

**Read data:**

```text theme={"dark"}
Read the data from cells A1 to D50 in the Sales worksheet
```

**Update cells:**

```text theme={"dark"}
Update cell C10 to "Closed Won" in the Deals workbook
```

**Add data:**

```text theme={"dark"}
Add a new row with "Acme Inc", 25000, and "Pending" to the Sales table
```

**Find a record:**

```text theme={"dark"}
Find the row where Company Name is "TechCorp"
```

**Copy a workbook:**

```text theme={"dark"}
Copy the "Q4 Budget" workbook into the Archive folder as "Q4 Budget (final)"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific workbook names or provide the OneDrive/SharePoint path                                                                              |
| Action not completing            | Check that you've authenticated with Microsoft 365 and have access to the file                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., listing worksheets first, then reading data). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                              |

<Note>
  Copying a workbook is asynchronous. Copy File returns an accepted status with a monitor URL, and the copy appears in the destination folder shortly after.
</Note>

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the Acme row in the Sales table" will find the row first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Excel MCP server](https://www.gumloop.com/mcp/excel) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
