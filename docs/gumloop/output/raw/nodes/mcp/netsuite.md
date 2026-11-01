> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# NetSuite

> Manage ERP data with AI-powered business automation.

NetSuite is Oracle's cloud-based ERP platform for business management. The NetSuite MCP server lets you query records, run SuiteQL, and manage customers, invoices, and orders using natural language.

## What Can It Do?

* **List and query records** like customers, invoices, and sales orders
* **Run SuiteQL queries** for advanced data retrieval
* **Create and update records** across your organization
* **Inspect schemas** to understand available fields

## Where to Use It

### In Agents (Recommended)

Add NetSuite as a tool to any agent. The agent can then manage your ERP data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with NetSuite tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List customers with name containing Acme")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                  | Description                    |
| --------------------- | ------------------------------ |
| **List Record**       | List records with filtering    |
| **Get Record**        | Get a single record by ID      |
| **Create Record**     | Create new records             |
| **Update Record**     | Update existing records        |
| **Delete Record**     | Delete records                 |
| **Run SuiteQL Query** | Execute SuiteQL queries        |
| **Get Record Schema** | Get schema for any record type |

## Setting Up Credentials

NetSuite uses OAuth 2.0 for authentication. See the [NetSuite OAuth Configuration Guide](/nodes/integrations/netsuite-oauth-config) for detailed setup instructions.

## Example Prompts

Use these with your agent or in the Agent Node:

**List customers:**

```text theme={"dark"}
Show me all customers with name containing "Tech"
```

**Get order details:**

```text theme={"dark"}
Get the details for sales order 12345
```

**Run a query:**

```text theme={"dark"}
Run SuiteQL: SELECT id, companyName FROM customer WHERE companyName LIKE '%Corp%'
```

**Create a record:**

```text theme={"dark"}
Create a customer with name "Acme Corp" and email "info@acme.com"
```

**Check schema:**

```text theme={"dark"}
What fields are available on the invoice record?
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific record IDs or exact names                                                                                              |
| Action not completing            | Check that you've authenticated with NetSuite                                                                                       |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |
| Authentication errors            | Verify your OAuth connection and role permissions                                                                                   |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the Acme customer phone number" will find the customer first, then update. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* [NetSuite OAuth Configuration Guide](/nodes/integrations/netsuite-oauth-config) for setup
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [NetSuite MCP server](https://www.gumloop.com/mcp/netsuite) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
