> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio

> Manage contacts, companies, and relationships with AI-powered CRM automation.

Attio is a modern relationship management platform that keeps contacts, companies, and interactions organized. The Attio MCP server lets you search, create, and update CRM records using natural language.

## What Can It Do?

* **Search companies and people** with flexible filters
* **Read, create, and update** company and person records
* **Manage lists** by viewing entries or adding new records
* **Sync CRM data** with other tools in your workflows

## Where to Use It

### In Agents (Recommended)

Add Attio as a tool to any agent. The agent can then interact with your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Attio tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search companies in the fintech industry")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                 | Description                             |
| -------------------- | --------------------------------------- |
| **Search Companies** | Search companies with filtering options |
| **Read Company**     | Get details for a specific company      |
| **Create Company**   | Create a new company record             |
| **Update Company**   | Update fields on a company              |
| **Search People**    | Search people with flexible filters     |
| **Read Person**      | Get details for a specific person       |
| **Create Person**    | Add a new person to Attio               |
| **Update Person**    | Update fields on a person               |
| **List Lists**       | List all lists in your workspace        |
| **Read List**        | Read entries from a specific list       |
| **Add To List**      | Add a company or person to a list       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find a company:**

```text theme={"dark"}
Search for companies in the fintech industry headquartered in London
```

**Find prospects:**

```text theme={"dark"}
Find people with title "VP Sales" at SaaS companies in San Francisco
```

**Update a record:**

```text theme={"dark"}
Update DataCorp to set their industry to "AI/ML" and funding stage to "Series B"
```

**Add to a list:**

```text theme={"dark"}
Add NewDeal Inc to the Pipeline - Due Diligence list
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific filters like industry, location, or title                                                                                              |
| Action not completing            | Check that you've authenticated and have the necessary Attio permissions                                                                            |
| Unexpected results               | The agent may chain multiple tools (e.g., searching companies first, then finding people). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find the CEO of TechCorp" will search for the company first, then find people at that company. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Attio MCP server](https://www.gumloop.com/mcp/attio) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
