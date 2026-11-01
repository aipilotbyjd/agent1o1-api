> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Devin

> Manage AI coding sessions and engineering automation with natural language.

Devin is an AI software engineer that can code, debug, and deploy. The Devin MCP server lets you create sessions, send messages, and manage organization resources using natural language.

## What Can It Do?

* **Start coding sessions** with task prompts and track progress
* **Send messages** to ongoing sessions and fetch details
* **Organize work** with tags on sessions
* **Manage resources** like secrets and knowledge items

## Where to Use It

### In Agents (Recommended)

Add Devin as a tool to any agent. The agent can then interact with your coding sessions conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Devin tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a new coding session")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                            |
| ------------------------ | -------------------------------------- |
| **List Sessions**        | List all coding sessions               |
| **Create Session**       | Start a new session with a task prompt |
| **Get Session**          | Get details for a session              |
| **Send Session Message** | Send a message to a session            |
| **Terminate Session**    | End an active session                  |
| **Update Session Tags**  | Organize sessions with tags            |
| **List Secrets**         | List organization secrets              |
| **Create Secret**        | Create a new encrypted secret          |
| **Delete Secret**        | Remove a secret                        |
| **List Knowledge**       | List knowledge items and folders       |
| **Create Knowledge**     | Create a knowledge item                |
| **Update Knowledge**     | Update a knowledge item                |
| **Delete Knowledge**     | Remove a knowledge item                |

## Example Prompts

Use these with your agent or in the Agent Node:

**Start a coding session:**

```text theme={"dark"}
Create a new session to implement the user authentication feature
```

**Check session status:**

```text theme={"dark"}
Get the status of my active sessions
```

**Send instructions:**

```text theme={"dark"}
Tell the session to focus on writing unit tests first
```

**Organize work:**

```text theme={"dark"}
Tag the authentication session with "sprint-23" and "backend"
```

**Manage knowledge:**

```text theme={"dark"}
Create a knowledge item with our API documentation
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific session names or IDs                                                                                                                   |
| Action not completing            | Check that you've authenticated with your Devin API key                                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., listing sessions first, then sending a message). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Send a message to the auth session" will find the session first, then send the message. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Devin MCP server](https://www.gumloop.com/mcp/devin) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
