> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Snowflake

> Query your data warehouse with AI-powered SQL automation.

Snowflake is a cloud data platform for analytics and data warehousing. The Snowflake MCP server lets you run SQL queries and inspect table schemas using natural language.

## What Can It Do?

* **Run SQL queries** and return structured results
* **Describe table schemas** to understand your data
* **Bulk load data** into tables via staging
* **Tag queries** for tracking in Snowflake's query history
* **Power analytics workflows** with live data

## Where to Use It

### In Agents (Recommended)

Add Snowflake as a tool to any agent. The agent can then query your data warehouse conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Snowflake tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Query orders from last month")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool               | Description                                                                                                                                                    |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Describe Table** | Get table structure and columns                                                                                                                                |
| **Execute Query**  | Run a SQL query. Automatically injects a structured QUERY\_TAG (including user\_email and agent\_id) on every call for tracking in Snowflake's QUERY\_HISTORY. |
| **Stage Data**     | Bulk load data into a table via staging                                                                                                                        |

<Note>
  Execute Query rejects Snowflake's client-side file transfer commands (`GET` and `PUT`). Use **Stage Data** to load data into a table.
</Note>

## Example Prompts

Use these with your agent or in the Agent Node:

**Query data:**

```text theme={"dark"}
Get all orders from last month with total over $1000
```

**Check table schema:**

```text theme={"dark"}
What columns are in the customers table?
```

**Aggregate data:**

```text theme={"dark"}
Show me daily revenue for the past 30 days
```

**Run custom SQL:**

```text theme={"dark"}
Run SELECT customer_id, SUM(amount) FROM orders GROUP BY customer_id
```

**Run a tagged query:**

```text theme={"dark"}
Run a query to get daily active users, tagged with "team:growth" for tracking
```

**Bulk load data:**

```text theme={"dark"}
Stage and load this CSV data into the customers table
```

## Configuration Options

| Option               | Description                                                                                                                       | Default |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------- | ------- |
| **`ocsp_fail_open`** | Allow connection when OCSP certificate validation fails. Enable if encountering certificate validation errors with large queries. | `false` |
| **`insecure_mode`**  | Skip OCSP certificate revocation checking entirely. Enable if encountering error `254007` on large result set downloads.          | `false` |

<Warning>
  Only enable `insecure_mode` if you are experiencing OCSP-related errors (e.g., error code 254007) when downloading large result sets. This disables certificate revocation checks on S3 result retrieval.
</Warning>

## Troubleshooting

| Issue                            | Solution                                                                                                                                     |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify schema and table names clearly                                                                                                       |
| Action not completing            | Check that you've authenticated with Snowflake                                                                                               |
| Unexpected results               | The agent may chain multiple tools (e.g., describing a table first, then querying). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                          |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Query the sales table" will describe it first to understand the schema, then run the query. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Snowflake MCP server](https://www.gumloop.com/mcp/snowflake) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
