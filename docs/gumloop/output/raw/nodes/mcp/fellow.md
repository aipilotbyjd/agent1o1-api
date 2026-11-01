> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Fellow

> Access meeting recordings, notes, and transcripts with AI-powered automation.

Fellow is a meeting productivity platform for notes, recordings, and action items. The Fellow MCP server lets you search recordings, retrieve transcripts, and access meeting notes using natural language.

## What Can It Do?

* **List and search recordings** with date and keyword filters
* **Retrieve transcripts** for meeting analysis
* **Access meeting notes** with attendee information
* **Get workspace details** for governance and routing

## Where to Use It

### In Agents (Recommended)

Add Fellow as a tool to any agent. The agent can then interact with your meeting data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Fellow tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List recordings from last week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                | Description                              |
| ------------------- | ---------------------------------------- |
| **Get Me**          | Get user and workspace details           |
| **List Recordings** | List recordings with filtering           |
| **Get Recording**   | Get a specific recording with transcript |
| **List Notes**      | List meeting notes with filtering        |
| **Get Note**        | Get a specific note with content         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find recordings:**

```text theme={"dark"}
List all recordings from last week that mention "product launch"
```

**Get a transcript:**

```text theme={"dark"}
Get the transcript from the Q4 planning meeting
```

**Review notes:**

```text theme={"dark"}
Show me the notes from my meetings with the engineering team
```

**Workspace info:**

```text theme={"dark"}
Get my account and workspace details
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                                |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific date ranges or meeting titles                                                                                                              |
| Action not completing            | Check that you've authenticated with Fellow                                                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., listing recordings first, then getting transcripts). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                     |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Summarize last week's meetings" will list recordings, get transcripts, and synthesize. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Fellow MCP server](https://www.gumloop.com/mcp/fellow) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
