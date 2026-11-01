> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Fathom

> Access meeting recordings, transcripts, and AI-generated summaries with automated notetaking.

Fathom is an AI-powered meeting assistant that records, transcribes, and summarizes meetings. The Fathom MCP server lets you list meetings, retrieve transcripts, and access AI-generated summaries using natural language.

## What Can It Do?

* **List and filter meetings** by date, recorder, team, or invitee domains
* **Retrieve AI-generated summaries** for meeting recordings
* **Access full transcripts** with speaker attribution and timestamps
* **Manage teams and members** across your organization

## Where to Use It

### In Agents (Recommended)

Add Fathom as a tool to any agent. The agent can then interact with your meeting data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Fathom tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List meetings from last week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                         | Description                                                               |
| ---------------------------- | ------------------------------------------------------------------------- |
| **List Meetings**            | List meetings with filtering by date, recorder, team, and invitee domains |
| **Get Recording Summary**    | Get the AI-generated summary for a meeting recording                      |
| **Get Recording Transcript** | Get the full transcript with speaker attribution and timestamps           |
| **List Teams**               | List all teams in the organization                                        |
| **List Team Members**        | List team members, optionally filtered by team name                       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find recent meetings:**

```text theme={"dark"}
List all meetings from last week recorded by the sales team
```

**Get a summary:**

```text theme={"dark"}
Get the AI summary from yesterday's product planning meeting
```

**Get a transcript:**

```text theme={"dark"}
Get the full transcript from the Q4 review meeting
```

**List teams:**

```text theme={"dark"}
Show me all teams in the organization
```

**Find team members:**

```text theme={"dark"}
List all members of the engineering team
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific date ranges or meeting titles                                                                                                          |
| Action not completing            | Check that you've authenticated with Fathom                                                                                                         |
| Unexpected results               | The agent may chain multiple tools (e.g., listing meetings first, then getting summaries). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Summarize last week's meetings" will list meetings, get summaries, and synthesize. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Fathom MCP server](https://www.gumloop.com/mcp/fathom) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
