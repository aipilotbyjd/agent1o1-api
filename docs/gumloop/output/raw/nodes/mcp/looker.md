> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Looker

> Access enterprise dashboards and analytics with AI-powered business intelligence automation.

Looker is a Google Cloud business intelligence platform for data exploration and visualization. The Looker MCP server lets you manage dashboards, run queries, explore LookML models, schedule deliveries, and monitor alerts using natural language.

## What Can It Do?

* **Manage dashboards** by listing, creating, updating, and deleting dashboards and their elements
* **Work with Looks** to create, run, and manage saved queries
* **Run queries** inline or from saved definitions, with async task support
* **Explore LookML models** and their dimensions, measures, and relationships
* **Schedule deliveries** for reports and dashboards
* **Manage alerts** for threshold-based notifications
* **Organize content** with folders, boards, favorites, and metadata
* **Render dashboards and Looks** as downloadable images or PDFs

## Where to Use It

### In Agents (Recommended)

Add Looker as a tool to any agent. The agent can then access your dashboards and analytics conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Looker tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run the monthly sales dashboard query")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Setting Up Credentials

Looker uses API3 client credentials for authentication. You'll need:

1. A Looker Enterprise instance with API access enabled
2. A dedicated service account Client ID and Client Secret (generated in Looker Admin → Users → API Keys)
3. Your Looker API Host URL (e.g., `https://company.cloud.looker.com`)

Add these credentials to your [Connectors page](https://www.gumloop.com/personal/connectors).

## Available Tools

### Dashboards

| Tool                     | Description                      |
| ------------------------ | -------------------------------- |
| **List Dashboards**      | Search Looker dashboards         |
| **Get Dashboard**        | Get a Looker dashboard           |
| **Create Dashboard**     | Create a Looker dashboard        |
| **Update Dashboard**     | Update a Looker dashboard        |
| **Delete Dashboard**     | Delete a Looker dashboard        |
| **Move or Copy Content** | Move or copy a dashboard or Look |

### Dashboard Elements

| Tool                         | Description               |
| ---------------------------- | ------------------------- |
| **List Dashboard Elements**  | Search dashboard tiles    |
| **Create Dashboard Element** | Create a dashboard tile   |
| **Update Dashboard Element** | Update a dashboard tile   |
| **Delete Dashboard Element** | Delete a dashboard tile   |
| **Create Dashboard Filter**  | Create a dashboard filter |
| **Update Dashboard Filter**  | Update a dashboard filter |
| **Delete Dashboard Filter**  | Delete a dashboard filter |

### Looks

| Tool            | Description          |
| --------------- | -------------------- |
| **List Looks**  | Search Looker Looks  |
| **Get Look**    | Get a Looker Look    |
| **Create Look** | Create a Looker Look |
| **Update Look** | Update a Looker Look |
| **Delete Look** | Delete a Looker Look |
| **Run Look**    | Run a Looker Look    |

### Queries

| Tool                       | Description                       |
| -------------------------- | --------------------------------- |
| **Run Query**              | Run a saved Looker query          |
| **Run Inline Query**       | Run an inline Looker query        |
| **Create Query**           | Create a Looker query             |
| **Get Query**              | Get a Looker query                |
| **Create Query Task**      | Create an async Looker query task |
| **Get Query Task**         | Get a Looker query task           |
| **Get Query Task Results** | Get Looker query task results     |

### Rendering

| Tool                             | Description                    |
| -------------------------------- | ------------------------------ |
| **Create Dashboard Render Task** | Create a dashboard render task |
| **Create Look Render Task**      | Create a Look render task      |
| **Get Render Task**              | Get a Looker render task       |
| **Get Render Task Results**      | Get Looker render task results |

### LookML

| Tool                         | Description          |
| ---------------------------- | -------------------- |
| **List LookML Models**       | List LookML models   |
| **Get LookML Model**         | Get a LookML model   |
| **Get LookML Model Explore** | Get a LookML explore |

### Folders & Content

| Tool                        | Description                    |
| --------------------------- | ------------------------------ |
| **List Folders**            | Search Looker folders          |
| **Get Folder**              | Get a Looker folder            |
| **Create Folder**           | Create a Looker folder         |
| **Update Folder**           | Update a Looker folder         |
| **Delete Folder**           | Delete a Looker folder         |
| **Search Content**          | Search Looker content          |
| **List Content Favorites**  | Search content favorites       |
| **Create Content Favorite** | Favorite Looker content        |
| **Delete Content Favorite** | Remove a content favorite      |
| **Get Content Metadata**    | Get Looker content metadata    |
| **Update Content Metadata** | Update Looker content metadata |
| **Validate Content**        | Run Looker content validation  |

### Boards

| Tool                     | Description            |
| ------------------------ | ---------------------- |
| **List Boards**          | List Looker boards     |
| **Get Board**            | Get a Looker board     |
| **Create Board**         | Create a Looker board  |
| **Update Board**         | Update a Looker board  |
| **Delete Board**         | Delete a Looker board  |
| **Create Board Section** | Create a board section |
| **Update Board Section** | Update a board section |
| **Delete Board Section** | Delete a board section |
| **Create Board Item**    | Create a board item    |
| **Update Board Item**    | Update a board item    |
| **Delete Board Item**    | Delete a board item    |

### Scheduled Plans & Delivery

| Tool                        | Description                 |
| --------------------------- | --------------------------- |
| **List Scheduled Plans**    | List Looker scheduled plans |
| **Get Scheduled Plan**      | Get a scheduled plan        |
| **Create Scheduled Plan**   | Create a scheduled plan     |
| **Update Scheduled Plan**   | Update a scheduled plan     |
| **Delete Scheduled Plan**   | Delete a scheduled plan     |
| **Run Scheduled Plan Once** | Run a scheduled plan once   |

### Alerts

| Tool             | Description           |
| ---------------- | --------------------- |
| **List Alerts**  | Search Looker alerts  |
| **Get Alert**    | Get a Looker alert    |
| **Create Alert** | Create a Looker alert |
| **Update Alert** | Update a Looker alert |
| **Delete Alert** | Delete a Looker alert |

## Example Prompts

Use these with your agent or in the Agent Node:

**List dashboards:**

```text theme={"dark"}
Show me all dashboards that contain "revenue" in the title
```

**Run a Look:**

```text theme={"dark"}
Run the "Monthly Sales Summary" Look and show me the results
```

**Run an inline query:**

```text theme={"dark"}
Run a query on the orders explore showing total revenue by region for this quarter
```

**Explore LookML:**

```text theme={"dark"}
What dimensions and measures are available in the orders explore?
```

**Schedule a report:**

```text theme={"dark"}
Create a scheduled plan to email the Sales Dashboard every Monday at 9am
```

**Manage content:**

```text theme={"dark"}
Move the Q4 dashboard to the Finance folder
```

**Render a dashboard:**

```text theme={"dark"}
Generate a PDF export of the Executive Summary dashboard
```

**Check alerts:**

```text theme={"dark"}
Show me all active alerts for the revenue metrics
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Client ID and Client Secret are correct and the service account has API access                          |
| Dashboard not found   | Use exact dashboard titles or IDs. Try listing dashboards first to find the correct reference.                      |
| Query timeout         | Large queries may take time. Use async query tasks for complex queries.                                             |
| Permission denied     | Ensure the service account has the appropriate Looker role and model access                                         |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Show me last month's revenue trend" will find the right Look or explore, run the query, and present the results. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Looker MCP server](https://www.gumloop.com/mcp/glooker) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
