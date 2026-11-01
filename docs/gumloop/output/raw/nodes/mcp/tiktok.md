> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# TikTok

> Scrape TikTok data with AI-powered social media automation.

TikTok is a leading short-form video platform with billions of users. The TikTok MCP server lets you search videos, analyze profiles, and collect engagement data using natural language.

## What Can It Do?

* **Get hashtag videos** with engagement metrics
* **Analyze creator profiles** and their content
* **Export follower lists** for research
* **Pull video details** including stats and music info

## Where to Use It

### In Agents (Recommended)

Add TikTok as a tool to any agent. The agent can then search and analyze TikTok data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with TikTok tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get the top 20 #fitness videos")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                      | Description                                                          |
| ------------------------- | -------------------------------------------------------------------- |
| **Get Hashtag Videos**    | Get videos for a hashtag                                             |
| **Get Profile Videos**    | List videos from a profile                                           |
| **Get Profile Followers** | List followers for a profile                                         |
| **Get Video Details**     | Get full video metadata                                              |
| **Search Videos**         | Search by keyword                                                    |
| **Get Video Comments**    | Get comments from videos with text, usernames, likes, and timestamps |

## Credit Costs

| Tool                  | Credits    |
| --------------------- | ---------- |
| Get Hashtag Videos    | 3 per item |
| Get Profile Videos    | 3 per item |
| Get Profile Followers | 3 per item |
| Get Video Details     | 5 per item |
| Search Videos         | 3 per item |
| Get Video Comments    | 3 per item |

## Example Prompts

Use these with your agent or in the Agent Node:

**Get trending content:**

```text theme={"dark"}
Get the top 20 #travelhacks videos
```

**Analyze a creator:**

```text theme={"dark"}
Show me the latest 15 videos from @gymshark
```

**Search for content:**

```text theme={"dark"}
Search TikTok for "meal prep" videos
```

**Get video details:**

```text theme={"dark"}
Get the details for this TikTok video URL
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use exact usernames with @ symbol                                                                                                                 |
| Action not completing            | Check that you've authenticated                                                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a profile first, then getting videos). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get engagement stats for the top fitness influencer" will search profiles first, then get videos. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [TikTok MCP server](https://www.gumloop.com/mcp/tiktok) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
