> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Jam

> Access your Jam bug reports and debug logs.

Jam is a bug reporting tool that captures browser context, console logs, and network requests automatically. The Jam MCP server lets you access your bug reports and debug information using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Jam. Authentication uses OAuth — just connect your Jam account and start using it immediately.
</Info>

## What Can It Do?

* **Access bug reports** with full browser context
* **View debug logs** including console output and network requests
* **Search issues** by description, reporter, or status

## Where to Use It

### In Agents (Recommended)

Add Jam as a tool to any agent. The agent can then access and analyze your bug reports conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Jam account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Jam tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Jam uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Jam to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**View bugs:**

```text theme={"dark"}
Show me the latest bug reports from this week
```

**Debug an issue:**

```text theme={"dark"}
Get the console logs and network requests for bug report #123
```

**Search reports:**

```text theme={"dark"}
Find all bug reports related to the login page
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Jam account                                                                               |
| No reports found   | Check that bug reports have been submitted through the Jam extension                                                |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
