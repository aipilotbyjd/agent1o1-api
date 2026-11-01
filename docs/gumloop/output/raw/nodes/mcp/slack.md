> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Slack

> Manage your workspace with AI-powered team communication automation.

Slack is the leading platform for team communication and collaboration. The Slack MCP server lets you read and post messages, manage channels, and search your workspace using natural language.

## What Can It Do?

* **Read and send messages** to channels and users
* **Manage channels** by creating, archiving, and updating topics and purposes
* **Search your workspace** for messages, files, and people
* **Handle membership** by adding or removing users
* **React to messages** with emoji reactions
* **Pin and unpin messages** in channels
* **Upload and download files** to and from channels
* **Create and manage canvases** with rich content

## Where to Use It

### In Agents (Recommended)

Add Slack as a tool to any agent. The agent can then manage your workspace conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Slack tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Post a message to #general")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                         | Description                                                                                                                                                                                                                                                                                                                                          |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Read Messages**            | Read messages from a channel                                                                                                                                                                                                                                                                                                                         |
| **Send Message**             | Post a message to a channel or user, or stage it as a draft in the user's "Drafts & sent" sidebar (`draft=true`). Supports `unfurl_links` parameter to control link previews. The bot can send messages to public channels even if it hasn't been added to them (via the `chat:write.public` scope). For private channels, the bot must be a member. |
| **Send Ephemeral Message**   | Send a message only visible to one user. Supports `blocks` parameter for rich Block Kit layouts.                                                                                                                                                                                                                                                     |
| **Create Canvas**            | Create a rich canvas message                                                                                                                                                                                                                                                                                                                         |
| **Add User To Channel**      | Add a user to a channel                                                                                                                                                                                                                                                                                                                              |
| **Remove From Channel**      | Remove a user from a channel                                                                                                                                                                                                                                                                                                                         |
| **Delete Message**           | Delete a message                                                                                                                                                                                                                                                                                                                                     |
| **Get Message Thread**       | Get a message and replies                                                                                                                                                                                                                                                                                                                            |
| **Search Users**             | Search for users                                                                                                                                                                                                                                                                                                                                     |
| **Create Channel**           | Create a new channel                                                                                                                                                                                                                                                                                                                                 |
| **Archive Channel**          | Archive a channel                                                                                                                                                                                                                                                                                                                                    |
| **Update Channel Topic**     | Update a channel's topic                                                                                                                                                                                                                                                                                                                             |
| **List Users In Channel**    | List channel members                                                                                                                                                                                                                                                                                                                                 |
| **Search**                   | Search messages and files                                                                                                                                                                                                                                                                                                                            |
| **Create Standalone Canvas** | Create a standalone canvas not tied to a channel                                                                                                                                                                                                                                                                                                     |
| **Lookup Canvas Sections**   | Retrieve sections and content from a canvas                                                                                                                                                                                                                                                                                                          |
| **Edit Canvas**              | Insert, replace, or delete content in a canvas                                                                                                                                                                                                                                                                                                       |
| **Delete Canvas**            | Delete an existing canvas                                                                                                                                                                                                                                                                                                                            |
| **Set Canvas Access**        | Set access levels for a canvas (channel-wide or specific users/teams)                                                                                                                                                                                                                                                                                |
| **Remove Canvas Access**     | Remove access to a canvas for specific users or teams                                                                                                                                                                                                                                                                                                |
| **Get User Presence**        | Check a user's online status                                                                                                                                                                                                                                                                                                                         |
| **Update Channel Purpose**   | Update a channel's purpose description                                                                                                                                                                                                                                                                                                               |
| **Unarchive Channel**        | Unarchive a previously archived channel                                                                                                                                                                                                                                                                                                              |
| **Get Conversation Info**    | Retrieve information about a conversation                                                                                                                                                                                                                                                                                                            |
| **List Pinned Items**        | List pinned items in a channel                                                                                                                                                                                                                                                                                                                       |
| **Pin Message**              | Pin a message in a channel                                                                                                                                                                                                                                                                                                                           |
| **Unpin Message**            | Unpin a message from a channel                                                                                                                                                                                                                                                                                                                       |
| **React To Message**         | Add an emoji reaction to a message                                                                                                                                                                                                                                                                                                                   |
| **List Channels**            | List channels in the workspace                                                                                                                                                                                                                                                                                                                       |
| **Upload File**              | Upload a file or image to a channel                                                                                                                                                                                                                                                                                                                  |
| **Download File**            | Download a file from Slack to Gumloop storage                                                                                                                                                                                                                                                                                                        |
| **List Reactions**           | List messages and files a user has reacted to                                                                                                                                                                                                                                                                                                        |
| **Create List**              | Create a Slack List                                                                                                                                                                                                                                                                                                                                  |
| **Read List Items**          | Read items from a Slack List                                                                                                                                                                                                                                                                                                                         |
| **Get List Item**            | Get a single item from a Slack List                                                                                                                                                                                                                                                                                                                  |
| **Create List Item**         | Add an item to a Slack List                                                                                                                                                                                                                                                                                                                          |
| **Update List Item**         | Update cells in a Slack List item                                                                                                                                                                                                                                                                                                                    |
| **Delete List Item**         | Delete an item from a Slack List                                                                                                                                                                                                                                                                                                                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Send a message:**

```text theme={"dark"}
Post "Daily standup starts in 10 minutes" to #engineering
```

**Read messages:**

```text theme={"dark"}
Show me the last 20 messages in #support
```

**Create a channel:**

```text theme={"dark"}
Create a private channel called "project-phoenix"
```

**Search workspace:**

```text theme={"dark"}
Search for messages from sarah about quarterly report
```

**Add a user:**

```text theme={"dark"}
Add john@company.com to the #marketing channel
```

**React to a message:**

```text theme={"dark"}
Add a thumbsup reaction to the last message in #general
```

**Pin a message:**

```text theme={"dark"}
Pin the latest announcement in #company-updates
```

**Draft a message:**

```text theme={"dark"}
Draft a message to #engineering saying "Deploy scheduled for Friday at 5pm"
```

<Info>
  When Slack tools are used through an agent, messages are posted as the **Gumloop bot** by default rather than as the authenticated user. This helps distinguish automated messages from manual ones.
</Info>

## Troubleshooting

| Issue                            | Solution                                                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use exact channel names with # prefix                                                                                                             |
| Action not completing            | Check that you've authenticated with Slack                                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a user first, then adding to channel). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add the new hire to the engineering channel" will find the user first, then add them. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Slack MCP server](https://www.gumloop.com/mcp/slack) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
