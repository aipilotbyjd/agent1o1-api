> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Confluence

> Manage documentation and knowledge bases with AI-powered content automation.

Confluence is Atlassian's collaboration platform for creating and organizing documentation. The Confluence MCP server lets you search pages, create content, and manage tasks using natural language.

## What Can It Do?

* **Find pages and blog posts** with flexible filters
* **Create and update** pages and blog posts in specific spaces
* **List and manage tasks** including status updates
* **Upload, list, and manage attachments** on pages
* **Discover spaces** to target where to publish or search

## Where to Use It

### In Agents (Recommended)

Add Confluence as a tool to any agent. The agent can then interact with your documentation conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Confluence tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search pages in the Engineering space")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                  | Description                                                   |
| --------------------- | ------------------------------------------------------------- |
| **List Pages**        | Search pages with filtering options                           |
| **Get Page**          | Get a specific page by ID                                     |
| **Create Page**       | Create a new page in a space                                  |
| **Update Page**       | Update a page by ID                                           |
| **List Tasks**        | List tasks with filtering                                     |
| **Get Task**          | Get a specific task                                           |
| **Update Task**       | Update task status                                            |
| **List Blog Posts**   | Search blog posts                                             |
| **Get Blog Post**     | Get a specific blog post                                      |
| **Create Blog Post**  | Create a new blog post                                        |
| **Update Blog Post**  | Update a blog post                                            |
| **Get Spaces**        | List spaces with filtering                                    |
| **Get Database**      | Get a specific database                                       |
| **Upload Attachment** | Upload a file to a Confluence page as an attachment           |
| **List Attachments**  | List attachments with filtering options                       |
| **Get Attachment**    | Get attachment metadata by ID, optionally download to storage |
| **Delete Attachment** | Delete an attachment by ID                                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find pages:**

```text theme={"dark"}
Search for pages about "API documentation" in the Engineering space
```

**Create a page:**

```text theme={"dark"}
Create a new page titled "Q4 Planning" in the Product space
```

**Update a task:**

```text theme={"dark"}
Mark the task "Review architecture docs" as complete
```

**Discover spaces:**

```text theme={"dark"}
List all spaces I have access to
```

**Publish a blog post:**

```text theme={"dark"}
Create a blog post titled "Product Update - January" in the Company News space
```

**Upload an attachment:**

```text theme={"dark"}
Upload the file "report.pdf" to the Q4 Planning page
```

**List attachments:**

```text theme={"dark"}
Show me all attachments on page 12345
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific space names or search terms                                                                                                           |
| Action not completing            | Check that you've authenticated and have permissions for the space                                                                                 |
| Unexpected results               | The agent may chain multiple tools (e.g., finding the space first, then creating a page). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create a page in the Marketing space" will find the space first, then create the page. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Confluence MCP server](https://www.gumloop.com/mcp/confluence) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
