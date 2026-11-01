> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Search Console

> Query search performance, inspect URLs, and manage sitemaps with AI-powered SEO automation.

Google Search Console is Google's tool for monitoring and maintaining your site's presence in search results. The Google Search Console MCP server lets you query search analytics, inspect URL indexing status, and manage sitemaps using natural language.

## What Can It Do?

* **Query search performance data** with dimensions like query, page, country, and device
* **Inspect URL indexing status** to check if Google can find and index your pages
* **List and manage sitemaps** submitted to Search Console
* **View Search Console properties** and permission levels
* **Filter analytics** by date ranges, search types, and custom dimension filters

## Where to Use It

### In Agents (Recommended)

Add Google Search Console as a tool to any agent. The agent can then analyze your search performance and indexing status conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Google account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Search Console tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get top queries for my site last month")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                       | Description                                                                                    |
| -------------------------- | ---------------------------------------------------------------------------------------------- |
| **List Sites**             | List Search Console properties accessible to the user                                          |
| **Get Site**               | Get permission details for a Search Console property                                           |
| **Query Search Analytics** | Query search performance data with dimensions (query, page, country, device, date) and filters |
| **Inspect URL**            | Inspect Google indexing status for a URL including crawl and index coverage                    |
| **List Sitemaps**          | List submitted sitemaps for a Search Console property                                          |
| **Get Sitemap**            | Get details for a submitted sitemap including processing status and content breakdown          |

## Example Prompts

Use these with your agent or in the Agent Node:

**Check search performance:**

```text theme={"dark"}
Show me the top 20 queries for my site in the last 30 days sorted by clicks
```

**Analyze page performance:**

```text theme={"dark"}
What pages on example.com got the most impressions last month?
```

**Inspect a URL:**

```text theme={"dark"}
Is https://example.com/blog/my-post indexed by Google?
```

**Filter by device:**

```text theme={"dark"}
Compare my site's search performance on mobile vs desktop for the past week
```

**Check sitemaps:**

```text theme={"dark"}
List all sitemaps submitted for https://example.com and their status
```

**Country breakdown:**

```text theme={"dark"}
Show me search clicks and impressions by country for example.com last quarter
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| No data returned      | Search Console data is typically available with a 2-3 day delay. Try querying an older date range.                                                |
| Authentication failed | Verify your Google account has access to the Search Console property you're querying                                                              |
| Property not found    | Use the exact property URL format from Search Console (e.g., `https://www.example.com/` or `sc-domain:example.com`)                               |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |
| Unexpected results    | The agent may chain multiple tools (e.g., listing sites first, then querying analytics). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "How is my site performing?" will list your properties first, then query search analytics for the relevant one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Search Console MCP server](https://www.gumloop.com/mcp/gsearchconsole) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
