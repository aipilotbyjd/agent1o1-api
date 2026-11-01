> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Exa

> Search the web intelligently with AI-powered research and content extraction.

Exa is an AI-powered search engine that finds high-quality web content with neural search. The Exa MCP server lets you search, extract content, find similar pages, and get citation-backed answers using natural language.

## What Can It Do?

* **Search the web** with neural and keyword search for relevant results
* **Extract content** including full text, summaries, and metadata from URLs
* **Find similar pages** to broaden research coverage
* **Get answers** with citations from reliable web sources

## Where to Use It

### In Agents (Recommended)

Add Exa as a tool to any agent. The agent can then search and research conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Exa tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for AI startup funding news")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                     | Description                                   |
| ------------------------ | --------------------------------------------- |
| **Search**               | Search the web with neural and keyword search |
| **Get Contents**         | Extract full page contents from URLs          |
| **Find Similar**         | Find similar pages to a source URL            |
| **Answer**               | Get LLM answers with citations                |
| **Create Research Task** | Start an async research task                  |
| **Get Research Task**    | Get status and results of research            |

## Credit Costs

| Tool                     | Credits Per Use |
| ------------------------ | --------------- |
| Get Contents             | 3 per item      |
| Find Similar             | 5 per item      |
| Answer                   | 10 credits      |
| Create/Get Research Task | 5 credits each  |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search the web:**

```text theme={"dark"}
Find the top 10 articles about AI regulation in 2024
```

**Extract content:**

```text theme={"dark"}
Get the full content from this article URL
```

**Find related sources:**

```text theme={"dark"}
Find similar pages to this TechCrunch article
```

**Get an answer:**

```text theme={"dark"}
What are the latest developments in quantum computing? Include citations.
```

**Start research:**

```text theme={"dark"}
Research the competitive landscape for AI writing tools
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                    |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use more specific search terms or date filters                                                                                              |
| Action not completing            | Check that you've authenticated and have sufficient Exa credits                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then getting contents). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                         |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Research AI trends and summarize" will search, get contents, and synthesize. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Exa MCP server](https://www.gumloop.com/mcp/exa) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
