> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Cursor

> Manage Cursor cloud agents to launch, monitor, and control AI coding agents on your repositories.

Cursor is an AI-powered code editor. The Cursor MCP server lets you launch and manage cloud agents that work on your GitHub repositories using natural language.

## What Can It Do?

* **Launch cloud agents** to work on GitHub repositories
* **Monitor agent status** and view conversation history
* **Send follow-up instructions** to running agents
* **Stop and delete agents** when done

## Where to Use It

### In Agents (Recommended)

Add Cursor as a tool to any agent. The agent can then interact with Cursor conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Cursor tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Launch an agent to fix the login bug")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                       | Description                                             |
| -------------------------- | ------------------------------------------------------- |
| **List Agents**            | List all cloud agents for the authenticated user        |
| **Launch Agent**           | Launch a new cloud agent to work on a GitHub repository |
| **Get Agent**              | Get a cloud agent's status and results                  |
| **Get Agent Conversation** | Get a cloud agent's conversation history                |
| **Add Followup**           | Send a follow-up instruction to a running cloud agent   |
| **Stop Agent**             | Stop a running cloud agent                              |
| **Delete Agent**           | Permanently delete a cloud agent                        |
| **List Models**            | List available LLM models for cloud agents              |
| **List Repositories**      | List accessible GitHub repositories                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Launch an agent:**

```text theme={"dark"}
Launch a Cursor agent to refactor the auth module in my-repo
```

**Check status:**

```text theme={"dark"}
What's the status of my running Cursor agents?
```

**Send follow-up:**

```text theme={"dark"}
Tell my Cursor agent to also update the tests
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Cursor credentials and that you have the required permissions                                           |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |
| Unexpected results    | The agent may chain multiple tools together. Review the agent's reasoning to understand its approach.               |

<Tip>
  Agents are smart enough to chain multiple API calls together. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Cursor MCP server](https://www.gumloop.com/mcp/cursor) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
