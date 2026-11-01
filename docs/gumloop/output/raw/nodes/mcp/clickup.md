> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# ClickUp

> Manage tasks, projects, and team collaboration with AI-powered automation.

ClickUp is a project management platform that organizes tasks, projects, and collaboration in one place. The ClickUp MCP server lets you view, create, and update tasks, lists, and folders using natural language.

## What Can It Do?

* **Retrieve workspaces, spaces, folders, lists, and tasks** without writing code
* **Create and update tasks** with assignees, due dates, and priorities
* **Add comments** to keep teammates informed
* **Manage project structure** by creating lists and folders

## Where to Use It

### In Agents (Recommended)

Add ClickUp as a tool to any agent. The agent can then interact with your projects conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with ClickUp tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get tasks from the Sprint 23 list")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                       | Description                                             |
| -------------------------- | ------------------------------------------------------- |
| **Get Authenticated User** | Get current user details                                |
| **Get Workspaces**         | List all workspaces                                     |
| **Get Spaces**             | List spaces in a workspace                              |
| **Get Folders**            | List folders in a space                                 |
| **Get Lists**              | List lists in a folder or space                         |
| **Get Tasks**              | Retrieve tasks from a list with cursor-based pagination |
| **Get Task By Id**         | Get a single task                                       |
| **Create Task**            | Create a new task                                       |
| **Update Task**            | Update an existing task                                 |
| **Add Comment**            | Add a comment to a task                                 |
| **Create List**            | Create a list in a folder                               |
| **Create Folder**          | Create a folder in a space                              |

## Example Prompts

Use these with your agent or in the Agent Node:

**Get tasks:**

```text theme={"dark"}
Show me all in-progress tasks from the Sprint 23 list
```

**Create a task:**

```text theme={"dark"}
Create a task "Review Q3 Budget" in the Finance Tasks list, due next Friday
```

**Update a task:**

```text theme={"dark"}
Mark the API Documentation task as complete
```

**Add a comment:**

```text theme={"dark"}
Add a comment to the Logo Redesign task: "Waiting on client approval"
```

**Explore structure:**

```text theme={"dark"}
List all folders in the Marketing space
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                        |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific list names or provide the workspace context                                                                                        |
| Action not completing            | Check that you've authenticated and have permissions for the workspace                                                                          |
| Unexpected results               | The agent may chain multiple tools (e.g., finding the list first, then getting tasks). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a task in the Marketing folder" will find the workspace, space, and folder first, then create the task. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [ClickUp MCP server](https://www.gumloop.com/mcp/clickup) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
