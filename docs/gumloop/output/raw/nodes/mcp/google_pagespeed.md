> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google PageSpeed

> Analyze website performance, accessibility, SEO, and Core Web Vitals using Google PageSpeed Insights.

Google PageSpeed Insights is Google's tool for measuring webpage performance. The Google PageSpeed MCP server lets you run Lighthouse audits, get performance scores, and check Core Web Vitals field data for any URL.

## What Can It Do?

* **Run full PageSpeed analysis** to get Lighthouse scores across performance, accessibility, best practices, and SEO
* **Get detailed Lighthouse audits** for a specific category with individual audit results and recommendations
* **Check Core Web Vitals** field data (LCP, FID, CLS, INP, TTFB) from real-user metrics
* **Analyze for desktop or mobile** with locale-specific results

## Where to Use It

### In Agents (Recommended)

Add Google PageSpeed as a tool to any agent. The agent can analyze any URL conversationally and explain the results.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Google PageSpeed API key

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google PageSpeed tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run a mobile performance audit on my homepage")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                       | Description                                                                                                                                                                                                                                                       | Credits |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| **Run PageSpeed Analysis** | Run a full PageSpeed analysis on a URL. Returns Lighthouse scores for selected categories (performance, accessibility, best practices, SEO), performance metrics, and real-user loading experience data. Supports desktop or mobile strategy and locale settings. | 5       |
| **Get Lighthouse Audits**  | Get detailed Lighthouse audit results for a specific category on a URL. Returns individual audit pass/fail results with descriptions and recommendations.                                                                                                         | 5       |
| **Get Core Web Vitals**    | Get Core Web Vitals field data (LCP, FID, CLS, INP, TTFB) for a URL. Returns real-user loading experience metrics with percentile values and performance categories (FAST, AVERAGE, SLOW).                                                                        | 5       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Full site analysis:**

```text theme={"dark"}
Run a PageSpeed analysis on https://example.com for both desktop and mobile
```

**SEO audit:**

```text theme={"dark"}
Get the SEO audit results for https://example.com
```

**Core Web Vitals check:**

```text theme={"dark"}
Check the Core Web Vitals for https://example.com on mobile
```

**Accessibility review:**

```text theme={"dark"}
Run an accessibility audit on https://example.com and list the failing checks
```

**Compare strategies:**

```text theme={"dark"}
Compare the performance scores of https://example.com on desktop vs mobile
```

## Troubleshooting

| Issue                      | Solution                                                                                                                                               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| No loading experience data | Field data requires sufficient real-user traffic. New or low-traffic pages may not have Core Web Vitals data available.                                |
| Authentication failed      | Verify your Google PageSpeed API key is connected and valid                                                                                            |
| Slow response times        | PageSpeed analysis runs a full Lighthouse audit which can take 10-30 seconds per request                                                               |
| Tool not available         | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                    |
| Unexpected results         | The agent may chain multiple tools (e.g., running a full analysis then drilling into audits). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "What's wrong with my site's performance?" will run the analysis, then drill into the performance audits to identify specific issues. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google PageSpeed MCP server](https://www.gumloop.com/mcp/gpagespeed) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
