> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sprig

> Pull survey data and AI insights with AI-powered research automation.

Sprig is a product research platform for capturing user feedback and insights. The Sprig MCP server lets you retrieve survey responses, study configurations, and AI-generated themes using natural language.

## What Can It Do?

* **Retrieve survey responses** with date filtering
* **Pull study configurations** by status
* **Access AI-generated themes** and insights
* **Look up user profiles** and attributes

## Where to Use It

### In Agents (Recommended)

Add Sprig as a tool to any agent. The agent can then access your research data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Sprig tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get responses from last month's survey")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                   | Description                         |
| ---------------------- | ----------------------------------- |
| **Retrieve Responses** | Get survey responses with filtering |
| **Retrieve Surveys**   | List study configurations           |
| **Retrieve Themes**    | Get AI-generated themes             |
| **Get User**           | Look up user by ID                  |
| **Upsert User**        | Create or update a user             |

## Example Prompts

Use these with your agent or in the Agent Node:

**Get responses:**

```text theme={"dark"}
Get all responses from the onboarding survey from last month
```

**List surveys:**

```text theme={"dark"}
Show me all active surveys
```

**Get themes:**

```text theme={"dark"}
What are the AI-generated themes from the NPS study?
```

**Look up user:**

```text theme={"dark"}
Get the profile and attributes for this user ID
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific survey IDs or date ranges                                                                                                             |
| Action not completing            | Check that you've authenticated with Sprig                                                                                                         |
| Unexpected results               | The agent may chain multiple tools (e.g., listing surveys first, then getting responses). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get responses from the onboarding survey" will find the survey first, then retrieve responses. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Sprig MCP server](https://www.gumloop.com/mcp/sprig) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
