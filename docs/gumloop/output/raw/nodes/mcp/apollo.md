> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Apollo

> Find, enrich, and track B2B contacts and companies with AI-powered prospecting.

Apollo is one of the world's largest B2B prospecting databases with 275M+ contacts and 73M+ companies. The Apollo MCP server lets you search prospects, enrich leads, and monitor companies using natural language.

## What Can It Do?

* **Find prospects** with powerful filters for role, location, company, and keywords
* **Enrich leads** with fresh emails, titles, and firmographic data
* **Monitor companies** for job postings that signal buying intent
* **Search organizations** by industry, size, funding, and location

## Where to Use It

### In Agents (Recommended)

Add Apollo as a tool to any agent. The agent can then search and enrich prospects conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Apollo tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Find sales managers at SaaS companies in San Francisco")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                              | Description                                               |
| --------------------------------- | --------------------------------------------------------- |
| **People Search**                 | Find people by role, location, company, or keywords       |
| **Organization Search**           | Search companies by industry, size, location, and funding |
| **Enrich Person**                 | Get full profile data for a known email or person ID      |
| **Enrich Organization**           | Get firmographic data for a known domain or org ID        |
| **Get Organization Job Postings** | Retrieve current job listings for a company               |

## Credit Costs

| Tool                          | Credits Per Use      |
| ----------------------------- | -------------------- |
| People Search                 | 3 credits per result |
| Organization Search           | 3 credits per result |
| Enrich Person                 | 3+ credits           |
| Enrich Organization           | 5 credits            |
| Get Organization Job Postings | 3 credits per result |

## Example Prompts

Use these with your agent or in the Agent Node:

**Prospect discovery:**

```text theme={"dark"}
Find 10 product managers at AI startups funded in the last 2 years
```

**Company research:**

```text theme={"dark"}
Search for cybersecurity companies with 50-200 employees in Austin
```

**Lead enrichment:**

```text theme={"dark"}
Get the full profile for john.smith@acme.com including their title and LinkedIn
```

**Hiring signals:**

```text theme={"dark"}
Show me the latest job postings from Stripe
```

**Targeted outreach:**

```text theme={"dark"}
Find VPs of Marketing at fintech companies in New York
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use more specific filters like job title, location, or company size                                                                                 |
| Action not completing            | Check that you've authenticated and have sufficient Apollo credits                                                                                  |
| Unexpected results               | The agent may chain multiple tools (e.g., searching companies first, then finding people). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find the CEO of companies that raised Series A last year" will search organizations first, then find people. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Apollo MCP server](https://www.gumloop.com/mcp/apollo) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
