> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Brandfetch

> Look up brand assets, logos, colors, fonts, and company details for any company.

Brandfetch is a brand data platform that provides logos, colors, fonts, and company information for millions of brands. The Brandfetch MCP server lets you search for brands, retrieve detailed brand assets, and identify brands from payment transaction labels.

## What Can It Do?

* **Search for brands by name** and get matching results with domains, icons, and brand IDs
* **Retrieve complete brand data** including logos (SVG, PNG), brand colors, fonts, company details, social links, industry classification, and financial identifiers — using a domain, brand ID, stock ticker, ISIN, or crypto symbol
* **Identify brands from transaction labels** by matching raw payment text (e.g., "STARBUCKS 1523 OMAHA NE") to brand data, useful for enriching financial records

## Where to Use It

### In Agents (Recommended)

Add Brandfetch as a tool to any agent. The agent can look up any brand's visual identity and company information conversationally.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Brandfetch account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Brandfetch tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get the logo and brand colors for Nike")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                                                                                                                                                                                                                                                                                                          | Credits |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| **Search Brands**      | Search for brands by name. Returns matching brands with their domain, icon, brand ID, and quality score.                                                                                                                                                                                                             | 5       |
| **Get Brand**          | Get complete brand data including logos, colors, fonts, company info, social links, industry, and financial identifiers. Accepts a domain (e.g., `nike.com`), brand ID, stock ticker (e.g., `NKE`), ISIN, or crypto symbol (e.g., `BTC`). You can optionally specify the identifier type to avoid naming collisions. | 30      |
| **Enrich Transaction** | Identify a brand from a raw payment transaction label (e.g., "STARBUCKS 1523 OMAHA NE"). Requires a country code. Returns the matched brand's full data including logos, colors, and company details.                                                                                                                | 30      |

### What Brand Data Includes

The `get_brand` and `enrich_transaction` tools return rich brand data:

* **Logos** — Multiple formats (SVG, PNG) with light/dark theme variants
* **Colors** — Brand accent and primary colors with hex codes
* **Fonts** — Brand typography with font names and types (title, body)
* **Company info** — Employee count, founded year, industry classification, public/private status
* **Financial identifiers** — ISIN numbers and stock tickers for public companies
* **Location** — Headquarters city, country, and region
* **Social links** — Twitter, LinkedIn, and other social media URLs
* **Images** — Banner images and other brand imagery

## Example Prompts

Use these with your agent or in the Agent Node:

**Look up a brand:**

```text theme={"dark"}
Get the logo, brand colors, and company details for Nike
```

**Search by name:**

```text theme={"dark"}
Search for brands matching "Spotify"
```

**Use a stock ticker:**

```text theme={"dark"}
Get the brand data for the company with ticker symbol AAPL
```

**Enrich a transaction:**

```text theme={"dark"}
Identify the brand from this transaction: "AMZN MKTP US*1A2B3C4D" in the US
```

**Get brand assets for design:**

```text theme={"dark"}
Get all logo variants and brand colors for stripe.com
```

## Troubleshooting

| Issue                     | Solution                                                                                                                                    |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Brand not found           | Try searching by name first to find the correct domain or brand ID                                                                          |
| Identifier type collision | Use the `identifier_type` parameter to explicitly specify domain, ticker, isin, or crypto                                                   |
| No logo variants          | Not all brands have complete data. The `qualityScore` field indicates data completeness.                                                    |
| Authentication failed     | Verify your Brandfetch API key is connected and valid                                                                                       |
| Tool not available        | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                         |
| Unexpected results        | The agent may chain multiple tools (e.g., searching first, then fetching details). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the Nike logo" will search for Nike, find the right brand, then retrieve the full brand data with logo URLs. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Brandfetch MCP server](https://www.gumloop.com/mcp/brandfetch) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
