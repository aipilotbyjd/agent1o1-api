> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Asana

> Manage projects, tasks, and teams with AI-powered work management.

Asana is a work management platform for organizing projects, tasks, and team collaboration. The Asana MCP server lets you create tasks, manage projects, and track work using natural language.

## What Can It Do?

* **Create and update tasks** with assignees, due dates, tags, and custom fields
* **Retrieve tasks** by project, assignee, tag, or section
* **Manage project access** by adding or removing members and followers
* **Explore portfolios and sections** to keep stakeholders informed

## Where to Use It

### In Agents (Recommended)

Add Asana as a tool to any agent. The agent can then interact with your projects and tasks conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Asana tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a task in the Marketing project")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                             | Description                                                    |
| -------------------------------- | -------------------------------------------------------------- |
| **Get User Details**             | Get current user's details                                     |
| **List Projects**                | List accessible projects with filtering                        |
| **Get Project**                  | Get detailed project information                               |
| **Add/Remove Project Members**   | Manage project membership                                      |
| **Add/Remove Project Followers** | Manage project followers                                       |
| **List Tasks**                   | List tasks by project, tag, assignee, or section               |
| **Create Task**                  | Create a new task with assignee, due date, and custom fields   |
| **Get Task**                     | Get detailed task information                                  |
| **Update Task**                  | Update task properties including custom fields                 |
| **Delete Task**                  | Move a task to trash                                           |
| **Duplicate Task**               | Create a copy of a task                                        |
| **Create Subtask**               | Create a subtask under a parent task with custom field support |
| **Get Subtasks**                 | Get all subtasks of a task                                     |
| **Add/Remove Task Tags**         | Manage task tags                                               |
| **Add/Remove Task Followers**    | Manage task followers                                          |
| **Get Project Sections**         | Get all sections in a project                                  |
| **Get/Create Tags**              | Manage workspace tags                                          |
| **Get Portfolio**                | Get portfolio details                                          |
| **Get Portfolio Items**          | Get projects in a portfolio                                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a task:**

```text theme={"dark"}
Create a task "Review Q4 budget" in the Finance project, assign to sarah@company.com, due next Friday
```

**Find tasks:**

```text theme={"dark"}
Show me all tasks assigned to me that are due this week
```

**Update task status:**

```text theme={"dark"}
Mark the task "Prepare presentation" as complete
```

**Manage project access:**

```text theme={"dark"}
Add john@company.com as a member to the Product Launch project
```

**Get project overview:**

```text theme={"dark"}
List all sections in the Marketing Campaign project
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                             |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Be specific with project names or use "in workspace X" for clarity                                                                                   |
| Action not completing            | Check that you've authenticated and have permissions for the project                                                                                 |
| Unexpected results               | The agent may chain multiple tools (e.g., finding the project first, then creating a task). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                  |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a task in the Marketing project" will find the project ID first, then create the task. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Asana MCP server](https://www.gumloop.com/mcp/asana) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
