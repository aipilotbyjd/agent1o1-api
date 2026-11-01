> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Sheets

> Read and write spreadsheet data with AI-powered automation.

Google Sheets is Google's cloud-based spreadsheet application for data management and collaboration. The Google Sheets MCP server lets you read, write, and manipulate spreadsheet data using natural language.

## What Can It Do?

* **Read spreadsheet data** from any range or entire sheets
* **Write and update cells** with new values or formulas
* **Create and manage sheets** within spreadsheets
* **Format cells** with bold, colors, font size, number formats, and alignment
* **Manage rows and columns** — insert or delete as needed
* **Sort and find-replace** data across ranges
* **Create charts** — bar, line, pie, scatter, area, and column charts from data
* **Search and filter data** based on conditions

## Where to Use It

### In Agents (Recommended)

Add Google Sheets as a tool to any agent. The agent can then work with your spreadsheets conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Sheets tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Read the sales data from column A")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                                                                                          |
| ------------------------ | ---------------------------------------------------------------------------------------------------- |
| **Create Sheet**         | Create a new Google Sheets document, optionally inside a specific Drive folder (by folder ID or URL) |
| **Add Sheet**            | Add a new sheet tab to an existing spreadsheet                                                       |
| **Get Spreadsheet Info** | Retrieve spreadsheet metadata (title, sheets, etc.)                                                  |
| **Get Sheet Names**      | List all sheet tab names in a spreadsheet                                                            |
| **Batch Get**            | Read values from multiple ranges                                                                     |
| **Batch Update**         | Write values to multiple ranges                                                                      |
| **Append Values**        | Append values to the end of a range (like inserting rows)                                            |
| **Lookup Row**           | Search for a row by column value in a specified range                                                |
| **Clear Values**         | Clear values from a given range                                                                      |
| **Copy Sheet**           | Copy a sheet from one spreadsheet to another                                                         |
| **Format Cells**         | Apply formatting to a cell range (bold, colors, font size, number format, alignment)                 |
| **Manage Rows/Columns**  | Insert or delete rows and columns in a sheet                                                         |
| **Manage Sheet**         | Rename or delete a sheet tab in a spreadsheet                                                        |
| **Sort Range**           | Sort a range of cells by one or more columns                                                         |
| **Find Replace**         | Find and replace values in a spreadsheet                                                             |
| **Add Chart**            | Create a chart (bar, line, pie, scatter, area, column) from a data range                             |

## Example Prompts

Use these with your agent or in the Agent Node:

**Read data:**

```text theme={"dark"}
Read all data from the Sales sheet in my Q4 Report spreadsheet
```

**Write data:**

```text theme={"dark"}
Update cell B2 in the inventory sheet to 150
```

**Append rows:**

```text theme={"dark"}
Add a new row with "John", "Sales", "2024-01-15" to the employee sheet
```

**Search:**

```text theme={"dark"}
Find all rows where the status column says "Pending"
```

**Get info:**

```text theme={"dark"}
What sheets are in my Budget spreadsheet?
```

**Format cells:**

```text theme={"dark"}
Make the header row bold with a blue background in my Sales sheet
```

**Sort data:**

```text theme={"dark"}
Sort the data in my inventory sheet by price from highest to lowest
```

**Find and replace:**

```text theme={"dark"}
Replace all instances of "TBD" with "Confirmed" in the Events sheet
```

**Create a chart:**

```text theme={"dark"}
Create a bar chart from the revenue data in columns A through D
```

## Troubleshooting

| Issue                            | Solution                                                                                                                         |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify the spreadsheet name and sheet tab clearly                                                                               |
| Action not completing            | Check that you've authenticated and have edit access                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., reading first, then writing). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)              |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the total in my budget sheet" will read the current data first, then update. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Sheets MCP server](https://www.gumloop.com/mcp/gsheets) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
