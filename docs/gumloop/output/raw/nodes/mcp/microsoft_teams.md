> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Microsoft Teams

> Manage teams, channels, and meetings with AI-powered collaboration automation.

Microsoft Teams is Microsoft's collaboration platform for chat, meetings, and teamwork. The Microsoft Teams MCP server lets you create channels, send messages, manage meetings, and work with members using natural language.

<Info>Looking to deploy an agent **inside** a Teams channel so your team can @mention it? See [Using Agents in Microsoft Teams](/core-concepts/agents_teams). This page covers the MCP integration that lets agents **use** Teams as a tool.</Info>

## What Can It Do?

* **List and create teams and channels** for your organization
* **Send and retrieve messages** in chats and channels
* **Manage team membership** by adding or removing users
* **Schedule, update, and cancel meetings** automatically

## Where to Use It

### In Agents (Recommended)

Add Microsoft Teams as a tool to any agent. The agent can then manage your Teams workspace conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Microsoft Teams tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Send a message to the General channel")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                           | Description                  |
| ------------------------------ | ---------------------------- |
| **Get Teams**                  | List all teams you belong to |
| **Get Team Details**           | Fetch details of a team      |
| **Get Team Channels**          | List channels in a team      |
| **Create Team Channel**        | Create a new channel         |
| **Get Direct Messages**        | List your direct chats       |
| **Get Direct Message History** | Get chat message history     |
| **Send Direct Message**        | Send a direct message        |
| **Get Team Channel Messages**  | Get channel messages         |
| **Send Team Channel Message**  | Post a channel message       |
| **Post Message Reply**         | Reply to a message           |
| **Get Team Members**           | List team members            |
| **Add Team Member**            | Add a user to a team         |
| **Create Meeting**             | Schedule a new meeting       |
| **List Meetings**              | List upcoming meetings       |
| **Update Meeting**             | Modify a meeting             |
| **Delete Meeting**             | Cancel a meeting             |

## Example Prompts

Use these with your agent or in the Agent Node:

**List channels:**

```text theme={"dark"}
Show me all channels in the Marketing team
```

**Send a message:**

```text theme={"dark"}
Post "Sprint review at 2pm" to the Announcements channel in Product team
```

**Add a member:**

```text theme={"dark"}
Add sarah@company.com to the Engineering team
```

**Schedule a meeting:**

```text theme={"dark"}
Create a Teams meeting called "Q3 Planning" for tomorrow at 10am with john@company.com
```

**Check messages:**

```text theme={"dark"}
Get the latest messages from the Support channel
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use exact team and channel names                                                                                                        |
| Action not completing            | Check that you've authenticated with Microsoft 365                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a team first, then posting). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                     |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Post to the Marketing Announcements channel" will find the team and channel first, then post. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Microsoft Teams MCP server](https://www.gumloop.com/mcp/teams) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
