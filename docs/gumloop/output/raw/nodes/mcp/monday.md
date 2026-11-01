> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Monday

> Manage boards, items, and projects with AI-powered work management automation.

Monday.com is a work operating system for managing projects and workflows. The Monday MCP server lets you create boards, manage items, update columns, and track work using natural language.

## What Can It Do?

* **Create and manage boards** for teams or projects
* **Add and update items** with statuses and due dates
* **Organize with groups** and subitems
* **Post updates** and track collaboration

## Where to Use It

### In Agents (Recommended)

Add Monday as a tool to any agent. The agent can then manage your work conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Monday tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create an item in the Sprint board")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description               |
| ----------------------- | ------------------------- |
| **Get Boards**          | Get all accessible boards |
| **Get Board**           | Get a specific board      |
| **Create Board**        | Create a new board        |
| **Create Item**         | Create a new item         |
| **Get Item**            | Get item details          |
| **Update Item**         | Update item properties    |
| **Delete Item**         | Delete an item            |
| **Search Items**        | Search items with filters |
| **Change Column Value** | Update a column value     |
| **Create Group**        | Create a new group        |
| **Create Subitem**      | Create a subitem          |
| **Get Updates**         | Get item comments         |
| **Create Update**       | Post a comment            |
| **Archive Item**        | Archive an item           |
| **Archive Board**       | Archive a board           |

## Example Prompts

Use these with your agent or in the Agent Node:

**View boards:**

```text theme={"dark"}
Show me all my Monday boards
```

**Create an item:**

```text theme={"dark"}
Create an item called "Website Launch" in the Marketing board
```

**Update status:**

```text theme={"dark"}
Set the status to "Done" for the API Integration task
```

**Search items:**

```text theme={"dark"}
Find all high-priority items in the Sprint board
```

**Add a comment:**

```text theme={"dark"}
Post an update "Meeting moved to Thursday" on the Client Presentation item
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific board and item names                                                                                                                 |
| Action not completing            | Check that you've authenticated with Monday.com                                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a board first, then creating an item). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a task in the Sprint board" will find the board first, then create the item. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Monday MCP server](https://www.gumloop.com/mcp/monday) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
