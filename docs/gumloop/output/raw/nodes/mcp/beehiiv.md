> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Beehiiv

> Manage your newsletter with AI-powered publishing and subscriber automation.

Beehiiv is a newsletter platform for creators and publishers. The Beehiiv MCP server lets you publish posts, manage subscribers, and organize your publication using natural language.

## What Can It Do?

* **Publish and manage posts** automatically
* **Add and update subscribers** with tags and custom fields
* **Retrieve segments and tiers** for targeted campaigns
* **Sync newsletter data** with other tools for reporting

## Where to Use It

### In Agents (Recommended)

Add Beehiiv as a tool to any agent. The agent can then interact with your publication conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Beehiiv tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List the 10 most recent posts")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                               | Description                       |
| ---------------------------------- | --------------------------------- |
| **List Automations**               | List automations in a publication |
| **Create/List Custom Fields**      | Manage custom fields              |
| **Create Post**                    | Create a new post                 |
| **List Posts**                     | Retrieve posts from a publication |
| **Get Post**                       | Get a single post                 |
| **Delete Post**                    | Archive or delete a post          |
| **List Segments**                  | List segments for a publication   |
| **Get Segment**                    | Get segment details               |
| **List Segment Subscribers**       | List subscribers in a segment     |
| **Create Subscription**            | Add a new subscriber              |
| **List Subscriptions**             | List subscriptions                |
| **Get/Update/Delete Subscription** | Manage subscriptions              |
| **Add Subscription Tag**           | Tag a subscription                |
| **List/Create Tiers**              | Manage publication tiers          |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a post:**

```text theme={"dark"}
Create a draft post titled "Product Launch Recap" for my newsletter
```

**Add a subscriber:**

```text theme={"dark"}
Subscribe sarah@company.com to my newsletter with the Premium tier
```

**Find segment subscribers:**

```text theme={"dark"}
List all subscribers in the VIP Readers segment
```

**Tag a subscriber:**

```text theme={"dark"}
Add the "enterprise_customer" tag to cto@bigcorp.com
```

**List recent posts:**

```text theme={"dark"}
Show me the 10 most recent posts and their status
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                       |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific publication names or IDs                                                                                                          |
| Action not completing            | Check that you've authenticated with your Beehiiv API key                                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a subscriber first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                            |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the subscription for [john@example.com](mailto:john@example.com) to Premium" will find the subscription first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Beehiiv MCP server](https://www.gumloop.com/mcp/beehiiv) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
