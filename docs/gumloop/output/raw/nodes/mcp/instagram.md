> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Instagram

> Scrape public Instagram data with AI-powered social media automation.

Instagram is the world's leading photo and video sharing platform. The Instagram MCP server lets you search profiles, pull posts, download reels, and collect engagement data using natural language.

## What Can It Do?

* **Scrape posts and reels** from any public profile by username or directly by URL
* **Pull comments** and engagement metrics
* **Search profiles** by name or username
* **Collect hashtag feeds** for content discovery

## Where to Use It

### In Agents (Recommended)

Add Instagram as a tool to any agent. The agent can then search and analyze Instagram data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Instagram tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get the latest 10 posts from @nike")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description                                                                                                                 |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **Scrape Posts**        | Scrape posts by username or directly by post URL. Returns captions, likes, comments, media URLs, tagged users, and metadata |
| **Scrape Reels**        | Scrape reels by username or directly by reel URL. Returns captions, view counts, likes, duration, hashtags, and video URLs  |
| **Get Post Comments**   | Retrieves comments on a post                                                                                                |
| **Get Hashtag Posts**   | Collects posts using a hashtag                                                                                              |
| **Find Users**          | Searches profiles by name                                                                                                   |
| **Get Profile Details** | Fetches profile metadata                                                                                                    |
| **Get Profile Stories** | Downloads active stories                                                                                                    |
| **Get Tagged Posts**    | Gets posts where user is tagged                                                                                             |

## Credit Costs

| Tool                | Credits Per Use |
| ------------------- | --------------- |
| Scrape Posts        | 3 per item      |
| Scrape Reels        | 3 per item      |
| Get Post Comments   | 3 per item      |
| Get Hashtag Posts   | 3 per item      |
| Find Users          | 3 per item      |
| Get Profile Details | 5 per item      |
| Get Profile Stories | 3 per item      |
| Get Tagged Posts    | 3 per item      |

## Example Prompts

Use these with your agent or in the Agent Node:

**Scrape posts by username:**

```text theme={"dark"}
Scrape the latest 15 posts from @nike with captions and like counts
```

**Scrape posts by URL:**

```text theme={"dark"}
Scrape these Instagram posts: https://www.instagram.com/p/ABC123/ and https://www.instagram.com/p/DEF456/
```

**Scrape reels by username:**

```text theme={"dark"}
Scrape 10 reels from @natgeo with view counts and durations
```

**Scrape reels by URL:**

```text theme={"dark"}
Scrape these Instagram reels: https://www.instagram.com/reel/XYZ789/
```

**Search hashtags:**

```text theme={"dark"}
Find 20 posts using #coffeeshop
```

**Find influencers:**

```text theme={"dark"}
Search for fitness coach profiles with over 10k followers
```

**Get profile info:**

```text theme={"dark"}
Get the follower count and bio for @natgeo
```

**Pull comments:**

```text theme={"dark"}
Get the comments from this Instagram post URL
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use exact usernames with @ symbol, or provide direct Instagram URLs                                                                              |
| Action not completing            | Check that you've authenticated and the profile is public                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a profile first, then getting posts). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                              |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get reels from the top fitness influencer" will search profiles first, then get reels. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Instagram MCP server](https://www.gumloop.com/mcp/instagram) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
