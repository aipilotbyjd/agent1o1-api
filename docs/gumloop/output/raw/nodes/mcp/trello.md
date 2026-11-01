> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Trello

> Manage boards, lists, and cards with AI-powered project management automation.

Trello is a visual project management tool that organizes work into boards, lists, and cards. The Trello MCP server lets you manage your boards, lists, cards, checklists, labels, and custom fields using natural language.

## What Can It Do?

* **Manage boards** with creation, updates, and member management
* **Organize lists and cards** including creation, updates, and deletion
* **Track progress** with checklists, labels, and comments
* **Read and write custom fields** on cards, including dropdown options
* **Search across** boards, cards, members, and organizations

## Where to Use It

### In Agents (Recommended)

Add Trello as a tool to any agent. The agent can then manage your project boards conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Trello tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a card in the To Do list")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Board Tools

| Tool                   | Description                                                   |
| ---------------------- | ------------------------------------------------------------- |
| **List Boards**        | List boards for the authenticated user with filtering options |
| **Get Board**          | Get a specific board by ID                                    |
| **Create Board**       | Create a new board                                            |
| **Update Board**       | Update an existing board                                      |
| **Delete Board**       | Permanently delete a board                                    |
| **List Board Members** | List members of a board                                       |
| **List Board Lists**   | List all lists on a board                                     |
| **List Board Labels**  | List all labels on a board                                    |

### List Tools

| Tool            | Description                  |
| --------------- | ---------------------------- |
| **Get List**    | Get a specific list by ID    |
| **Create List** | Create a new list on a board |
| **Update List** | Update an existing list      |

### Card Tools

| Tool                 | Description                 |
| -------------------- | --------------------------- |
| **List Cards**       | List cards on a list        |
| **Get Card**         | Get a specific card by ID   |
| **Create Card**      | Create a new card on a list |
| **Update Card**      | Update an existing card     |
| **Delete Card**      | Permanently delete a card   |
| **Add Card Comment** | Add a comment to a card     |

### Checklist Tools

| Tool                      | Description                       |
| ------------------------- | --------------------------------- |
| **List Card Checklists**  | List all checklists on a card     |
| **Create Checklist**      | Create a new checklist on a card  |
| **Delete Checklist**      | Delete a checklist from a card    |
| **Create Checklist Item** | Create a new item on a checklist  |
| **Update Checklist Item** | Update a checklist item on a card |

### Custom Field Tools

| Tool                         | Description                                                                     |
| ---------------------------- | ------------------------------------------------------------------------------- |
| **List Board Custom Fields** | List custom field definitions on a board, including options for dropdown fields |
| **List Card Custom Fields**  | List custom field values set on a card                                          |
| **Update Card Custom Field** | Set, update, or clear a custom field value on a card                            |

### Label and Search Tools

| Tool                   | Description                                              |
| ---------------------- | -------------------------------------------------------- |
| **Create Label**       | Create a new label on a board                            |
| **Update Label**       | Update an existing label                                 |
| **Delete Label**       | Delete a label from a board                              |
| **Search**             | Search for boards, cards, members, and organizations     |
| **Get My Profile**     | Get the authenticated user's profile                     |
| **List Organizations** | List organizations/workspaces for the authenticated user |
| **Get Organization**   | Get a specific organization/workspace by ID              |

## Example Prompts

Use these with your agent or in the Agent Node:

**List boards:**

```text theme={"dark"}
Show me all my Trello boards
```

**Create a card:**

```text theme={"dark"}
Create a card called "Fix login bug" in the To Do list on the Engineering board
```

**Move a card:**

```text theme={"dark"}
Move the "Design review" card to the Done list
```

**Add a checklist:**

```text theme={"dark"}
Add a checklist called "Launch steps" to the Release card with items: update docs, notify team, deploy
```

**Set a custom field:**

```text theme={"dark"}
Set the Priority custom field to "High" on the "Fix login bug" card
```

**Search:**

```text theme={"dark"}
Search for cards about "onboarding" across all my boards
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                       |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific board or card names                                                                                                               |
| Action not completing            | Check that you've authenticated with Trello                                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a board first, then listing cards). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                            |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add a comment to the latest card in To Do" will find the list, get cards, then add a comment. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Trello MCP server](https://www.gumloop.com/mcp/trello) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
