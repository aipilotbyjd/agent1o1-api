> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Webflow

> Manage sites, collections, forms, and pages with AI-powered web design automation.

Webflow is a visual web development platform. The Webflow MCP server lets you manage sites, CMS collections, forms, pages, and users using natural language.

## What Can It Do?

* **Manage sites** and custom domains
* **Create and update CMS collections** and items
* **Handle form submissions** across your sites
* **Manage pages** and user access

## Where to Use It

### In Agents (Recommended)

Add Webflow as a tool to any agent. The agent can then interact with Webflow conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Webflow tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all CMS collections on my site")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                              | Description                                                |
| --------------------------------- | ---------------------------------------------------------- |
| **Get Authorized User**           | Get information about the authorized Webflow user          |
| **List Sites**                    | List all sites the provided access token is able to access |
| **Get Site**                      | Get details of a specific site by its ID                   |
| **Get Custom Domains**            | Get a list of all custom domains related to a site         |
| **List Forms**                    | List forms for a given site                                |
| **List Form Submissions**         | List form submissions for a given form                     |
| **Get Form Submission**           | Get information about a specific form submission           |
| **List Form Submissions By Site** | List form submissions for a given site                     |
| **Delete Form Submission**        | Delete a form submission                                   |
| **List Pages**                    | List all pages for a site                                  |
| **Get Page Metadata**             | Get metadata information for a single page                 |
| **Get Page Content**              | Get content from a static page                             |
| **List Collections**              | List all Collections within a Site                         |
| **Get Collection**                | Get the full details of a collection from its ID           |
| **Delete Collection**             | Delete a collection using its ID                           |
| **Create Collection**             | Create a Collection for a site                             |
| **List Collection Items**         | List all Items within a Collection                         |
| **Get Collection Item**           | Get details of a selected Collection Item                  |
| **Update Collection Item**        | Update a selected Item in a Collection                     |
| **Update Collection Items**       | Update a single item or multiple items in a Collection     |
| **Create Collection Item**        | Create Item in a Collection                                |
| **Delete Collection Item**        | Delete an item from a collection                           |
| **Delete Collection Items**       | Delete Items from a Collection                             |
| **List Users**                    | Get a list of users for a site                             |
| **Get User**                      | Get a User by ID                                           |
| **Delete User**                   | Delete a User by ID                                        |
| **Invite User**                   | Create and invite a user with an email address             |

## Example Prompts

Use these with your agent or in the Agent Node:

**List sites:**

```text theme={"dark"}
Show me all my Webflow sites
```

**Manage collections:**

```text theme={"dark"}
List all CMS collections on my site
```

**View submissions:**

```text theme={"dark"}
Get the latest form submissions for my contact form
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Webflow credentials and that you have the required permissions                                          |
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

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Webflow MCP server](https://www.gumloop.com/mcp/webflow) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
