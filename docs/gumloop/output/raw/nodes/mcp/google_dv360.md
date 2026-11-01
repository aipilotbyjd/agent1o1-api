> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google DV360

> Manage campaigns, line items, and targeting with AI-powered programmatic advertising automation.

Google Display & Video 360 is a programmatic advertising platform. The Google DV360 MCP server lets you manage campaigns, line items, and targeting options using natural language.

## What Can It Do?

* **Manage campaigns and line items** across advertisers
* **Configure targeting options** for precise audience reach
* **Track performance data** with comprehensive metrics
* **Control entity status** to pause or activate campaigns

## Where to Use It

### In Agents (Recommended)

Add Google DV360 as a tool to any agent. The agent can then interact with Google DV360 conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Google account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google DV360 tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List campaigns for my advertiser")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                         | Description                                                                                |
| ---------------------------- | ------------------------------------------------------------------------------------------ |
| **List Partners**            | List partners accessible to the current user to get valid partner IDs for other operations |
| **List Advertisers**         | List advertisers in your Google DV360 account with filtering options                       |
| **Get Advertiser**           | Get detailed information about a specific advertiser by ID                                 |
| **List Campaigns**           | List campaigns for a specific advertiser with filtering options                            |
| **Get Campaign**             | Get detailed information about a specific campaign by ID                                   |
| **List Line Items**          | List line items for a specific advertiser with filtering options                           |
| **Get Line Item**            | Get detailed information about a specific line item by ID                                  |
| **Get Targeting Option**     | Get a specific targeting option assigned to a line item                                    |
| **List Targeting Options**   | List targeting options assigned to a line item for a specific targeting type               |
| **Search Targeting Options** | Search targeting options across multiple line items and targeting types                    |
| **Update Targeting Options** | Update targeting options for multiple line items (delete and create in one operation)      |
| **Update Status**            | Update the status of a campaign, insertion order, or line item (pause/activate)            |
| **Get Performance**          | Get comprehensive performance data                                                         |

## Example Prompts

Use these with your agent or in the Agent Node:

**List campaigns:**

```text theme={"dark"}
Show me all active campaigns for my advertiser
```

**Check performance:**

```text theme={"dark"}
What's the performance data for my Q4 campaign?
```

**Manage targeting:**

```text theme={"dark"}
List the targeting options on my display line item
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Google DV360 credentials and that you have the required permissions                                     |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |
| Unexpected results    | The agent may chain multiple tools together. Review the agent's reasoning to understand its approach.               |

<Tip>
  Agents are smart enough to chain multiple API calls together. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google DV360 MCP server](https://www.gumloop.com/mcp/gdv360) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
