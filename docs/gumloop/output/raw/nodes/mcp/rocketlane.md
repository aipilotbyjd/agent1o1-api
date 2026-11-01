> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Rocketlane

> Manage your Rocketlane projects, tasks, and customer onboarding.

Rocketlane is a customer onboarding and professional services automation platform that helps teams deliver projects on time and create a consistent onboarding experience. The Rocketlane MCP server lets you manage projects, tasks, and customer onboarding workflows using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Rocketlane. Authentication uses OAuth — just connect your Rocketlane account and start using it immediately.
</Info>

## What Can It Do?

* **Manage projects** — create, update, and track customer onboarding projects
* **Handle tasks** — assign, update, and complete tasks across projects
* **Track onboarding** — monitor customer onboarding progress and milestones

## Where to Use It

### In Agents (Recommended)

Add Rocketlane as a tool to any agent. The agent can then manage your customer onboarding projects conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Rocketlane account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Rocketlane tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Rocketlane uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Rocketlane to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Check project status:**

```text theme={"dark"}
Show me the status of all active onboarding projects
```

**Manage tasks:**

```text theme={"dark"}
List all overdue tasks across my Rocketlane projects
```

**Track onboarding:**

```text theme={"dark"}
What's the onboarding progress for Acme Corp?
```

**Create a task:**

```text theme={"dark"}
Create a task to schedule a kickoff call with the new client
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Rocketlane account with the appropriate permissions                                       |
| Data not loading   | Check that your Rocketlane workspace has projects and tasks configured                                              |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
