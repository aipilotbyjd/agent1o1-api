> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Similarweb

> Analyze website traffic, audiences, and competitor insights with Similarweb.

Similarweb is a digital intelligence platform providing website traffic analysis, audience insights, and competitive benchmarking. The Similarweb MCP server lets you analyze web traffic and competitor data using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Similarweb. Authentication uses OAuth — just connect your Similarweb account and start using it immediately.
</Info>

## What Can It Do?

* **Analyze website traffic** volumes and trends
* **Research audiences** and demographics
* **Compare competitors** with benchmarking data
* **Track industry trends** and market insights

## Where to Use It

### In Agents (Recommended)

Add Similarweb as a tool to any agent. The agent can then research and analyze web data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Similarweb account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Similarweb tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Similarweb uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Similarweb to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Analyze traffic:**

```text theme={"dark"}
What's the monthly traffic for competitor.com over the last 6 months?
```

**Compare sites:**

```text theme={"dark"}
Compare the traffic sources for example.com and competitor.com
```

**Research audience:**

```text theme={"dark"}
What demographics visit techblog.com?
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Similarweb subscription                                                                   |
| No data available  | Some domains may not have sufficient traffic data                                                                   |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
