> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Expensify

> Automate expense management with AI-powered report exports and bulk expense creation.

Expensify is an expense management platform for tracking receipts, reports, and corporate card transactions. The Expensify MCP server lets you export reports, reconcile transactions, and create expenses using natural language.

## What Can It Do?

* **Export expense reports** in CSV, Excel, PDF, or XML formats
* **Reconcile card transactions** by exporting transaction data
* **Create expenses in bulk** with categories, tags, and custom fields
* **Get download links** for exported data

## Where to Use It

### In Agents (Recommended)

Add Expensify as a tool to any agent. The agent can then interact with your expense data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Expensify tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Export reports from last month")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

To use Expensify, generate API credentials:

1. Create an account at [expensify.com](https://www.expensify.com/)
2. Go to [Integrations](https://www.expensify.com/tools/integrations/)
3. Copy your partnerUserID and partnerUserSecret
4. Add them to your [Connectors page](https://www.gumloop.com/personal/connectors)

## Available Tools

| Tool                      | Description                                      |
| ------------------------- | ------------------------------------------------ |
| **Get Reports**           | Export reports in CSV, Excel, PDF, or XML        |
| **Get Card Transactions** | Export card transactions for reconciliation      |
| **Create Expenses**       | Create expenses in bulk with categories and tags |

## Example Prompts

Use these with your agent or in the Agent Node:

**Export reports:**

```text theme={"dark"}
Export expense reports from January 1 to January 31 as a CSV
```

**Get card transactions:**

```text theme={"dark"}
Export corporate card transactions for last month
```

**Create expenses:**

```text theme={"dark"}
Create an expense for $45.50 at Uber on January 15 in the Travel category
```

## Troubleshooting

| Issue                            | Solution                                                                                                                             |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Specify clear date ranges and report states                                                                                          |
| Action not completing            | Check that you've authenticated with your Expensify API credentials                                                                  |
| Unexpected results               | The agent may chain multiple tools (e.g., exporting first, then filtering). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                  |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Get my Q4 expense summary" will export reports and calculate totals. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Expensify MCP server](https://www.gumloop.com/mcp/expensify) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
