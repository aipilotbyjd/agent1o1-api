> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Tableau

> Access dashboards and analytics with AI-powered business intelligence automation.

Tableau is a leading business intelligence platform for data visualization. The Tableau MCP server lets you list workbooks, export views, and access Pulse metrics using natural language.

## What Can It Do?

* **List workbooks and views** across your site
* **Export view data** as CSV or images
* **Access Tableau Pulse** metrics and AI insights
* **Search content** across your organization
* **Query datasources directly** using VizQL Data Service with date aggregations, binning, field aliases, and advanced filtering

## Where to Use It

### In Agents (Recommended)

Add Tableau as a tool to any agent. The agent can then access your dashboards conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Tableau tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all workbooks in the Finance project")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Setting Up Credentials

Tableau uses Personal Access Tokens (PAT) for authentication. Generate a token in your Tableau account settings, then add it to your [Connectors page](https://www.gumloop.com/personal/connectors).

## Available Tools

| Tool                                  | Description                                                                                                                                                                         |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **List Views**                        | List all views on a site                                                                                                                                                            |
| **Get View Data**                     | Export view data as CSV                                                                                                                                                             |
| **Get View Image**                    | Export view as PNG                                                                                                                                                                  |
| **List Workbooks**                    | List all workbooks                                                                                                                                                                  |
| **Get Workbook**                      | Get workbook details                                                                                                                                                                |
| **List Datasources**                  | List published data sources                                                                                                                                                         |
| **Search Content**                    | Search across all content                                                                                                                                                           |
| **List All Pulse Metric Definitions** | List Pulse metrics                                                                                                                                                                  |
| **Generate Pulse Insight Bundle**     | Get AI insights                                                                                                                                                                     |
| **Get Datasource Metadata**           | Get field metadata from a published datasource using VizQL Data Service                                                                                                             |
| **Query Datasource**                  | Query data directly from a published datasource with custom fields, filters, aggregations, date grouping (YEAR/QUARTER/MONTH/WEEK/DAY), binning, field aliases, and context filters |

## Example Prompts

Use these with your agent or in the Agent Node:

**List workbooks:**

```text theme={"dark"}
Show me all workbooks in the Finance project
```

**Export data:**

```text theme={"dark"}
Get the data from the Sales Dashboard view as CSV
```

**Get dashboard image:**

```text theme={"dark"}
Export an image of the Executive Summary dashboard
```

**Search content:**

```text theme={"dark"}
Search for workbooks and views containing "revenue"
```

**Get Pulse insights:**

```text theme={"dark"}
Generate AI insights for the monthly sales metric
```

**Get datasource metadata:**

```text theme={"dark"}
Show me all available fields in the Sales datasource
```

**Query datasource directly:**

```text theme={"dark"}
Query the Sales datasource for total revenue by region, sorted by revenue descending
```

**Query with date aggregation:**

```text theme={"dark"}
Query the Orders datasource for monthly order count grouped by MONTH, filtered to the last 6 months
```

**Query with binning:**

```text theme={"dark"}
Query the Sales datasource for revenue distribution using bins of size 1000
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use exact workbook or view names                                                                                                                 |
| Action not completing            | Check that your Personal Access Token is valid                                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., listing workbooks first, then getting views). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                              |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Export the Finance dashboard" will find the view first, then export it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Tableau MCP server](https://www.gumloop.com/mcp/tableau) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
