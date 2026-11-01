> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Circleback

> Search and access your Circleback meeting notes and action items.

Circleback is an AI meeting assistant that automatically records, transcribes, and summarizes meetings. The Circleback MCP server lets you search and access your meeting notes and action items using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Circleback. Authentication uses OAuth — just connect your Circleback account and start using it immediately.
</Info>

## What Can It Do?

* **Search meeting notes** by topic, date, or participant
* **Access action items** from your meetings
* **View meeting summaries** and key decisions

## Where to Use It

### In Agents (Recommended)

Add Circleback as a tool to any agent. The agent can then access your meeting history conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Circleback account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Circleback tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Circleback uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Circleback to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Search notes:**

```text theme={"dark"}
Find meeting notes from last week about the product launch
```

**Get action items:**

```text theme={"dark"}
What are my outstanding action items from recent meetings?
```

**Review decisions:**

```text theme={"dark"}
What decisions were made in the board meeting on Monday?
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Circleback account                                                                        |
| No meetings found  | Check that Circleback has been joined to your meetings                                                              |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
