> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Basedash

> Create and manage Basedash charts, dashboards, and database insights.

Basedash is the database management and visualization platform that makes it easy to create charts and dashboards from your data. The Basedash MCP server lets you create and manage charts, dashboards, and database insights using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Basedash. Authentication uses OAuth — just connect your Basedash account and start using it immediately.
</Info>

## What Can It Do?

* **Create charts** and visualizations from your data
* **Manage dashboards** and organize views
* **Access database insights** and query results

## Where to Use It

### In Agents (Recommended)

Add Basedash as a tool to any agent. The agent can then create visualizations and access your data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Basedash account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Basedash tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Basedash uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Basedash to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a chart:**

```text theme={"dark"}
Create a line chart showing signups per day for the last 30 days
```

**Manage dashboards:**

```text theme={"dark"}
List all dashboards and their charts
```

**Query data:**

```text theme={"dark"}
Show me the top 10 customers by revenue this quarter
```

## Troubleshooting

| Issue               | Solution                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect      | Ensure you have an active Basedash account with database connections                                                |
| Chart not rendering | Check that the underlying data source is accessible                                                                 |
| Tool not available  | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
