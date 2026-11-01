> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Maps

> Find and enrich location data with AI-powered place discovery.

Google Maps is the world's most popular mapping platform with millions of businesses and places. The Google Maps MCP server lets you search places, get details, and collect reviews using natural language.

## What Can It Do?

* **Search places** by keyword, category, or location
* **Get place details** including contact info, ratings, and hours
* **Collect customer reviews** for reputation monitoring
* **Build lead lists** with geo-targeted searches

## Where to Use It

### In Agents (Recommended)

Add Google Maps as a tool to any agent. The agent can then search and explore location data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Maps tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Find coffee shops near Times Square")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                    | Description                    |
| ----------------------- | ------------------------------ |
| **Search Places**       | Search by location and keyword |
| **Get Place Details**   | Get full info for a place      |
| **Search By Category**  | Find places by category        |
| **Get Place Reviews**   | Pull customer reviews          |
| **Find Places In Area** | List places in a map area      |

## Credit Costs

| Tool                | Credits Per Use |
| ------------------- | --------------- |
| Search Places       | 3 per item      |
| Get Place Details   | 5 per item      |
| Search By Category  | 3 per item      |
| Get Place Reviews   | 3 per item      |
| Find Places In Area | 3 per item      |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search places:**

```text theme={"dark"}
Find coffee shops within 5 miles of Times Square
```

**Get details:**

```text theme={"dark"}
Get the contact info and rating for this restaurant
```

**Search by category:**

```text theme={"dark"}
Find coworking spaces in Austin, TX
```

**Get reviews:**

```text theme={"dark"}
Get the latest reviews for this business
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Be specific with location and search terms                                                                                                 |
| Action not completing            | Check that you've authenticated and have API credits                                                                                       |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get reviews for the best-rated coffee shop in Seattle" will search first, then get reviews. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Maps MCP server](https://www.gumloop.com/mcp/gmaps) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
