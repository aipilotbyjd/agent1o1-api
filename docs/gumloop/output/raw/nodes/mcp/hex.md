> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Hex

> Manage data projects and analytics workflows with AI-powered Hex automation.

Hex is a collaborative data platform for analytics, data science, and reporting. The Hex MCP server lets you manage projects, trigger runs, organize collections, and administer users and groups using natural language.

## What Can It Do?

* **List and manage projects** including status updates and sharing permissions
* **Trigger and monitor project runs** with optional input parameters
* **Organize work** with collections for grouping related projects
* **Manage data connections** to external databases and warehouses
* **Administer users and groups** for workspace access control

## Where to Use It

### In Agents (Recommended)

Add Hex as a tool to any agent. The agent can then manage your Hex workspace conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Hex tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run my weekly analytics project")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Projects

| Tool                                   | Description                                                          |
| -------------------------------------- | -------------------------------------------------------------------- |
| **List Projects**                      | List projects in your Hex workspace, or get a specific project by ID |
| **Update Project Status**              | Update the status of a Hex project                                   |
| **Update Project Sharing (Users)**     | Update user-level sharing permissions on a project                   |
| **Update Project Sharing (Workspace)** | Update workspace and public sharing permissions on a project         |

### Runs

| Tool                  | Description                                                       |
| --------------------- | ----------------------------------------------------------------- |
| **Run Project**       | Trigger a run of a published Hex project                          |
| **List Project Runs** | List runs for a project, or get a specific run's status by run ID |
| **Cancel Run**        | Cancel an active run for a project                                |

### Collections

| Tool                  | Description                                                            |
| --------------------- | ---------------------------------------------------------------------- |
| **List Collections**  | List collections in your workspace, or get a specific collection by ID |
| **Create Collection** | Create a new collection in your workspace                              |
| **Update Collection** | Update a collection's permissions and settings                         |

### Data Connections

| Tool                       | Description                                                                 |
| -------------------------- | --------------------------------------------------------------------------- |
| **List Data Connections**  | List data connections in your workspace, or get a specific connection by ID |
| **Update Data Connection** | Update a data connection's settings, credentials, or sharing configuration  |

### Administration

| Tool                | Description                                                  |
| ------------------- | ------------------------------------------------------------ |
| **List Users**      | List users in your Hex workspace                             |
| **Deactivate User** | Deactivate a user in your workspace                          |
| **List Groups**     | List groups in your workspace, or get a specific group by ID |
| **Create Group**    | Create a new group with optional initial members             |
| **Update Group**    | Update a group's name and/or add or remove members           |
| **Delete Group**    | Delete a group from your workspace                           |

## Example Prompts

Use these with your agent or in the Agent Node:

**List projects:**

```text theme={"dark"}
Show me my most recently published Hex projects
```

**Run a project:**

```text theme={"dark"}
Trigger a run of the Weekly Sales Report project
```

**Check run status:**

```text theme={"dark"}
What's the status of my latest run for the Revenue Dashboard?
```

**Cancel a run:**

```text theme={"dark"}
Cancel the currently running job for my Analytics project
```

**Manage collections:**

```text theme={"dark"}
Create a new collection called "Q1 Reports" and list all existing collections
```

**Share a project:**

```text theme={"dark"}
Give editor access to alice@company.com on the Revenue Dashboard project
```

**Manage groups:**

```text theme={"dark"}
Create a new group called "Data Team" and add alice@company.com and bob@company.com
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific project names or provide project IDs                                                                                                  |
| Action not completing            | Check that you've authenticated with Hex                                                                                                           |
| Unexpected results               | The agent may chain multiple tools (e.g., listing projects first, then triggering a run). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Run my latest project and check the status" will find the project, trigger a run, and poll for completion. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Hex MCP server](https://www.gumloop.com/mcp/hex) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
