> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# X (Twitter)

> Manage your X presence with AI-powered social media automation.

X (formerly Twitter) is a leading social platform for real-time conversations. The X MCP server lets you search tweets, manage bookmarks, post content, and analyze engagement using natural language.

## What Can It Do?

* **Search tweets** by keyword, user, or date
* **Post and manage tweets** without leaving your workflow
* **Track mentions** and engagement
* **Manage followers** and bookmarks

## Where to Use It

### In Agents (Recommended)

Add X as a tool to any agent. The agent can then manage your X presence conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with X tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search tweets about AI automation")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description                                                  |
| ----------------------- | ------------------------------------------------------------ |
| **Search Tweets**       | Search tweets by query                                       |
| **Get User Tweets**     | Get tweets from a user                                       |
| **Get User Mentions**   | Get tweets mentioning a user                                 |
| **Get User Timeline**   | Get your home timeline                                       |
| **Create Tweet**        | Post a new tweet                                             |
| **Delete Tweet**        | Delete a tweet                                               |
| **Get Bookmarks**       | Get bookmarked tweets                                        |
| **Create Bookmark**     | Bookmark a tweet                                             |
| **Get Users**           | Look up multiple users by their IDs or usernames (up to 100) |
| **Get Followers**       | Get user's followers                                         |
| **Get Following**       | Get accounts a user follows                                  |
| **Manage Follow**       | Follow or unfollow a user                                    |
| **Get Trends By WOEID** | Get trending topics                                          |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search tweets:**

```text theme={"dark"}
Search for tweets about "AI automation" from the last 7 days
```

**Monitor mentions:**

```text theme={"dark"}
Get tweets mentioning @gumloop from today
```

**Post a tweet:**

```text theme={"dark"}
Create a tweet: "Excited to announce our new product launch!"
```

**Get trending topics:**

```text theme={"dark"}
What's trending in the United States right now?
```

**Analyze engagement:**

```text theme={"dark"}
Get users who liked this tweet
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific keywords or user handles                                                                                                      |
| Action not completing            | Check that you've authenticated with X                                                                                                     |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the latest tweets from our competitor" will find the user first, then get tweets. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [X MCP server](https://www.gumloop.com/mcp/x) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
