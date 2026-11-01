> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Datadog

> Monitor your infrastructure with AI-powered observability and incident management.

Datadog is a comprehensive monitoring and analytics platform for cloud-scale infrastructure, applications, and logs. The Datadog MCP server lets you monitor infrastructure, query metrics, search logs, manage incidents, and control monitors using natural language.

## What Can It Do?

* **Monitor management** including create, update, mute, and delete monitors
* **Query metrics** with timeseries data and aggregation
* **Search logs** with advanced filtering and time ranges
* **Incident management** for tracking and coordinating responses
* **Dashboard operations** including create, update, and view dashboards
* **Host management** including muting and listing infrastructure hosts
* **SLO tracking** for service level objectives
* **Synthetic monitoring** for proactive testing

## Where to Use It

### In Agents (Recommended)

Add Datadog as a tool to any agent. The agent can then monitor your infrastructure conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Enter your Datadog API credentials

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Datadog tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all monitors in alert state")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

Datadog uses API key authentication. When connecting your Datadog account, you'll need to provide:

* **API Key**: Your Datadog API key for authentication
* **Application Key**: Your Datadog Application key for authorization
* **Site**: Your Datadog site (e.g., `datadoghq.com`, `datadoghq.eu`, `us3.datadoghq.com`)

To create API and Application keys:

1. Log in to your Datadog account
2. Navigate to **Organization Settings** → **API Keys** to create an API key
3. Navigate to **Organization Settings** → **Application Keys** to create an Application key
4. Copy both keys and your site URL for use in Gumloop

<Warning>
  Keep your API and Application keys secure. These keys provide access to your Datadog account and should not be shared publicly.
</Warning>

## Available Tools

### Monitor Tools

| Tool               | Description                                                                 |
| ------------------ | --------------------------------------------------------------------------- |
| **List Monitors**  | List all monitors with filtering by name, tags, or status                   |
| **Get Monitor**    | Get detailed information about a specific monitor by ID                     |
| **Create Monitor** | Create a new monitor with specified type, query, and alerting configuration |
| **Update Monitor** | Update an existing monitor's configuration                                  |
| **Delete Monitor** | Delete a monitor by ID                                                      |
| **Mute Monitor**   | Mute a monitor to suppress notifications during maintenance                 |
| **Unmute Monitor** | Unmute a previously muted monitor to resume notifications                   |

### Metrics Tools

| Tool              | Description                                                     |
| ----------------- | --------------------------------------------------------------- |
| **Query Metrics** | Query timeseries metrics data with aggregation and grouping     |
| **List Metrics**  | List available metrics in your account to discover metric names |

### Logs Tools

| Tool            | Description                                                           |
| --------------- | --------------------------------------------------------------------- |
| **Search Logs** | Search and retrieve log events with filtering by query and time range |

### Incident Tools

| Tool                       | Description                                                              |
| -------------------------- | ------------------------------------------------------------------------ |
| **List Incidents**         | List incidents with optional filtering by state or query                 |
| **Get Incident**           | Get detailed information about a specific incident                       |
| **Create Incident**        | Create a new incident for tracking and coordination                      |
| **Update Incident**        | Update an existing incident's attributes like title, status, or severity |
| **Delete Incident**        | Delete an incident by ID                                                 |
| **List Incident Timeline** | Get the timeline of events for a specific incident                       |

### Dashboard Tools

| Tool                 | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| **List Dashboards**  | List all dashboards with optional filtering                  |
| **Get Dashboard**    | Get detailed dashboard configuration by ID including widgets |
| **Create Dashboard** | Create a new dashboard with specified layout and widgets     |
| **Update Dashboard** | Update an existing dashboard's layout and widgets            |
| **Delete Dashboard** | Delete a dashboard from Datadog                              |

### Host Tools

| Tool                | Description                                                         |
| ------------------- | ------------------------------------------------------------------- |
| **List Hosts**      | List infrastructure hosts with filtering by name, tags, or criteria |
| **Get Host Totals** | Get the total count of active and up hosts                          |
| **Mute Host**       | Mute a host to suppress alerts during maintenance                   |
| **Unmute Host**     | Unmute a previously muted host to resume alerts                     |

### Event Tools

| Tool             | Description                                              |
| ---------------- | -------------------------------------------------------- |
| **List Events**  | Query events by time range and optional filters          |
| **Get Event**    | Get detailed information about a specific event by ID    |
| **Create Event** | Post a new event to Datadog for tracking and correlation |

### Downtime Tools

| Tool                | Description                                                 |
| ------------------- | ----------------------------------------------------------- |
| **List Downtimes**  | List all scheduled downtimes for maintenance windows        |
| **Get Downtime**    | Get detailed information about a specific downtime          |
| **Create Downtime** | Schedule a new downtime to mute monitors during maintenance |
| **Cancel Downtime** | Cancel a scheduled downtime to resume monitoring            |

### SLO Tools

| Tool                | Description                                               |
| ------------------- | --------------------------------------------------------- |
| **List SLOs**       | List all Service Level Objectives with optional filtering |
| **Get SLO**         | Get detailed information about a specific SLO             |
| **Create SLO**      | Create a new Service Level Objective                      |
| **Update SLO**      | Update an existing SLO's configuration                    |
| **Delete SLO**      | Delete an SLO by ID                                       |
| **Get SLO History** | Get historical SLI data for an SLO                        |

### Synthetics Tools

| Tool                  | Description                                              |
| --------------------- | -------------------------------------------------------- |
| **List Synthetics**   | List all synthetic monitoring tests                      |
| **Get Synthetic**     | Get detailed information about a specific synthetic test |
| **Create Synthetic**  | Create a new synthetic monitoring test                   |
| **Update Synthetic**  | Update an existing synthetic test                        |
| **Delete Synthetic**  | Delete synthetic tests by IDs                            |
| **Trigger Synthetic** | Manually trigger synthetic tests                         |

## Example Prompts

Use these with your agent or in the Agent Node:

**List alerting monitors:**

```text theme={"dark"}
Show me all monitors that are currently in alert state
```

**Query metrics:**

```text theme={"dark"}
Get the average CPU usage across all hosts for the last hour
```

**Search logs:**

```text theme={"dark"}
Search for error logs in production from the last 30 minutes
```

**Create an incident:**

```text theme={"dark"}
Create a critical incident for the database outage affecting the checkout service
```

**Mute a monitor:**

```text theme={"dark"}
Mute the high CPU monitor for 2 hours during the deployment
```

**Check SLO status:**

```text theme={"dark"}
Show me the current status of all SLOs for the payment service
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your API Key and Application Key are correct                                                                                               |
| Wrong site            | Make sure you're using the correct Datadog site (e.g., datadoghq.com vs datadoghq.eu)                                                             |
| Permission denied     | Ensure your Application Key has the necessary permissions                                                                                         |
| Monitor not found     | Check that the monitor ID is correct                                                                                                              |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |
| Unexpected results    | The agent may chain multiple tools (e.g., listing monitors first, then getting details). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Mute all monitors for the web service during maintenance" will list the monitors first, then mute each one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Datadog MCP server](https://www.gumloop.com/mcp/datadog) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
