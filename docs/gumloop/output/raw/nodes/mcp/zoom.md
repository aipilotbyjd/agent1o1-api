> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Zoom

> Manage meetings, webinars, registrants, polls, surveys, and recordings with AI-powered video conferencing automation.

Zoom is a leading video conferencing platform for meetings and webinars. The Zoom MCP server lets you schedule and manage meetings and webinars, handle registrants and panelists, run polls and surveys, and retrieve recordings, transcripts, and analytics using natural language.

## What Can It Do?

* **Schedule and manage meetings and webinars** with specific details
* **Manage registrants, panelists, and invitations** for meetings and webinars
* **Create and review polls and post-event surveys**
* **Retrieve recordings, transcripts, AI summaries, and analytics**
* **Read Zoom Canvas documents**

## Where to Use It

### In Agents (Recommended)

Add Zoom as a tool to any agent. The agent can then manage your meetings conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Zoom tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Schedule a meeting for tomorrow")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Setting Up Credentials

Connect your Zoom account via [Connectors page](https://www.gumloop.com/personal/connectors?provider=zoom). Follow the OAuth flow to grant access.

## Available Tools

**Meetings**

| Tool                                 | Description                                    |
| ------------------------------------ | ---------------------------------------------- |
| **Create/Get/Update/Delete Meeting** | Manage meetings                                |
| **List Meetings**                    | List scheduled meetings                        |
| **List Upcoming Meetings**           | List meetings in the next 24 hours             |
| **End/Recover Meeting**              | Update a meeting's status                      |
| **Get Past Meeting**                 | Get details of a past meeting                  |
| **List Past Meeting Instances**      | List instances of a past meeting               |
| **List Past Meeting Participants**   | List participants of a past meeting            |
| **List Past Meeting Q\&A**           | List questions and answers from a past meeting |
| **List Meeting Templates**           | List available meeting templates               |

**Webinars**

| Tool                                 | Description                                    |
| ------------------------------------ | ---------------------------------------------- |
| **Create/Get/Update/Delete Webinar** | Manage webinars                                |
| **List Webinars**                    | List scheduled webinars                        |
| **End Webinar**                      | Update a webinar's status                      |
| **Create Webinar Invite Links**      | Create a batch of webinar invite links         |
| **List/Add/Remove Panelists**        | Manage webinar panelists                       |
| **List Past Webinar Instances**      | List instances of a past webinar               |
| **List Past Webinar Participants**   | List participants of a past webinar            |
| **Get Webinar Absentees**            | List absentees of a past webinar               |
| **List Past Webinar Q\&A**           | List questions and answers from a past webinar |
| **Get Webinar Tracking Sources**     | List registration tracking sources             |
| **List Webinar Templates**           | List available webinar templates               |

**Registrants & Invitations**

| Tool                                           | Description                                  |
| ---------------------------------------------- | -------------------------------------------- |
| **Get Meeting Invitation**                     | Get a meeting's invitation note              |
| **Create Meeting Invite Links**                | Create a batch of meeting invite links       |
| **List/Add/Get/Delete Meeting Registrants**    | Manage meeting registrants                   |
| **Update Meeting Registrant Status**           | Approve, deny, or cancel meeting registrants |
| **List/Update Meeting Registration Questions** | Manage meeting registration questions        |
| **List/Add/Get/Delete Webinar Registrants**    | Manage webinar registrants                   |
| **Update Webinar Registrant Status**           | Approve, deny, or cancel webinar registrants |
| **List/Update Webinar Registration Questions** | Manage webinar registration questions        |

**Polls**

| Tool                                           | Description                           |
| ---------------------------------------------- | ------------------------------------- |
| **List/Create/Get/Update/Delete Meeting Poll** | Manage meeting polls                  |
| **List Past Meeting Poll Results**             | View poll results from a past meeting |
| **List/Create/Get/Update/Delete Webinar Poll** | Manage webinar polls                  |
| **List Past Webinar Poll Results**             | View poll results from a past webinar |

**Surveys**

| Tool                                 | Description                            |
| ------------------------------------ | -------------------------------------- |
| **Get/Update/Delete Meeting Survey** | Manage a meeting's post-meeting survey |
| **Get/Update/Delete Webinar Survey** | Manage a webinar's post-webinar survey |

**Recordings**

| Tool                                        | Description                                  |
| ------------------------------------------- | -------------------------------------------- |
| **Get Meeting Recordings**                  | Get recording information for a meeting      |
| **List Meeting Recordings**                 | List all cloud recordings                    |
| **Download Meeting Recording File**         | Download a recording file to Gumloop storage |
| **Delete Meeting Recordings**               | Delete all cloud recordings of a meeting     |
| **Delete Meeting Recording File**           | Delete a single cloud recording file         |
| **Get Meeting Transcript**                  | Get transcript info (cloud recordings only)  |
| **Get Meeting Summary**                     | Get a meeting's AI Companion summary         |
| **Get Recording Analytics Summary/Details** | View recording analytics                     |

**Canvas**

| Tool                          | Description                         |
| ----------------------------- | ----------------------------------- |
| **List Canvas File Children** | List child files in a Zoom Canvas   |
| **Get Canvas File Metadata**  | Get metadata for a Canvas file      |
| **Get Canvas File Content**   | Get Canvas file content as Markdown |

## Example Prompts

Use these with your agent or in the Agent Node:

**Schedule a meeting:**

```text theme={"dark"}
Schedule a Zoom meeting for tomorrow at 2pm called "Team Sync"
```

**List meetings:**

```text theme={"dark"}
Show me my upcoming meetings this week
```

**Get meeting details:**

```text theme={"dark"}
Get the details for this Zoom meeting link
```

**Get recordings:**

```text theme={"dark"}
Get the recording for yesterday's team meeting
```

**Get transcript:**

```text theme={"dark"}
Get the transcript from this meeting
```

**Schedule a webinar:**

```text theme={"dark"}
Create a webinar next Thursday at 10am titled "Product Launch" and add Jane as a panelist
```

**Run a poll:**

```text theme={"dark"}
Create a poll for my upcoming meeting and then show me the results afterward
```

**Manage registrants:**

```text theme={"dark"}
List everyone registered for the webinar and approve the pending ones
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                          |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific meeting topics or links                                                                                                              |
| Action not completing            | Check that you've authenticated with Zoom                                                                                                         |
| Unexpected results               | The agent may chain multiple tools (e.g., listing meetings first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                               |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get the transcript from the sales call" will find the meeting first, then retrieve the transcript. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Zoom MCP server](https://www.gumloop.com/mcp/zoom) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
