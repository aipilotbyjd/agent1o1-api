> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Chorus

> Access conversation intelligence and meeting insights with AI-powered automation.

Chorus is a conversation intelligence platform that records and analyzes sales calls. The Chorus MCP server lets you search meetings, review scorecards, and access conversation details using natural language.

## What Can It Do?

* **Search recorded meetings** with filters for date, participants, and topics
* **Pull conversation details** for coaching and quality assurance
* **Review scorecards** by recipient, reviewer, or initiative
* **Discover playlists** for onboarding and training

## Where to Use It

### In Agents (Recommended)

Add Chorus as a tool to any agent. The agent can then search and analyze your conversation data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Chorus tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get meetings from last week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                  | Description                      |
| --------------------- | -------------------------------- |
| **Get Me**            | Get authenticated user details   |
| **Get Engagements**   | Search meetings with filtering   |
| **Delete Engagement** | Delete a specific engagement     |
| **Get Conversation**  | Fetch detailed conversation data |
| **Get Playlists**     | Fetch playlists with filtering   |
| **Get Scorecards**    | Fetch scorecards with filtering  |
| **Get Users**         | Fetch all users from Chorus      |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find recent meetings:**

```text theme={"dark"}
Show me all meetings from last week with external participants
```

**Get conversation details:**

```text theme={"dark"}
Get the details for the meeting with Acme Corp including participants and duration
```

**Review scorecards:**

```text theme={"dark"}
Find scorecards for sarah@company.com submitted this month
```

**Discover playlists:**

```text theme={"dark"}
List training playlists owned by the sales team
```

**User directory:**

```text theme={"dark"}
Show me all users with their roles and team assignments
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                               |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific date ranges or participant names                                                                                                          |
| Action not completing            | Check that you've authenticated and have access to the recordings                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., searching engagements first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                    |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the details for the Acme meeting" will search for the engagement first, then fetch conversation details. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Chorus MCP server](https://www.gumloop.com/mcp/chorus) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
