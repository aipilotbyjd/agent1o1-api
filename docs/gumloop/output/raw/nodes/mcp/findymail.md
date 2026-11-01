> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Findymail

> Find and verify professional emails with AI-powered lead discovery and enrichment.

Findymail is an email finding and verification platform for B2B prospecting. The Findymail MCP server lets you find emails, verify addresses, enrich companies, discover employees, and search for leads using natural language.

## What Can It Do?

* **Find emails** by name and domain, domain only, or LinkedIn URL
* **Verify email addresses** to check deliverability
* **Enrich companies** with firmographic data like size, industry, and domain
* **Find employees** at target companies by job title
* **Search leads** using AI-powered IntelliMatch with natural language queries
* **Find lookalike companies** similar to a seed company
* **Manage contact lists** to organize your prospects

## Where to Use It

### In Agents (Recommended)

Add Findymail as a tool to any agent. The agent can then find and verify emails conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Findymail tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Find the email for the CEO of gumloop.com")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                                                   |
| --------------------------- | ----------------------------------------------------------------------------- |
| **Find Email**              | Find a person's email address by name+domain, domain only, or LinkedIn URL    |
| **Verify Email**            | Verify if an email address is valid and deliverable                           |
| **Find Phone**              | Find a person's phone number from their LinkedIn profile URL                  |
| **Enrich Company**          | Get company details like size, industry, and domain from a company identifier |
| **Find Employees**          | Find employees at a company by website and job titles                         |
| **Reverse Email Lookup**    | Look up a person's profile and company info from their email address          |
| **List Contact Lists**      | List all saved contact lists                                                  |
| **Create Contact List**     | Create a new contact list                                                     |
| **Get Contacts**            | Get contacts saved in a specific list or all contacts                         |
| **Search Leads**            | Search for leads using AI-powered IntelliMatch with natural language queries  |
| **Get Lead Search Status**  | Check the status of an IntelliMatch lead search by its hash                   |
| **Get Lead Search Results** | Get paginated results from a completed IntelliMatch lead search               |
| **Search Lookalike**        | Find companies similar to a given company                                     |

## Setting Up Credentials

Findymail uses API key authentication. You'll need to provide your Findymail API key to connect.

**To get your API key:**

1. Log in to your [Findymail Dashboard](https://app.findymail.com)
2. Navigate to your account settings or API section
3. Copy your API key

**To connect in Gumloop:**

1. Go to your [Connectors page](https://www.gumloop.com/personal/connectors)
2. Find Findymail and click **Connect**
3. Paste your API key when prompted

<Info>
  Your API key is stored securely and used to authenticate all Findymail API requests on your behalf. Each API call consumes credits from your Findymail account based on the action performed.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Find an email:**

```text theme={"dark"}
Find the email address for Satya Nadella at Microsoft
```

**Verify an email:**

```text theme={"dark"}
Is john@company.com a valid email address?
```

**Enrich a company:**

```text theme={"dark"}
Get company details for gumloop.com
```

**Find employees:**

```text theme={"dark"}
Find the CEO and CTO at stripe.com
```

**Search for leads:**

```text theme={"dark"}
Find SaaS companies in San Francisco with 50-200 employees
```

**Find similar companies:**

```text theme={"dark"}
Find companies similar to stripe.com in the same country
```

**Reverse lookup:**

```text theme={"dark"}
Who is the person behind sarah@acme.com? Include their full profile.
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                                   |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Provide specific names, domains, or LinkedIn URLs for accurate results                                                                                     |
| Action not completing            | Check that you've connected your Findymail API key and have sufficient credits                                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a company first, then searching for employees). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                        |
| Lead search taking long          | IntelliMatch searches are asynchronous. Use **Get Lead Search Status** to poll progress, then **Get Lead Search Results** once complete.                   |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find the email for the VP of Sales at Acme Corp" will enrich the company first, then find the employee. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Findymail MCP server](https://www.gumloop.com/mcp/findymail) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
