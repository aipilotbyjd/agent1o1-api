> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Stripe

> Manage customers, subscriptions, and payments with AI-powered billing automation.

Stripe is an online payment processing platform. The Stripe MCP server lets you manage customers, subscriptions, invoices, and products using natural language.

## What Can It Do?

* **Manage customers** with creation, search, and updates
* **Handle subscriptions** with create, update, and cancel
* **Track payments** and charges
* **Manage invoices** and products
* **Create and manage coupons** for discounts

## Where to Use It

### In Agents (Recommended)

Add Stripe as a tool to any agent. The agent can then interact with Stripe conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Stripe tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all active subscriptions")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                      | Description                                                       |
| ------------------------- | ----------------------------------------------------------------- |
| **List Customers**        | List all Stripe customers                                         |
| **Retrieve Balance**      | Retrieve the current Stripe account balance                       |
| **List Subscriptions**    | List all subscriptions in the Stripe account                      |
| **Update Subscription**   | Update metadata or attributes of a Stripe subscription            |
| **List Payment Intents**  | List all payment intents                                          |
| **List Charges**          | List all charges processed by the Stripe account                  |
| **Create Customer**       | Create a new customer in Stripe                                   |
| **List Invoices**         | List all invoices created in Stripe                               |
| **Retrieve Customer**     | Retrieve details of a specific customer by ID                     |
| **List Products**         | List all available products in Stripe                             |
| **Cancel Subscription**   | Cancel a subscription by ID                                       |
| **Retrieve Subscription** | Retrieve a subscription by its ID                                 |
| **Create Subscription**   | Create a subscription for a customer with a price                 |
| **Update Customer**       | Update customer attributes such as name, email, etc.              |
| **Create Coupon**         | Create a new Stripe coupon for discounts                          |
| **Retrieve Coupon**       | Retrieve a specific Stripe coupon by ID                           |
| **List Coupons**          | List all Stripe coupons with optional filtering and pagination    |
| **Delete Coupon**         | Delete a Stripe coupon (prevents new customers from redeeming it) |
| **Search Customers**      | Search for customers using Stripe's Search Query Language         |
| **Search Invoices**       | Search for invoices using Stripe's Search Query Language          |

## Example Prompts

Use these with your agent or in the Agent Node:

**List customers:**

```text theme={"dark"}
Show me all Stripe customers
```

**View subscriptions:**

```text theme={"dark"}
List all active subscriptions
```

**Check balance:**

```text theme={"dark"}
What's my current Stripe account balance?
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Stripe credentials and that you have the required permissions                                           |
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

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Stripe MCP server](https://www.gumloop.com/mcp/stripe) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
