> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Carta

> Manage your Carta cap table, equity, and stakeholders.

Carta is the platform for managing equity, cap tables, and valuations. The Carta MCP server lets you manage your cap table, equity grants, and stakeholder information using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Carta. Authentication uses OAuth — just connect your Carta account and start using it immediately.
</Info>

## What Can It Do?

* **View cap table** information and ownership breakdowns
* **Manage equity** grants and vesting schedules
* **Access stakeholder** information and contacts

## Where to Use It

### In Agents (Recommended)

Add Carta as a tool to any agent. The agent can then access your equity data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Carta account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Carta tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Carta uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Carta to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**View cap table:**

```text theme={"dark"}
Show me the current ownership breakdown for my company
```

**Check equity:**

```text theme={"dark"}
What's the vesting schedule for the latest employee grants?
```

**Find stakeholders:**

```text theme={"dark"}
List all stakeholders with more than 1% ownership
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have admin or viewer access to your Carta company                                                        |
| Data not loading   | Check that your permissions include the requested data                                                              |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
