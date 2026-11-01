> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Parallel

> Search the web and extract content with AI-powered research automation.

Parallel.ai provides accurate web search, content extraction, and site monitoring. The Parallel MCP server lets you search, extract, and monitor web data using natural language.

## What Can It Do?

* **Search the web** with high-accuracy results
* **Extract clean content** from any URL
* **Monitor websites** for changes and updates
* **Run task automation** with structured outputs

## Where to Use It

### In Agents (Recommended)

Add Parallel as a tool to any agent. The agent can then search and extract web data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Parallel tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search the web for AI news")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                   |
| --------------------------- | --------------------------------------------- |
| **Extract**                 | Extract content from web URLs                 |
| **Search**                  | Search the web for results                    |
| **List Monitors**           | List active web monitors                      |
| **Create Monitor**          | Create a new web monitor                      |
| **Get Monitor**             | Get monitor details                           |
| **Update Monitor**          | Update an existing monitor                    |
| **Delete Monitor**          | Delete a monitor                              |
| **List Monitor Events**     | List events for a monitor                     |
| **Get Monitor Event Group** | Retrieve a specific event group for a monitor |
| **Create Task Run**         | Start a new task run                          |
| **Get Task Run**            | Get task run status                           |
| **Get Task Run Result**     | Get completed task results                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search the web:**

```text theme={"dark"}
Search for "AI startup funding 2025" and return the top 5 results
```

**Extract content:**

```text theme={"dark"}
Extract the main content from this URL: https://example.com/article
```

**Create a monitor:**

```text theme={"dark"}
Create a monitor to track changes on this competitor's pricing page
```

**Check monitor events:**

```text theme={"dark"}
What changes were detected on my monitors this week?
```

## Troubleshooting

| Issue                            | Solution                                                                                                                              |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Be more specific with search queries                                                                                                  |
| Action not completing            | Check that you've authenticated with Parallel                                                                                         |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then extracting). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                   |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find and extract the top article about AI" will search first, then extract content. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Parallel MCP server](https://www.gumloop.com/mcp/parallel) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
