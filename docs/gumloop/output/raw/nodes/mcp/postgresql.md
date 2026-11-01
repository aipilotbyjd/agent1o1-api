> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# PostgreSQL

> Query and manage PostgreSQL databases with AI-powered schema exploration and SQL execution.

PostgreSQL is a powerful open-source relational database. The PostgreSQL MCP server lets you explore schemas, execute SQL queries, and analyze query plans using natural language.

## What Can It Do?

* **Explore database schemas** and table structures
* **Execute SQL queries** and view results
* **Analyze query performance** with execution plans
* **Browse tables, views, and sequences** across schemas

## Where to Use It

### In Agents (Recommended)

Add PostgreSQL as a tool to any agent. The agent can then interact with PostgreSQL conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with PostgreSQL tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all tables in the database")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                                                          |
| ---------------------- | -------------------------------------------------------------------- |
| **List Schemas**       | List all schemas with their owners                                   |
| **List Objects**       | List tables, views, sequences, or extensions in a schema             |
| **Get Object Details** | Get detailed information about a table, view, sequence, or extension |
| **Execute Sql**        | Execute any SQL query and return results                             |
| **Explain Query**      | Show query execution plan with costs and strategy                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Explore schema:**

```text theme={"dark"}
List all tables in the public schema
```

**Run a query:**

```text theme={"dark"}
Show me the top 10 customers by order count
```

**Analyze performance:**

```text theme={"dark"}
Explain the query plan for selecting from the orders table
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your PostgreSQL credentials and that you have the required permissions                                       |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |
| Unexpected results    | The agent may chain multiple tools together. Review the agent's reasoning to understand its approach.               |

<Tip>
  Agents are smart enough to chain multiple API calls together. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [PostgreSQL MCP server](https://www.gumloop.com/mcp/postgresql) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
