> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Salesloft

> Manage sales engagement with AI-powered outreach automation.

Salesloft is a sales engagement platform for SDR and revenue teams. The Salesloft MCP server lets you manage people, accounts, cadences, calls, and conversations using natural language.

## What Can It Do?

* **Find and manage people** and accounts
* **Add prospects to cadences** and track performance
* **Retrieve calls and emails** for reporting
* **Access AI conversation insights** and recordings

## Where to Use It

### In Agents (Recommended)

Add Salesloft as a tool to any agent. The agent can then manage your sales engagement conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Salesloft tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Add person to outbound cadence")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                           | Description              |
| ------------------------------ | ------------------------ |
| **List People**                | List people with filters |
| **Create Person**              | Create a new person      |
| **Get Person**                 | Get person details       |
| **Update Person**              | Update person properties |
| **List Accounts**              | List accounts            |
| **Create Account**             | Create an account        |
| **Get Account**                | Get account details      |
| **List Cadences**              | List cadences            |
| **Get Cadence Stats**          | Get cadence performance  |
| **Create Cadence Membership**  | Add person to cadence    |
| **List Calls**                 | List calls with filters  |
| **Get Conversation**           | Get conversation details |
| **Get Conversation Extensive** | Get AI insights          |
| **List Emails**                | List emails              |
| **Create Note**                | Create a note            |
| **Create Task**                | Create a task            |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find people:**

```text theme={"dark"}
Search for people with email containing @acme.com
```

**Add to cadence:**

```text theme={"dark"}
Add this person to the Outbound Q1 cadence
```

**Check cadence stats:**

```text theme={"dark"}
What are the reply rates for the Enterprise cadence?
```

**Get conversation insights:**

```text theme={"dark"}
Get the AI summary and action items from this conversation
```

**List recent calls:**

```text theme={"dark"}
Show me all calls from last week with their dispositions
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific emails or person IDs                                                                                                                   |
| Action not completing            | Check that you've authenticated with Salesloft                                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a person first, then adding to cadence). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add [john@company.com](mailto:john@company.com) to the outbound cadence" will find the person first, then add to cadence. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Salesloft MCP server](https://www.gumloop.com/mcp/salesloft) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
