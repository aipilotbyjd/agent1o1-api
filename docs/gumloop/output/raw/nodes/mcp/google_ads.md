> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Ads

> Manage campaigns, keywords, and performance with AI-powered advertising automation.

Google Ads is the world's largest digital advertising platform. The Google Ads MCP server lets you inspect, optimize, and update your campaigns using natural language.

## What Can It Do?

* **Pull campaign performance** metrics including conversions, CTR, and cost per conversion
* **Analyze competitive positioning** with impression share metrics
* **Identify wasted spend** with low quality score keywords
* **Update campaign settings** and budgets without the UI
* **Track conversion value** across campaigns and asset groups
* **Export filtered data** to other tools for alerts

## Where to Use It

### In Agents (Recommended)

Add Google Ads as a tool to any agent. The agent can then interact with your advertising data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Ads tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List enabled campaigns with their performance")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                               | Description                                                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| **List Campaigns**                 | Get campaigns with filters and metrics (includes conversions, CTR, cost per conversion, and conversion value) |
| **Get Campaign**                   | Get details for a single campaign with optional performance metrics                                           |
| **Update Campaign**                | Modify campaign settings                                                                                      |
| **List Asset Groups**              | Get asset groups with filters and performance metrics                                                         |
| **Get Asset Group**                | Get details for an asset group with optional metrics                                                          |
| **Update Asset Group**             | Modify asset group settings                                                                                   |
| **Get Account**                    | Get account-level information with optional metrics                                                           |
| **List Budgets**                   | List campaign budgets with amount, shared status, and usage details                                           |
| **Update Budget**                  | Update a campaign budget's daily amount, name, or delivery method                                             |
| **List Negative Keywords**         | List negative keywords                                                                                        |
| **Get Low Quality Score Keywords** | Find underperforming keywords                                                                                 |
| **Get Overspent Campaigns**        | Find campaigns over budget                                                                                    |
| **Get Competitive Metrics**        | Get impression share and lost impression share metrics for campaigns to analyze competitive positioning       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Campaign performance:**

```text theme={"dark"}
List all enabled campaigns with more than 1000 impressions from the last 30 days
```

**Budget optimization:**

```text theme={"dark"}
Find campaigns that exceeded their daily budget this week
```

**Keyword audit:**

```text theme={"dark"}
Get keywords with quality score below 4 in my search campaigns
```

**Update a campaign:**

```text theme={"dark"}
Pause the Black Friday campaign
```

**Account overview:**

```text theme={"dark"}
Get my account summary including total spend
```

**Competitive positioning:**

```text theme={"dark"}
Get impression share and lost impression share for my search campaigns this month
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific campaign names or IDs                                                                                                                 |
| Action not completing            | Check that you've authenticated and have account access                                                                                            |
| Unexpected results               | The agent may chain multiple tools (e.g., listing campaigns first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Pause the worst performing campaign" will list campaigns, identify the worst one, then pause it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Ads MCP server](https://www.gumloop.com/mcp/gads) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
