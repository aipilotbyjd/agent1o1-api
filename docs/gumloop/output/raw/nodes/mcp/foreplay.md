> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Foreplay

> Research ads and competitor strategies with AI-powered creative intelligence.

Foreplay is an ad research platform with access to 100M+ live and historical ads. The Foreplay MCP server lets you search brands, discover ads, and pull analytics using natural language.

## What Can It Do?

* **Search brands** by name or domain for detailed profiles
* **Discover ads** with filters for platform, format, niche, and date
* **Retrieve brand ads** with advanced filtering options
* **Pull analytics** including ad distribution and creative velocity

## Where to Use It

### In Agents (Recommended)

Add Foreplay as a tool to any agent. The agent can then research ads and competitors conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Foreplay tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for Nike ads on Facebook")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                         | Description                      |
| ---------------------------- | -------------------------------- |
| **Search Brands**            | Search brands by name or domain  |
| **Search And Filter Ads**    | Search ads with multiple filters |
| **Get Ads By Brand Or Page** | Get ads for a specific brand     |
| **Get Brand Analytics**      | Get ad distribution and velocity |
| **Get Ad Details**           | Get comprehensive ad details     |

## Credit Costs

| Tool                     | Credits Per Use |
| ------------------------ | --------------- |
| Search Brands            | 3 per item      |
| Search And Filter Ads    | 3 per item      |
| Get Ads By Brand Or Page | 3 per item      |
| Get Brand Analytics      | 5 per item      |
| Get Ad Details           | 5 per item      |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find a brand:**

```text theme={"dark"}
Search for Nike and get their brand profile
```

**Discover ads:**

```text theme={"dark"}
Find video ads about fitness on Facebook from the last 30 days
```

**Get brand ads:**

```text theme={"dark"}
Show me all of Glossier's Instagram ads from Q4
```

**Analyze a competitor:**

```text theme={"dark"}
Get analytics for Allbirds including their ad distribution by platform
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                      |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific brand names or domains                                                                                                           |
| Action not completing            | Check that you've authenticated and have sufficient Foreplay credits                                                                          |
| Unexpected results               | The agent may chain multiple tools (e.g., searching brands first, then getting ads). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                           |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Analyze Nike's ad strategy" will search the brand, get ads, and pull analytics. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Foreplay MCP server](https://www.gumloop.com/mcp/foreplay) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
