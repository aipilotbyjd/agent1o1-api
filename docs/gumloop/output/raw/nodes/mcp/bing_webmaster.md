> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Bing Webmaster

> Manage your Bing Webmaster Tools with AI-powered SEO automation.

Bing Webmaster Tools helps you monitor and optimize your site's presence in Bing search results. The Bing Webmaster MCP server lets you manage sites, submit URLs, pull search and crawl analytics, and monitor indexing using natural language.

## What Can It Do?

* **Manage sites** — add, verify, and remove sites from your account
* **Submit URLs and content** — push URLs and page content to Bing for crawling and indexing
* **Analyze search traffic** — get query stats, page stats, rank and traffic trends
* **Research keywords** — get impression data and related keywords across Bing
* **Monitor indexing** — check URL index status, inbound links, and connected pages
* **Track crawl health** — view crawl stats, issues, and adjust crawl settings
* **Manage sitemaps** — list, submit, and remove sitemap feeds

## Where to Use It

### In Agents (Recommended)

Add Bing Webmaster as a tool to any agent. The agent can then manage your SEO and indexing conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Bing Webmaster tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get top search queries for my site")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Sites

| Tool            | Description                                     |
| --------------- | ----------------------------------------------- |
| **List Sites**  | List sites in your Bing Webmaster Tools account |
| **Add Site**    | Add a site to your account                      |
| **Verify Site** | Verify ownership of a site                      |
| **Remove Site** | Remove a site from your account                 |

### URL Submission

| Tool                             | Description                                                      |
| -------------------------------- | ---------------------------------------------------------------- |
| **Submit URLs**                  | Submit one or more URLs to Bing for crawling and indexing        |
| **Submit Content**               | Submit a URL together with its page content to Bing for indexing |
| **Fetch URL**                    | Request that Bing fetch a specific URL                           |
| **Get URL Submission Quota**     | Get the remaining URL submission quota for a site                |
| **Get Content Submission Quota** | Get the remaining content submission quota for a site            |
| **List Fetched URLs**            | List URLs that have been fetched for a site                      |
| **Get Fetched URL Details**      | Get details for a single fetched URL                             |

### Search Analytics & Keywords

| Tool                            | Description                                                               |
| ------------------------------- | ------------------------------------------------------------------------- |
| **Get Query Stats**             | Get traffic statistics for a site's top search queries                    |
| **Get Page Stats**              | Get traffic statistics for a site's top pages                             |
| **Get Page Query Stats**        | Get the search queries driving traffic to a specific page                 |
| **Get Query Page Stats**        | Get the pages that rank for a specific search query                       |
| **Get Query Page Detail Stats** | Get detailed traffic statistics for a specific query and page combination |
| **Get Rank and Traffic Stats**  | Get overall rank and traffic statistics for a site over time              |
| **Get Keyword**                 | Get impression data for a keyword over a date range (Bing-wide)           |
| **Get Keyword Stats**           | Get historical impression statistics for a keyword (Bing-wide)            |
| **Get Related Keywords**        | Get keywords related to a query with impression data (Bing-wide)          |

### URL Index, Traffic & Links

| Tool                              | Description                                                        |
| --------------------------------- | ------------------------------------------------------------------ |
| **Get URL Info**                  | Get index details for a single page                                |
| **Get URL Traffic Info**          | Get index traffic details for a single page                        |
| **Get Children URL Info**         | Get index details for the pages under a directory                  |
| **Get Children URL Traffic Info** | Get index traffic details for the pages under a directory          |
| **Get Link Counts**               | Get pages that have inbound links with their link counts           |
| **Get URL Links**                 | Get inbound links for a specific page                              |
| **List Connected Pages**          | List pages connected to the site (pages elsewhere that link to it) |
| **Add Connected Page**            | Register a page that links to the site as connected                |

### Crawl & Feeds

| Tool                      | Description                                                  |
| ------------------------- | ------------------------------------------------------------ |
| **Get Crawl Stats**       | Get crawl statistics for a site                              |
| **Get Crawl Issues**      | Get crawl issues detected for a site                         |
| **Get Crawl Settings**    | Get the crawl settings for a site                            |
| **Update Crawl Settings** | Update the crawl settings for a site                         |
| **List Feeds**            | List the sitemap feeds submitted for a site                  |
| **Get Feed Details**      | Get details for a sitemap feed, including its child sitemaps |
| **Submit Feed**           | Submit a sitemap feed for a site                             |
| **Remove Feed**           | Remove a sitemap feed from a site                            |

## Example Prompts

Use these with your agent or in the Agent Node:

**Check search performance:**

```text theme={"dark"}
What are my top 10 search queries by clicks this month?
```

**Submit new content:**

```text theme={"dark"}
Submit these URLs for indexing: https://example.com/new-page, https://example.com/updated-post
```

**Monitor crawl health:**

```text theme={"dark"}
Are there any crawl issues on my site?
```

**Keyword research:**

```text theme={"dark"}
Get related keywords for "project management software" with impression data
```

**Check indexing status:**

```text theme={"dark"}
Is https://example.com/blog/new-post indexed by Bing?
```

**Manage sitemaps:**

```text theme={"dark"}
Submit my sitemap at https://example.com/sitemap.xml
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                     |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific site URLs when querying stats                                                                                                   |
| Action not completing            | Check that you've authenticated with Bing Webmaster Tools                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., listing sites first, then pulling stats). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                          |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Show me crawl issues and top queries for my site" will list your sites first, then pull the relevant data. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Bing Webmaster MCP server](https://www.gumloop.com/mcp/bing-webmaster) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
