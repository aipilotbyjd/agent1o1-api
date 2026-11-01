> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# AppSheet

> Manage your AppSheet tables with AI-powered data operations.

AppSheet is Google's no-code application platform that lets you build apps from spreadsheets and databases. The AppSheet MCP server lets you read, add, update, and delete rows using natural language.

## What Can It Do?

* **Retrieve rows** from any table with filters and conditions
* **Add new rows** to keep your app data current
* **Update existing rows** to reflect changes from other systems
* **Delete rows** to clean up outdated records

## Where to Use It

### In Agents (Recommended)

Add AppSheet as a tool to any agent. The agent can then interact with your app's tables conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with AppSheet tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get all rows from Inventory where In Stock is true")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Setting Up AppSheet Credentials

Before using AppSheet, you need to enable API access in your app:

<Steps>
  <Step title="Open Your AppSheet App">
    In the AppSheet editor, go to **Settings** → **Integrations**.
  </Step>

  <Step title="Enable Cloud Services Access">
    Under **IN: from cloud services to your app**, toggle **Enable** to allow external services to communicate with your app.
  </Step>

  <Step title="Copy Your App ID">
    Your **App Id** is displayed in the Integrations panel. Copy this value.
  </Step>

  <Step title="Create an Access Key">
    Click **Create Application Access Key** to generate a new key. Copy the access key value.
  </Step>

  <Step title="Add Credentials in Gumloop">
    Go to [Connectors page](https://www.gumloop.com/personal/connectors), add AppSheet, and enter your App ID and Access Key.
  </Step>
</Steps>

<Warning>
  Application Access Keys provide full access to your AppSheet app's data. Keep them secure and never share publicly.
</Warning>

## Available Tools

| Tool            | Description                                      |
| --------------- | ------------------------------------------------ |
| **Get Rows**    | Retrieve rows from a table with optional filters |
| **Add Rows**    | Add new rows to a table                          |
| **Update Rows** | Update existing rows in a table                  |
| **Delete Rows** | Delete rows from a table                         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Retrieve records:**

```text theme={"dark"}
Get all rows from the Inventory table where In Stock is true
```

**Add new data:**

```text theme={"dark"}
Add a new contact: Name "Sarah Smith", Email "sarah@example.com", Company "Acme Corp"
```

**Update records:**

```text theme={"dark"}
Update the project PRJ-123 to set Status to "In Progress"
```

**Clean up data:**

```text theme={"dark"}
Delete all rows from the Logs table older than June 2024
```

## Troubleshooting

| Issue                            | Solution                                                                                                                               |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Include the app ID and exact table names in your request                                                                               |
| Action not completing            | Check that cloud services access is enabled and your access key is active                                                              |
| Unexpected results               | The agent may chain multiple tools (e.g., getting rows first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                    |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update all overdue tasks to high priority" will get the tasks first, then update them. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [AppSheet MCP server](https://www.gumloop.com/mcp/gappsheet) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
