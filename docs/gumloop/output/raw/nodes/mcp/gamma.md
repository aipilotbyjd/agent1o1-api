> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gamma

> Create presentations, documents, and carousels with AI-powered content generation.

Gamma is a content creation platform that turns ideas into polished presentations, documents, and social carousels. The Gamma MCP server lets you create and retrieve content using natural language.

## What Can It Do?

* **Create presentations** with customizable tone, imagery, and formatting
* **Generate documents** and one-pagers from outlines
* **Build social carousels** with consistent branding
* **Retrieve finished content** via shareable URLs

## Where to Use It

### In Agents (Recommended)

Add Gamma as a tool to any agent. The agent can then create and retrieve content conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Gamma tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a presentation about Q4 results")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool               | Description                                        |
| ------------------ | -------------------------------------------------- |
| **Create Gamma**   | Create presentations, documents, or social content |
| **Get Generation** | Check status and retrieve the finished URL         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a presentation:**

```text theme={"dark"}
Create a presentation about our Q4 results with a professional tone
```

**Create a document:**

```text theme={"dark"}
Create a one-page summary of our product features
```

**Create a carousel:**

```text theme={"dark"}
Create a social media carousel about productivity tips with 5 slides
```

**Check status:**

```text theme={"dark"}
Get the URL for my presentation once it's ready
```

## Troubleshooting

| Issue                            | Solution                                                                                                                      |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Be specific about the type of content (presentation, document, carousel)                                                      |
| Action not completing            | Check that you've authenticated with Gamma                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., creating then retrieving). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)           |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a presentation and share the link" will create the content first, then retrieve the URL. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Gamma MCP server](https://www.gumloop.com/mcp/gamma) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
