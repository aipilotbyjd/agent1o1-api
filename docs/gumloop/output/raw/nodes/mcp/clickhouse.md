> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# ClickHouse

> Query ClickHouse Cloud and manage services, ClickPipes, and ClickStack observability with natural language.

ClickHouse Cloud is a serverless, columnar data warehouse for real-time analytics and observability. The ClickHouse MCP server lets you run SQL, manage services and backups, configure ClickPipes, track costs, and operate the ClickStack observability plane (dashboards, alerts, sources) using natural language.

## What Can It Do?

* **Run SQL** against any ClickHouse Cloud service and return structured results
* **Manage services** by listing them, starting, or stopping on demand
* **Inspect backups** and backup schedules for each service
* **Manage ClickPipes** to monitor streaming ingestion jobs
* **Track organization costs** with daily billing and usage data
* **Operate ClickStack observability** with dashboards, alerts, and data sources

## Where to Use It

### In Agents (Recommended)

Add ClickHouse as a tool to any agent. The agent can then interact with your data and observability plane conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your ClickHouse Cloud API key

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with ClickHouse tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Query the events table for the last 24 hours")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

ClickHouse uses a **Key ID / Key Secret** API key pair plus a **database user and password** for SQL access.

1. In the ClickHouse Cloud console, go to **Organization Settings → API Keys → New API Key**
2. Assign a role that matches what you want the MCP to do (Developer for read-only, Admin for writes and lifecycle operations)
3. Copy the **Key ID** and **Key Secret** (the secret is shown once)
4. Get your **Database User** (defaults to `default`) and **Database Password** from **Service Settings** in the Cloud console

See [Managing API Keys](https://clickhouse.com/docs/cloud/manage/openapi) for full details.

## Available Tools

| Tool                                 | Description                                                        |
| ------------------------------------ | ------------------------------------------------------------------ |
| **Execute Query**                    | Run a SQL query against a ClickHouse Cloud service                 |
| **List Organizations**               | List your Cloud organizations or fetch one by id                   |
| **List Services**                    | List services in an organization or fetch one by id                |
| **Update Service State**             | Start or stop a ClickHouse Cloud service                           |
| **List Service Backups**             | List backups for a service or fetch one by id                      |
| **Get Service Backup Configuration** | Get the backup schedule and retention for a service                |
| **List ClickPipes**                  | List ClickPipes on a service or fetch one by id                    |
| **Get Organization Cost**            | Retrieve daily billing and usage cost for an organization          |
| **List Dashboards**                  | List ClickStack dashboards on a service or fetch one by id         |
| **Create Dashboard**                 | Create a ClickStack dashboard                                      |
| **Update Dashboard**                 | Update a ClickStack dashboard                                      |
| **Delete Dashboard**                 | Delete a ClickStack dashboard                                      |
| **List Alerts**                      | List ClickStack alerts on a service or fetch one by id             |
| **Create Alert**                     | Create a ClickStack alert tied to a dashboard tile or saved search |
| **Update Alert**                     | Update a ClickStack alert                                          |
| **Delete Alert**                     | Delete a ClickStack alert                                          |
| **List Sources**                     | List ClickStack data sources configured on a service               |

## Example Prompts

Use these with your agent or in the Agent Node:

**Run a query:**

```text theme={"dark"}
Run "SELECT count() FROM events WHERE event_date = today()" on my production service
```

**List services:**

```text theme={"dark"}
Show me all ClickHouse services in my organization and their current state
```

**Start a service:**

```text theme={"dark"}
Start the "analytics-prod" service
```

**Check backups:**

```text theme={"dark"}
List the latest backups for the analytics-prod service and its backup retention policy
```

**Monitor ingestion:**

```text theme={"dark"}
List all ClickPipes on the analytics-prod service and flag any that are not running
```

**Track cost:**

```text theme={"dark"}
Show me the daily ClickHouse Cloud cost for my organization over the last 7 days
```

**Create an alert:**

```text theme={"dark"}
Create an alert on the "API Errors" dashboard tile that fires when errors exceed 100 per hour
```

## Troubleshooting

| Issue                  | Solution                                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failing | Verify the Key ID, Key Secret, and database credentials. The Key Secret is only shown once when created.            |
| Query returns no data  | Confirm you're targeting the right service and that the service is running. Use **List Services** first.            |
| Action not permitted   | Check that your API key role (Developer vs Admin) has the permissions the tool needs.                               |
| Tool not available     | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Start the analytics service and run a smoke-test query" will list services, start the right one, wait, and then run the query. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***
