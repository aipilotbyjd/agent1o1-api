> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gong

> Access sales call recordings, transcripts, and analytics with AI-powered automation.

Gong is a revenue intelligence platform that records and analyzes sales conversations. The Gong MCP server lets you search calls, retrieve transcripts, and access analytics using natural language.

## What Can It Do?

* **Fetch calls and transcripts** for coaching and QA
* **Access scorecards and trackers** for performance insights
* **Manage Engage flows** to assign and track prospects in sequences
* **Manage users and workspaces** without manual API calls
* **Stream Gong data** to other tools for reporting

## Where to Use It

### In Agents (Recommended)

Add Gong as a tool to any agent. The agent can then search and analyze your sales data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Gong tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List calls from last week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                              | Description                                                                                                                   |
| --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **List Calls**                    | List calls in a date range, oldest first by default (set sort direction to `desc` with a max limit for the most recent calls) |
| **Get Call**                      | Get details for a specific call                                                                                               |
| **Add Call**                      | Add a new call to Gong                                                                                                        |
| **Get Call Transcript**           | Retrieve call transcripts                                                                                                     |
| **Get Detailed Calls**            | Get calls with interaction metrics                                                                                            |
| **List/Get Users**                | Access user data                                                                                                              |
| **Get Answered Scorecards**       | Retrieve reviewed scorecards                                                                                                  |
| **Get Interaction Stats**         | Get user interaction metrics                                                                                                  |
| **Get Scorecards**                | List all scorecards                                                                                                           |
| **Get Trackers**                  | Get keyword tracker details                                                                                                   |
| **List Workspaces**               | List all workspaces                                                                                                           |
| **List Library Folders**          | List library folders                                                                                                          |
| **Get Folder Content**            | Get folder contents                                                                                                           |
| **List Flows**                    | List Gong Engage flows for a user                                                                                             |
| **List Flow Folders**             | List Gong Engage flow folders for a user                                                                                      |
| **List Prospect Flows**           | List Gong Engage flows assigned to prospects                                                                                  |
| **Assign Prospects to Flow**      | Assign prospects to a Gong Engage flow                                                                                        |
| **Unassign Prospects from Flows** | Unassign prospects from Gong Engage flows                                                                                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**List recent calls:**

```text theme={"dark"}
List all calls from last week with Acme Corp
```

**Get a transcript:**

```text theme={"dark"}
Get the transcript from the latest discovery call
```

**Check interaction stats:**

```text theme={"dark"}
Get interaction stats for Sarah for Q2 including talk ratio and empathy score
```

**Find scorecards:**

```text theme={"dark"}
Get answered scorecards from last month for the sales team
```

**List users:**

```text theme={"dark"}
List all users in the sales workspace with their roles
```

**List Engage flows:**

```text theme={"dark"}
List all Engage flows for sarah@company.com
```

**Assign prospects to a flow:**

```text theme={"dark"}
Assign prospect john@acme.com to the "SDR Outbound" Engage flow
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific date ranges or call IDs                                                                                                               |
| Action not completing            | Check that you've authenticated and have access to the workspace                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., listing calls first, then getting transcripts). Review the agent's reasoning to understand its approach. |
| Only older calls returned        | Gong returns calls oldest first. Ask for the most recent calls (sort direction `desc`) along with a max limit.                                     |
| Empty interaction stats          | Interaction stats only cover calls that had Whisper enabled, so users without Whisper calls return no data.                                        |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the transcript from the Acme call" will find the call first, then retrieve the transcript. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Gong MCP server](https://www.gumloop.com/mcp/gong) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
