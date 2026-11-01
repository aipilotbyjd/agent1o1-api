> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Firecrawl

> Search, scrape, and map websites using Firecrawl's web data API.

Firecrawl is a web scraping API that turns websites into clean, structured data. The Firecrawl MCP server lets you search, scrape, crawl, and extract data from websites using natural language.

## What Can It Do?

* **Search the web** with optional scraping and source filtering
* **Scrape single URLs** for content in markdown, HTML, or other formats
* **Map websites** to get all URLs ordered by relevance
* **Crawl entire sites** and extract content from multiple pages
* **Deep extract** data by autonomously navigating and exploring links
* **Interact with pages** using natural language prompts or browser code
* **Manage interactive browser sessions** for persistent browser automation

## Where to Use It

### In Agents (Recommended)

Add Firecrawl as a tool to any agent. The agent can then scrape and extract web data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Firecrawl tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Scrape this URL and get the content")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                         | Description                                                                                                                                          | Credits    |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| **Search**                   | Search the web and optionally scrape full page content. Returns results organized by source type (web, images, news).                                | 8 per item |
| **Scrape**                   | Scrape a single URL and extract content in various formats. Supports persistent profiles to reuse browser state across sessions.                     | 8          |
| **Map**                      | Get all URLs from a website. Returns a list of URLs ordered by relevance.                                                                            | 1          |
| **Crawl**                    | Crawl a website and extract content from multiple pages.                                                                                             | 40         |
| **Get Crawl Status**         | Get the status and results of a crawl job.                                                                                                           | 8 per item |
| **Batch Scrape**             | Scrape multiple URLs at once.                                                                                                                        | 40         |
| **Get Batch Scrape Status**  | Get the status and results of a batch scrape job.                                                                                                    | 8 per item |
| **Deep Extract**             | Autonomously navigate and extract data from websites based on a prompt. Unlike regular extract, this explores links and pages to find relevant data. | 120        |
| **Get Deep Extract Status**  | Get the status and results of a deep extract job.                                                                                                    | 3          |
| **Interact**                 | Interact with a previously scraped page using a natural language prompt or browser code.                                                             | 14         |
| **Stop Interact**            | Stop an interactive browser session to release resources.                                                                                            | 3          |
| **Create Interact Session**  | Start a standalone interactive browser session. Supports persistent profiles scoped per user.                                                        | 4          |
| **Execute Interact Session** | Run Playwright or agent-browser code in a standalone interact session.                                                                               | 4          |
| **Delete Interact Session**  | Stop a standalone interact session to release browser resources.                                                                                     | 3          |
| **List Interact Sessions**   | List standalone interact sessions, optionally filtered by status.                                                                                    | 3          |

<Info>
  The Gumloop-managed Firecrawl key supports searching, scraping, mapping, crawling, and extraction tools. Interactive browser tools — **Interact**, **Stop Interact**, **Create Interact Session**, **Execute Interact Session**, **Delete Interact Session**, and **List Interact Sessions** — require your own Firecrawl API key. Connect it in your [Connectors page](https://www.gumloop.com/personal/connectors).
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Scrape a page:**

```text theme={"dark"}
Scrape this URL and get the main content as markdown
```

**Search the web:**

```text theme={"dark"}
Search for "AI startup funding" and get the top 10 results
```

**Map a website:**

```text theme={"dark"}
Get all URLs from example.com
```

**Crawl a site:**

```text theme={"dark"}
Crawl example.com/blog with depth 2 and get all article content
```

**Deep extract:**

```text theme={"dark"}
Extract pricing information from this SaaS website, exploring all relevant pages
```

**Batch scrape:**

```text theme={"dark"}
Scrape these 5 URLs and get the main content from each
```

**Interactive browser session:**

```text theme={"dark"}
Create a browser session, navigate to example.com, and click the login button
```

## Troubleshooting

| Issue                             | Solution                                                                                                                                        |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data  | Ensure the URL is publicly accessible                                                                                                           |
| Interactive tool requires API key | Interactive browser tools require your own Firecrawl API key. Connect it in your [Connectors page](https://www.gumloop.com/personal/connectors) |
| Action not completing             | Check that you've authenticated and have sufficient Firecrawl credits                                                                           |
| Unexpected results                | The agent may chain multiple tools (e.g., mapping first, then scraping). Review the agent's reasoning to understand its approach.               |
| Tool not available                | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get all blog posts from this site" will map the URLs first, then scrape each one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Firecrawl MCP server](https://www.gumloop.com/mcp/firecrawl) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
