> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Meet

> Manage video meetings and transcripts with AI-powered automation.

Google Meet is Google's video conferencing service integrated with Google Calendar. The Google Meet MCP server lets you create, manage, and access meeting transcripts using natural language.

## What Can It Do?

* **Create meetings** with specific times and attendees
* **Manage attendees** and update meeting details
* **Search meetings** by date or get specific meeting info
* **Access transcripts** from completed meetings

## Where to Use It

### In Agents (Recommended)

Add Google Meet as a tool to any agent. The agent can then manage your meetings conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Meet tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a meeting for tomorrow at 10am")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                      |
| --------------------------- | -------------------------------- |
| **Create Meeting**          | Create a new Google Meet session |
| **Add Attendees**           | Add attendees to a meeting       |
| **Fetch Meetings By Date**  | List meetings for a date         |
| **Get Meeting Details**     | Get full meeting info            |
| **Update Meeting**          | Change meeting details           |
| **Delete Meeting**          | Remove a meeting                 |
| **Read Meeting Transcript** | Get transcript text              |
| **List Conference Records** | List past meeting records        |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a meeting:**

```text theme={"dark"}
Create a Google Meet for tomorrow at 10:30am called "Team Sync"
```

**Add attendees:**

```text theme={"dark"}
Add john@company.com and sarah@company.com to the team meeting
```

**Find meetings:**

```text theme={"dark"}
What meetings do I have scheduled for Friday?
```

**Get transcript:**

```text theme={"dark"}
Get the transcript from yesterday's client call
```

**Update meeting:**

```text theme={"dark"}
Move the Q4 planning meeting to 2pm
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify dates and meeting names clearly                                                                                                             |
| Action not completing            | Check that you've authenticated with Google Meet                                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a meeting first, then adding attendees). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the transcript from the Acme call" will find the meeting first, then retrieve the transcript. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Meet MCP server](https://www.gumloop.com/mcp/gmeet) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
