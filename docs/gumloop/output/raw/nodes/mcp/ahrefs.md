> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Ahrefs

> Analyze backlinks, keywords, and SEO performance with AI-powered search marketing automation.

Ahrefs is a comprehensive SEO toolset for backlink analysis, keyword research, rank tracking, and site auditing. The Ahrefs MCP server lets you access SEO data and analytics using natural language.

## What Can It Do?

* **Analyze backlinks and domains** with ratings, referring domains, and anchor text
* **Research keywords** with volume, difficulty, SERP data, and suggestions
* **Track rankings** across projects and competitors
* **Audit sites** for SEO issues and page content
* **Monitor brand mentions** in AI chatbot responses

## Where to Use It

### In Agents (Recommended)

Add Ahrefs as a tool to any agent. The agent can then analyze SEO data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Ahrefs tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get the domain rating for example.com")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Site Explorer

| Tool                            | Description                                            |
| ------------------------------- | ------------------------------------------------------ |
| **Domain Rating**               | Get domain rating for a domain                         |
| **Backlinks Stats**             | Get backlinks statistics for a domain or URL           |
| **Outlinks Stats**              | Get outlinks statistics for a domain or URL            |
| **Metrics**                     | Get comprehensive metrics for a domain or URL          |
| **Metrics By Country**          | Get metrics filtered by country for a domain or URL    |
| **Pages By Traffic**            | Get pages by traffic for a domain or URL               |
| **Domain Rating History**       | Get domain rating history for a domain                 |
| **URL Rating History**          | Get URL rating history for a URL                       |
| **Refdomains History**          | Get referring domains history for a domain or URL      |
| **Pages History**               | Get pages history for a domain or URL                  |
| **Metrics History**             | Get metrics history for a domain or URL                |
| **Keywords History**            | Get keywords history for a domain or URL               |
| **Total Search Volume History** | Get total search volume history for a domain or URL    |
| **Backlinks**                   | Get backlinks for a domain or URL                      |
| **Broken Backlinks**            | Get broken backlinks for a domain or URL               |
| **Refdomains**                  | Get referring domains for a domain or URL              |
| **Anchors**                     | Get anchor text for a domain or URL                    |
| **Organic Keywords**            | Get organic keywords for a domain or URL               |
| **Organic Competitors**         | Get organic competitors for a domain or URL            |
| **Top Pages**                   | Get top organic pages for a domain                     |
| **Paid Pages**                  | Get paid pages for a domain or URL                     |
| **Best By External Links**      | Get pages with the most external links                 |
| **Best By Internal Links**      | Get pages with the most internal links                 |
| **Linked Domains**              | Get domains that are linked from the target            |
| **Outgoing External Anchors**   | Get external anchor texts used in outgoing links       |
| **Outgoing Internal Anchors**   | Get internal anchor texts used in outgoing links       |
| **Batch Analysis**              | Batch analyze multiple URLs or domains for SEO metrics |

### Keywords Explorer

| Tool                   | Description                                     |
| ---------------------- | ----------------------------------------------- |
| **Keywords Overview**  | Get metrics for keywords from Keywords Explorer |
| **Volume History**     | Get search volume history for a keyword         |
| **Volume By Country**  | Get search volume by country for a keyword      |
| **Matching Terms**     | Get matching terms for keywords                 |
| **Related Terms**      | Get related terms for keywords                  |
| **Search Suggestions** | Get search suggestions for keywords             |
| **SERP Overview**      | Get top SERP results for a keyword              |

### Rank Tracker

| Tool                                  | Description                                              |
| ------------------------------------- | -------------------------------------------------------- |
| **Rank Tracker Overview**             | Get keyword rankings overview for a Rank Tracker project |
| **Rank Tracker Competitors Overview** | Get competitor rankings for a Rank Tracker project       |
| **Rank Tracker Competitors Pages**    | Get competitor pages for a Rank Tracker project          |
| **Rank Tracker Competitors Stats**    | Get competitor statistics for a Rank Tracker project     |
| **Rank Tracker SERP Overview**        | Get SERP overview for a tracked keyword in Rank Tracker  |

### Site Audit

| Tool                         | Description                                 |
| ---------------------------- | ------------------------------------------- |
| **Site Audit Projects**      | List Site Audit projects with health scores |
| **Site Audit Issues**        | Get SEO issues found by Site Audit          |
| **Site Audit Page Content**  | Get page content from a Site Audit crawl    |
| **Site Audit Page Explorer** | Explore pages from a Site Audit crawl       |

### Brand Radar

| Tool                                 | Description                                      |
| ------------------------------------ | ------------------------------------------------ |
| **Brand Radar AI Responses**         | Get AI chatbot responses mentioning brands       |
| **Brand Radar Cited Domains**        | Get domains cited in AI responses about brands   |
| **Brand Radar Cited Pages**          | Get pages cited in AI responses about brands     |
| **Brand Radar Impressions Overview** | Get brand impression statistics from AI chatbots |
| **Brand Radar Mentions Overview**    | Get brand mention statistics from AI chatbots    |
| **Brand Radar Impressions History**  | Get brand impressions over time from AI chatbots |
| **Brand Radar Mentions History**     | Get brand mentions over time from AI chatbots    |
| **Brand Radar SOV History**          | Get share of voice history from AI chatbots      |
| **Brand Radar SOV Overview**         | Get share of voice overview from AI chatbots     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Check domain authority:**

```text theme={"dark"}
Get the domain rating for competitor.com
```

**Analyze backlinks:**

```text theme={"dark"}
Show me the backlink stats for our website example.com
```

**Research keywords:**

```text theme={"dark"}
Get keyword overview for "project management software"
```

**Find competitors:**

```text theme={"dark"}
Who are the organic competitors for example.com?
```

**Audit site health:**

```text theme={"dark"}
List all Site Audit projects and their health scores
```

**Track brand mentions:**

```text theme={"dark"}
Show AI chatbot responses that mention our brand
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use exact domain names without protocol (e.g., "example.com" not "[https://example.com](https://example.com)")                                   |
| Action not completing            | Check that you've authenticated with Ahrefs                                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., getting domain rating first, then backlinks). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                              |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Compare our backlink profile with competitor.com" will analyze both domains then present results. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Ahrefs MCP server](https://www.gumloop.com/mcp/ahrefs) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
