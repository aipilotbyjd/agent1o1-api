> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Semrush

> Pull SEO and marketing analytics with AI-powered competitive research automation.

Semrush is a leading SEO and digital marketing platform. The Semrush MCP server lets you analyze domains, research keywords, and audit backlinks using natural language.

## Prerequisites

Semrush requires your own API key, available on Semrush subscription plans that include API access. Add it on your [Credentials page](https://www.gumloop.com/personal/connectors) before connecting Semrush to an agent.

<Tip>
  No Semrush plan? [DataForSEO](/nodes/mcp/dataforseo) covers the same keyword research, competitor analysis, and backlink data, and works without any account of your own.
</Tip>

## What Can It Do?

* **Analyze domains** for keywords, traffic, and competitors
* **Research keywords** with volume, difficulty, and SERP data
* **Audit backlinks** and referring domains
* **Compare competitors** by shared or unique keywords

## Where to Use It

### In Agents (Recommended)

Add Semrush as a tool to any agent. The agent can then perform SEO research conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Semrush tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get domain keywords for hubspot.com")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                                      | Description                                                                                                                                           |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Get Domain Rank Report**                | Get SEMrush rank report showing the most popular domains ranked by traffic from Google's top 100 organic search results                               |
| **Get Domain Overview Data**              | Get overview data across all regional databases showing keyword rankings in both organic and paid search for domains, subdomains, subfolders, or URLs |
| **Get Winners Losers Report**             | Get changes in keyword rankings, traffic, and budget estimates of the most popular websites showing winners and losers                                |
| **Get Domain Keywords**                   | Get keywords that bring users to a target via Google's organic or paid search results for domains, subdomains, subfolders, or URLs                    |
| **Get Domain Ad Copies**                  | Get unique ad copies that appeared when target ranked in Google's paid search results for domains, subdomains, or subfolders                          |
| **Get Domain Competitors**                | Get domain's competitors in organic or paid search results                                                                                            |
| **Get Domain Ad History**                 | Get keywords a domain has bid on in the last 12 months and its positions in paid search results                                                       |
| **Compare Domains By Keywords**           | Compare up to five domains by common keywords, unique keywords, or all keywords                                                                       |
| **Get Domain Pla Keywords**               | Get keywords that trigger a domain's product listing ads (PLA) in Google's paid search results                                                        |
| **Get Domain Pla Copies**                 | Get product listing ad (PLA) copies that appeared when domain ranked in Google's paid search results                                                  |
| **Get Domain Pla Competitors**            | Get domains that compete against the requested domain in Google's paid search results with product listing ads (PLA)                                  |
| **Get Domain Organic Subdomains**         | Get subdomains of the analyzed domain ranking in Google's top 100 organic search results                                                              |
| **Get Organic Pages**                     | Get unique pages ranking in Google's top 100 organic search results for domains, subdomains, or subfolders                                            |
| **Get Keyword Overview**                  | Get keyword overview data for a specific database or across all regional databases including volume, CPC, and competition                             |
| **Get Keyword Search Results**            | Get domains ranking in Google's organic or paid search results for a keyword                                                                          |
| **Research Related Keywords**             | Get keyword research data including related keywords, broad match keywords, or question-based keywords                                                |
| **Get Keyword Ads History**               | Get domains that have bid on a keyword in the last 12 months and their positions                                                                      |
| **Get Keyword Difficulty Score**          | Get keyword difficulty index to estimate how difficult it would be to rank in Google's top 10 for a keyword                                           |
| **Get Backlinks Overview**                | Get a summary of backlinks including type, referring domains, and IP addresses for a domain                                                           |
| **Get Backlinks List**                    | Get a list of backlinks for a domain, root domain, or URL                                                                                             |
| **Analyze Backlinks Data**                | Get backlinks analysis data including referring domains, IPs, TLD distribution, geographical distribution, or anchor texts                            |
| **Get Backlinks Pages**                   | Get indexed pages of the queried domain                                                                                                               |
| **Get Backlinks Competitors**             | Get domains that share a similar backlink profile with the analyzed domain                                                                            |
| **Get Backlinks Authority Score Profile** | Get distribution of referring domains by Authority Score                                                                                              |
| **Get Backlinks Categories Profile**      | Get categories that referring domains belong to                                                                                                       |
| **Get Domain Categories**                 | Get categories that the queried domain belongs to                                                                                                     |
| **Get Backlinks Historical Data**         | Get monthly historical trends of backlinks and referring domains                                                                                      |
| **Get Subdomain Competitors**             | Get subdomain's competitors in organic or paid search results                                                                                         |
| **Get Traffic Summary**                   | Get traffic summary metrics including total visits, unique visitors, pages per visit, and bounce rate for domains                                     |
| **Get Daily Traffic**                     | Get day-by-day traffic breakdown including visits, traffic sources (direct, organic, paid, social), and engagement metrics                            |
| **Get Weekly Traffic**                    | Get week-by-week traffic analysis including visits, traffic sources, and engagement metrics for broader trend analysis                                |
| **Get Traffic Sources**                   | Get detailed traffic sources breakdown by channel (direct, search, social, referral, email, display) and type (organic, paid)                         |
| **Get Purchase Conversion**               | Get purchase conversion rate showing percentage of sessions ending in a purchase (desktop only, requires Premium API access)                          |
| **Get Top Pages**                         | Get most popular pages of domains showing which content resonates most with the audience                                                              |
| **Get Traffic Rank**                      | Get domains sorted by traffic rank to benchmark against competitors and understand relative market position                                           |

Semrush tools do not consume Gumloop credits. Usage is billed by Semrush against your own plan's API units.

## Example Prompts

Use these with your agent or in the Agent Node:

**Competitive analysis:**

```text theme={"dark"}
Who are the top organic competitors for monday.com?
```

**Keyword research:**

```text theme={"dark"}
Get the search volume and difficulty for "digital nomad visa"
```

**Backlink audit:**

```text theme={"dark"}
How many backlinks does shopify.com have?
```

**Domain overview:**

```text theme={"dark"}
Give me an overview of tesla.com's organic and paid traffic
```

**Compare domains:**

```text theme={"dark"}
Compare shopify.com, wix.com, and bigcommerce.com by unique keywords
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use exact domain names without https\://                                                                                                   |
| Action not completing            | Check that you've authenticated with Semrush                                                                                               |
| Unexpected results               | The agent may chain multiple tools (e.g., getting overview first, then keywords). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Analyze competitor.com's SEO" will gather overview, keywords, and backlinks. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Semrush MCP server](https://www.gumloop.com/mcp/semrush) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
