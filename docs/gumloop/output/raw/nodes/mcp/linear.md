> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Linear

> Manage issues and projects with AI-powered product development automation.

Linear is a modern issue tracking platform for product teams. The Linear MCP server lets you manage issues, projects, initiatives, and more using natural language.

## What Can It Do?

* **Search issues** using keywords, labels, states, or assignees
* **Create new issues** in any team or project
* **Update issue status**, priority, or assignee
* **Manage projects** with filtering by team, status, and initiative
* **Manage initiatives** with full CRUD, status tracking, and project linking
* **Triage bugs** and feature requests automatically

## Where to Use It

### In Agents (Recommended)

Add Linear as a tool to any agent. The agent can then manage your issues conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Linear tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a bug in team Platform")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Issue Tools

| Tool              | Description                                                                                                                            |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| **Search Issues** | Search issues with filters including team, label, state, assignee, cycle, project, and date ranges. Filters accept both names and IDs. |
| **Create Issue**  | Create a new issue in any team with title, description, priority, labels, and assignee                                                 |
| **Update Issue**  | Update status, priority, assignee, labels, and other issue fields                                                                      |
| **Delete Issue**  | Permanently delete an issue                                                                                                            |

### Issue Relation Tools

| Tool                      | Description                                                                      |
| ------------------------- | -------------------------------------------------------------------------------- |
| **List Issue Relations**  | List an issue's relations in one direction — what it blocks, or what blocks it   |
| **Create Issue Relation** | Create a relation between two issues — blocks, blocked by, related, or duplicate |
| **Update Issue Relation** | Change a relation's type or either issue it connects                             |
| **Delete Issue Relation** | Delete a relation between two issues                                             |

### Comment Tools

| Tool               | Description                        |
| ------------------ | ---------------------------------- |
| **List Comments**  | List comments for a specific issue |
| **Create Comment** | Create a comment on an issue       |
| **Delete Comment** | Delete a comment on an issue       |

### Project Tools

| Tool               | Description                                                                                                   |
| ------------------ | ------------------------------------------------------------------------------------------------------------- |
| **List Projects**  | List projects with filtering by team, status, initiative, and date ranges. Filters accept both names and IDs. |
| **Get Project**    | Retrieve details of a specific project                                                                        |
| **Create Project** | Create a new project with name, description, teams, and target dates                                          |
| **Update Project** | Update project details including name, status, and dates                                                      |
| **Delete Project** | Permanently delete a project                                                                                  |

### Initiative Tools

| Tool                  | Description                                                                    |
| --------------------- | ------------------------------------------------------------------------------ |
| **List Initiatives**  | List initiatives with filtering by status, health, name, and date ranges       |
| **Get Initiative**    | Retrieve details of a specific initiative including linked projects            |
| **Create Initiative** | Create a new initiative with name, description, status, target date, and owner |
| **Update Initiative** | Update initiative details and link or unlink projects                          |
| **Delete Initiative** | Permanently delete an initiative                                               |

### Status Update Tools

| Tool                     | Description                                                                                              |
| ------------------------ | -------------------------------------------------------------------------------------------------------- |
| **List Status Updates**  | List project status updates (health/progress reports) with optional date filtering                       |
| **Create Status Update** | Create a project status update with Markdown body and health status (`onTrack`, `atRisk`, or `offTrack`) |
| **Post Status Update**   | Create or edit a project status update with health status and description                                |
| **Delete Status Update** | Delete a project status update                                                                           |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find issues:**

```text theme={"dark"}
Search for bugs labeled "critical" in the Backend team
```

**Create an issue:**

```text theme={"dark"}
Create an issue in team Growth titled "Add referral tracking"
```

**Update status:**

```text theme={"dark"}
Move the API rate limit issue to In Progress
```

**Assign work:**

```text theme={"dark"}
Assign the database timeout issue to alice@company.com
```

**Check status:**

```text theme={"dark"}
What's the status of the checkout bug?
```

**List projects:**

```text theme={"dark"}
Show me all active projects in the Platform team
```

**Create an initiative:**

```text theme={"dark"}
Create a new initiative called "Q2 Platform Improvements" with status Planned
```

**Link a project to an initiative:**

```text theme={"dark"}
Link the API Redesign project to the Q2 Platform Improvements initiative
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific issue titles or team names                                                                                             |
| Action not completing            | Check that you've authenticated with Linear                                                                                         |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Set the checkout bug to high priority" will find the issue first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Linear MCP server](https://www.gumloop.com/mcp/linear) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
