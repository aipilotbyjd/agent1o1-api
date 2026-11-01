> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Tasks

> Manage to-dos and task lists with AI-powered automation.

Google Tasks is Google's task management service integrated with Gmail and Calendar. The Google Tasks MCP server lets you create, organize, and update tasks using natural language.

## What Can It Do?

* **Create and manage tasks** with titles, notes, and due dates
* **Organize task lists** for different projects or contexts
* **Mark tasks complete** and track progress
* **Query tasks** by list, status, or due date

## Where to Use It

### In Agents (Recommended)

Add Google Tasks as a tool to any agent. The agent can then manage your tasks conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Tasks tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a task in my Work list")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                               |
| ------------------------ | ----------------------------------------- |
| **List Task Lists**      | Retrieve all your task lists              |
| **Find Task List**       | Find a task list by name                  |
| **List Tasks in a List** | List tasks within a specific list         |
| **Create Task**          | Create a new task with due date and notes |
| **Update Task**          | Update task title, due date, or notes     |
| **Complete Task**        | Mark a task as completed                  |
| **Delete Task**          | Delete a task from a list                 |

## Example Prompts

Use these with your agent or in the Agent Node:

**View tasks:**

```text theme={"dark"}
What tasks do I have due this week?
```

**Create a task:**

```text theme={"dark"}
Add "Review quarterly report" to my Work list with a due date of Friday
```

**Complete a task:**

```text theme={"dark"}
Mark the "Send invoice" task as complete
```

**List by project:**

```text theme={"dark"}
Show me all tasks in my Marketing list
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                        |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify the task list name clearly                                                                                                              |
| Action not completing            | Check that you've authenticated with Google Tasks                                                                                               |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a list first, then creating a task). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add a task to my Work list" will find the list ID first, then create the task. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Tasks MCP server](https://www.gumloop.com/mcp/gtasks) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
