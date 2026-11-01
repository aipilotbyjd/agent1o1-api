> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Databricks

> Manage data engineering and ML operations with AI-powered workspace automation.

Databricks is a unified analytics platform for data engineering, data science, and machine learning. The Databricks MCP server lets you manage clusters, run jobs, execute SQL, and query ML endpoints using natural language.

## What Can It Do?

* **Manage clusters** by listing, starting, and terminating on demand
* **Orchestrate jobs** by triggering runs and fetching outputs
* **Run SQL** on warehouses and return structured data
* **Query ML endpoints** and vector indexes for AI workflows

## Where to Use It

### In Agents (Recommended)

Add Databricks as a tool to any agent. The agent can then interact with your workspace conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Databricks tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all active clusters")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                             | Description                         |
| -------------------------------- | ----------------------------------- |
| **Get Me**                       | Get authenticated user information  |
| **List Clusters**                | List all pinned and active clusters |
| **Start Cluster**                | Start a terminated cluster          |
| **Terminate Cluster**            | Terminate a running cluster         |
| **List Jobs**                    | List jobs with pagination           |
| **Run Job**                      | Trigger a new job run               |
| **Manage Job Run**               | Cancel or delete a job run          |
| **Get Job Run Output**           | Get output from a job run           |
| **Execute SQL**                  | Run SQL on a warehouse              |
| **List Warehouses**              | List all SQL warehouses             |
| **Query Serving Endpoint**       | Query a model serving endpoint      |
| **List Serving Endpoints**       | List all serving endpoints          |
| **Query Vector Index**           | Query a vector index                |
| **List Vector Search Endpoints** | List vector search endpoints        |

## Example Prompts

Use these with your agent or in the Agent Node:

**Manage clusters:**

```text theme={"dark"}
List all my clusters and their current status
```

**Start compute:**

```text theme={"dark"}
Start the cluster named "analytics-cluster"
```

**Run a job:**

```text theme={"dark"}
Trigger the daily ETL job and return the run ID
```

**Execute SQL:**

```text theme={"dark"}
Run "SELECT * FROM sales WHERE region = 'West'" on the main warehouse
```

**Query ML endpoint:**

```text theme={"dark"}
Query the fraud-detection endpoint with this transaction data
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                  |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific cluster or job names                                                                                                         |
| Action not completing            | Check that you've authenticated and have the necessary workspace permissions                                                              |
| Unexpected results               | The agent may chain multiple tools (e.g., listing jobs first, then running one). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                       |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Run the ETL job" will find the job first, then trigger it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Databricks MCP server](https://www.gumloop.com/mcp/databricks) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
