> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# YouTube

> Search and analyze YouTube content with AI-powered video automation.

YouTube is the world's largest video platform with billions of users. The YouTube MCP server lets you search videos, get channel data, and collect comments using natural language.

## What Can It Do?

* **Search videos** by keyword with filters
* **Get video details** with full metadata
* **Analyze channels** and their content
* **Collect comments** for sentiment analysis

## Where to Use It

### In Agents (Recommended)

Add YouTube as a tool to any agent. The agent can then search and analyze YouTube data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with YouTube tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for Python tutorial videos")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description                    |
| ----------------------- | ------------------------------ |
| **Search Videos**       | Search by keyword with filters |
| **Get Video Details**   | Get full video metadata        |
| **Get Channel Videos**  | List videos from a channel     |
| **Get Playlist Videos** | Get videos from a playlist     |
| **Get Channel Details** | Get channel stats              |
| **Get Video Comments**  | Collect comments               |

## Credit Costs

| Tool                | Credits    |
| ------------------- | ---------- |
| Search Videos       | 3 per item |
| Get Video Details   | 4 per item |
| Get Channel Videos  | 3 per item |
| Get Playlist Videos | 3 per item |
| Get Channel Details | 5 per item |
| Get Video Comments  | 5 per item |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search videos:**

```text theme={"dark"}
Find Python pandas tutorials uploaded this year
```

**Get video details:**

```text theme={"dark"}
Get the details for this YouTube video URL
```

**Analyze a channel:**

```text theme={"dark"}
How many subscribers does @mkbhd have?
```

**Get comments:**

```text theme={"dark"}
Get the top 50 comments from this video
```

**Channel content:**

```text theme={"dark"}
Show me the latest 10 videos from @MrBeast
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use exact channel handles or video URLs                                                                                                           |
| Action not completing            | Check that you've authenticated                                                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a channel first, then getting videos). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the top comments from MrBeast's latest video" will find the channel, get recent videos, then fetch comments. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [YouTube MCP server](https://www.gumloop.com/mcp/youtube) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
