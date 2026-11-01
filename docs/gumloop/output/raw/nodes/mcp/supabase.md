> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Supabase

> Manage Supabase projects, databases, branches, and logs.

Supabase is the open-source Firebase alternative providing databases, authentication, storage, and edge functions. The Supabase MCP server lets you manage your projects, databases, and infrastructure using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Supabase. Authentication uses OAuth — just connect your Supabase account and start using it immediately.
</Info>

## What Can It Do?

* **Manage projects** and their configurations
* **Work with databases** including schema and queries
* **Handle branches** for database development workflows
* **View logs** and monitor project health

## Where to Use It

### In Agents (Recommended)

Add Supabase as a tool to any agent. The agent can then manage your backend infrastructure conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Supabase account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Supabase tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Supabase uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Supabase to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Manage projects:**

```text theme={"dark"}
List all my Supabase projects and their status
```

**Work with databases:**

```text theme={"dark"}
Show me the schema for the users table in my production project
```

**View logs:**

```text theme={"dark"}
Get the recent error logs from my project
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Supabase account with project access                                                      |
| Project not found  | Check that you have the correct organization selected                                                               |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
