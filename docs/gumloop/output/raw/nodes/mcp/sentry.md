> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sentry

> Investigate Sentry issues, errors, releases, and performance data.

Sentry is the application monitoring platform for error tracking, performance monitoring, and release management. The Sentry MCP server lets you investigate issues, errors, and performance data using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Sentry. Authentication uses OAuth — just connect your Sentry account and start using it immediately.
</Info>

## What Can It Do?

* **Investigate issues** and error reports
* **Track errors** with stack traces and context
* **Monitor releases** and their health
* **Analyze performance** data and bottlenecks

## Where to Use It

### In Agents (Recommended)

Add Sentry as a tool to any agent. The agent can then investigate errors and performance issues conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Sentry account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Sentry tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Sentry uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Sentry to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Investigate issues:**

```text theme={"dark"}
Show me the top unresolved issues in the production project
```

**Check errors:**

```text theme={"dark"}
What's the stack trace for the most frequent error this week?
```

**Monitor releases:**

```text theme={"dark"}
How is the latest release performing compared to the previous one?
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have member access to your Sentry organization                                                           |
| No issues found    | Check that the correct project is selected                                                                          |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
