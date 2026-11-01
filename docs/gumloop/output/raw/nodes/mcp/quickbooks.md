> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# QuickBooks

> Manage accounting, invoices, customers, and financial reports with AI-powered automation.

QuickBooks Online is Intuit's cloud-based accounting platform used by millions of small and mid-sized businesses. The QuickBooks MCP server lets you manage invoices, customers, vendors, payments, bills, and financial reports using natural language.

## What Can It Do?

* **Manage invoices** — create, update, send, void, and delete invoices
* **Track customers and vendors** — create and update contact records
* **Record payments and bills** — log customer payments, vendor bills, and bill payments
* **Generate financial reports** — Profit & Loss, Balance Sheet, Cash Flow, Trial Balance, and more
* **Query any entity** — use SQL-like syntax to list and filter any QuickBooks object

## Where to Use It

### In Agents (Recommended)

Add QuickBooks as a tool to any agent. The agent can then manage your accounting data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your QuickBooks account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with QuickBooks tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create an invoice for a customer")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Querying

| Tool              | Description                                                   |
| ----------------- | ------------------------------------------------------------- |
| **Execute Query** | Run a SQL-like query to list and filter any QuickBooks entity |

### Invoices

| Tool               | Description                                               |
| ------------------ | --------------------------------------------------------- |
| **Get Invoice**    | Retrieve a single invoice by ID                           |
| **Create Invoice** | Create a new invoice for a customer with line items       |
| **Update Invoice** | Update an existing invoice                                |
| **Delete Invoice** | Delete an invoice                                         |
| **Void Invoice**   | Void an invoice (keeps the record but zeroes the balance) |
| **Send Invoice**   | Send an invoice via email to the customer                 |

### Customers

| Tool                | Description                  |
| ------------------- | ---------------------------- |
| **Get Customer**    | Retrieve a customer by ID    |
| **Create Customer** | Create a new customer record |
| **Update Customer** | Update an existing customer  |

### Vendors

| Tool              | Description                |
| ----------------- | -------------------------- |
| **Get Vendor**    | Retrieve a vendor by ID    |
| **Create Vendor** | Create a new vendor record |
| **Update Vendor** | Update an existing vendor  |

### Payments

| Tool                    | Description                                |
| ----------------------- | ------------------------------------------ |
| **Get Payment**         | Retrieve a customer payment by ID          |
| **Create Payment**      | Record a customer payment against invoices |
| **Delete Payment**      | Void a customer payment                    |
| **Create Bill Payment** | Record a bill payment to a vendor          |

### Bills

| Tool            | Description                     |
| --------------- | ------------------------------- |
| **Get Bill**    | Retrieve a vendor bill by ID    |
| **Create Bill** | Create a new bill from a vendor |
| **Update Bill** | Update an existing bill         |

### Estimates

| Tool                | Description                                |
| ------------------- | ------------------------------------------ |
| **Get Estimate**    | Retrieve an estimate by ID                 |
| **Create Estimate** | Create a new estimate/quote for a customer |
| **Update Estimate** | Update an existing estimate                |

### Items (Products & Services)

| Tool            | Description                              |
| --------------- | ---------------------------------------- |
| **Get Item**    | Retrieve a product or service item by ID |
| **Create Item** | Create a new product or service item     |
| **Update Item** | Update an existing item                  |

### Accounts (Chart of Accounts)

| Tool               | Description                                   |
| ------------------ | --------------------------------------------- |
| **Get Account**    | Retrieve an account by ID                     |
| **Create Account** | Create a new account in the chart of accounts |
| **Update Account** | Update an existing account                    |

### Employees

| Tool                | Description                                     |
| ------------------- | ----------------------------------------------- |
| **Get Employee**    | Retrieve an employee by ID                      |
| **Create Employee** | Create a new employee record                    |
| **Update Employee** | Update an existing employee using sparse update |

### Journal Entries

| Tool                     | Description                                        |
| ------------------------ | -------------------------------------------------- |
| **Get Journal Entry**    | Retrieve a journal entry by ID                     |
| **Create Journal Entry** | Create a new journal entry with debit/credit lines |

### Sales Receipts & Credit Memos

| Tool                     | Description                            |
| ------------------------ | -------------------------------------- |
| **Get Sales Receipt**    | Retrieve a sales receipt by ID         |
| **Create Sales Receipt** | Create a new sales receipt (cash sale) |
| **Get Credit Memo**      | Retrieve a credit memo by ID           |
| **Create Credit Memo**   | Create a credit memo for a customer    |

### Vendor Credits & Purchases

| Tool                     | Description                       |
| ------------------------ | --------------------------------- |
| **Get Vendor Credit**    | Retrieve a vendor credit by ID    |
| **Create Vendor Credit** | Create a vendor credit            |
| **Get Purchase**         | Retrieve a purchase/expense by ID |
| **Get Purchase Order**   | Retrieve a purchase order by ID   |

### Time Activities

| Tool                     | Description                          |
| ------------------------ | ------------------------------------ |
| **Get Time Activity**    | Retrieve a time tracking entry by ID |
| **Create Time Activity** | Log a new time tracking entry        |
| **Update Time Activity** | Update an existing time activity     |

### Financial Reports

| Tool                             | Description                                                                     |
| -------------------------------- | ------------------------------------------------------------------------------- |
| **Get Profit And Loss Report**   | Run a Profit & Loss report for a date range                                     |
| **Get Balance Sheet Report**     | Run a Balance Sheet report as of a date                                         |
| **Get Cash Flow Report**         | Run a Statement of Cash Flows report                                            |
| **Get Trial Balance Report**     | Run a Trial Balance report as of a date                                         |
| **Get Aged Receivables Report**  | Run an Aged Receivables report                                                  |
| **Get Aged Payables Report**     | Run an Aged Payables report                                                     |
| **Get General Ledger Report**    | Run a General Ledger report                                                     |
| **Get Budget vs Actuals Report** | Run a Budget vs Actuals report comparing budgeted amounts to actual performance |

### Company Info

| Tool                 | Description                               |
| -------------------- | ----------------------------------------- |
| **Get Company Info** | Retrieve connected company information    |
| **Get Preferences**  | Retrieve company preferences and settings |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create an invoice:**

```text theme={"dark"}
Create an invoice for customer John Smith for 5 hours of consulting at $150/hour
```

**Check outstanding receivables:**

```text theme={"dark"}
Run an aged receivables report and show me anything overdue by more than 30 days
```

**Record a payment:**

```text theme={"dark"}
Record a $750 payment from Acme Corp against invoice INV-1042
```

**Get financial overview:**

```text theme={"dark"}
Show me the Profit & Loss report for Q1 2026
```

**Find a customer:**

```text theme={"dark"}
Find all invoices for customers in California with a balance over $1000
```

## Troubleshooting

| Issue                  | Solution                                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failing | Ensure you've connected your QuickBooks Online account (not Desktop)                                                |
| Entity not found       | Use `Execute Query` to search for the correct ID first                                                              |
| Action not completing  | Check that you've authenticated with the correct QuickBooks company                                                 |
| Tool not available     | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Use `Execute Query` with SQL-like syntax to find IDs before performing updates or deletes. For example: `SELECT * FROM Customer WHERE DisplayName LIKE 'Acme%'`
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [QuickBooks MCP server](https://www.gumloop.com/mcp/quickbooks) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
