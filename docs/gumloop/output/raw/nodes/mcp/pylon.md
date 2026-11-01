> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Pylon

> Manage your Pylon support issues, accounts, and contacts.

Pylon is a B2B customer support platform built for modern SaaS teams. The Pylon MCP server lets you manage support issues, accounts, and contacts using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Pylon. Authentication uses OAuth — just connect your Pylon account and start using it immediately.
</Info>

## What Can It Do?

* **Manage support issues** and track their status
* **View and update accounts** and customer information
* **Search contacts** and communication history

## Where to Use It

### In Agents (Recommended)

Add Pylon as a tool to any agent. The agent can then manage your support operations conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Pylon account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Pylon tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Pylon uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Pylon to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**View issues:**

```text theme={"dark"}
Show me all open support issues for Acme Corp
```

**Update a ticket:**

```text theme={"dark"}
Mark issue #456 as resolved with a note about the fix
```

**Search contacts:**

```text theme={"dark"}
Find all contacts at company "TechStart Inc"
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have access to your Pylon workspace                                                                      |
| Issues not loading | Check that your account has the required permissions                                                                |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
