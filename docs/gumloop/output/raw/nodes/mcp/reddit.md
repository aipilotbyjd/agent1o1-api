> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Reddit

> Search and engage with Reddit communities using AI-powered automation.

Reddit is a social platform with millions of users across 100k+ communities. The Reddit MCP server lets you search subreddits, fetch posts, manage comments, and publish content using natural language.

<Warning>
  **Reddit MCP nodes require you to bring your own Reddit app credentials.** If you only need read-only access, use the [Reddit Scraper](/nodes/integrations/reddit_scraper) node instead, which works without credentials.

  If you only need to fetch posts, comments, or search subreddits, use the **[Reddit Scraper](/nodes/integrations/reddit_scraper)** node instead. It works out of the box without any custom credentials and is easier to set up.
</Warning>

## What Can It Do?

* **Search subreddits** and discover communities
* **Fetch posts** with full details and comments
* **Create and edit posts** in any subreddit
* **Manage comments** on your posts

## Where to Use It

### In Agents (Recommended)

Add Reddit as a tool to any agent. The agent can then interact with Reddit conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Reddit app credentials

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Reddit tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get top posts from r/programming")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Setting Up Credentials

Reddit MCP requires your own Reddit app credentials. See the [Reddit app preferences](https://www.reddit.com/prefs/apps) to create an app, or request [enterprise API access](https://support.reddithelp.com/hc/en-us/requests/new?ticket_form_id=14868593862164\&tf_14867328473236=api_request_type_enterprise) for high-volume use cases.

## Available Tools

| Tool                        | Description                      |
| --------------------------- | -------------------------------- |
| **Retrieve Reddit Post**    | Fetch top posts from a subreddit |
| **Get Reddit Post Details** | Get full details of a post       |
| **Create Reddit Post**      | Publish a new post               |
| **Edit Reddit Post**        | Update an existing post          |
| **Delete Reddit Post**      | Remove a post                    |
| **Fetch Post Comments**     | Get comments on a post           |
| **Create Reddit Comment**   | Add a comment                    |
| **Edit Reddit Comment**     | Update a comment                 |
| **Delete Reddit Comment**   | Remove a comment                 |
| **Search Subreddits**       | Find subreddits by name          |
| **Search Posts**            | Search posts across Reddit       |
| **Search Users**            | Find users by profile            |

## Example Prompts

Use these with your agent or in the Agent Node:

**Get trending posts:**

```text theme={"dark"}
Get the top 10 hot posts from r/technology
```

**Search subreddits:**

```text theme={"dark"}
Find subreddits about machine learning
```

**Get post details:**

```text theme={"dark"}
Get the full details and comments for this Reddit post URL
```

**Search posts:**

```text theme={"dark"}
Search r/startups for posts about fundraising
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific subreddit names or post URLs                                                                                                  |
| Action not completing            | Check that you've configured Reddit app credentials                                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |
| Authentication errors            | Verify your Reddit app credentials in [Connectors page](https://www.gumloop.com/personal/connectors)                                       |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get comments from the top post in r/programming" will fetch posts first, then get comments. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* [Reddit Scraper](/nodes/integrations/reddit_scraper) for read-only access without credentials
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Reddit MCP server](https://www.gumloop.com/mcp/reddit) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
