> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Seismic

> Manage your sales enablement content with AI-powered Seismic automation.

Seismic is the leading sales enablement platform for managing, distributing, and tracking content. The Seismic MCP server lets you search content, manage files and folders, create LiveSend links, and access reporting data using natural language.

## What Can It Do?

* **Search and discover content** across your library with advanced filtering
* **Manage files and folders** including create, update, copy, and delete operations
* **Create LiveSend links** for secure content sharing with recipients
* **Access reporting data** including user activities, content engagement, and AI activities
* **Manage Digital Sales Rooms (DSRs)** for personalized buyer experiences

## Where to Use It

### In Agents (Recommended)

Add Seismic as a tool to any agent. The agent can then manage your sales content conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Seismic account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Seismic tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for content about product pricing")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

Seismic uses OAuth 2.0 for authentication. When connecting your Seismic account, you'll need to authorize Gumloop to access your Seismic tenant. The integration requires your Seismic tenant subdomain for authentication.

The following OAuth scopes are requested:

* `seismic.user.view` - View user information
* `seismic.self.view` - View your own profile and favorites
* `seismic.reporting` - Access reporting data
* `seismic.library.manage` - Manage library content
* `seismic.search` - Search content
* `seismic.gen-search` - Use generative search features
* `seismic.delivery` - Create and manage content delivery
* `seismic.engagement.manage` - Manage engagements and DSRs

## Available Tools

| Tool                         | Description                                                             |
| ---------------------------- | ----------------------------------------------------------------------- |
| **Search Content**           | Search Seismic content with advanced filtering, sorting, and pagination |
| **Search Generative**        | Get AI-generated answers with source documents from your content        |
| **Search Generative Source** | Get source documents for generative search queries                      |
| **Get File Info**            | Get file information including metadata and custom properties           |
| **Update File Info**         | Update file properties including name, owner, and description           |
| **Download File**            | Download the binary content of a file or specific version               |
| **Copy File**                | Copy a file to any target folder within the same teamsite               |
| **Create Folder**            | Create a new folder inside a given folder                               |
| **Get Folder Info**          | Get folder information and properties                                   |
| **Update Folder Info**       | Update folder properties including name and location                    |
| **Copy Folder**              | Copy a folder and its contents to a target folder                       |
| **List Folder Items**        | Get the list of items in a folder with pagination                       |
| **Delete Item**              | Delete any item type from the teamsite                                  |
| **Get Item Info**            | Get information for any item type                                       |
| **Copy Item**                | Copy any item type to a target folder                                   |
| **List Item Versions**       | Get the list of versions for a given item                               |
| **Search Items**             | Search items using filters such as external ID                          |
| **Create URL**               | Add a new URL to the teamsite with metadata                             |
| **Create LiveSend Link**     | Create a LiveSend link for secure content sharing                       |
| **Get LiveSend Settings**    | Get LiveSend settings including password rules                          |
| **List Delivery Options**    | Get available delivery options including custom integrations            |
| **Get Custom Delivery Form** | Get required inputs for a custom delivery form                          |
| **Deliver Custom Content**   | Deliver content via custom delivery options                             |
| **Create Link Delivery**     | Create a link delivery for a Digital Sales Room                         |
| **Generate LiveSend Link**   | Generate a LiveSend link with contents and recipients                   |
| **List Engagements**         | Get engagement information with advanced filtering                      |
| **List CRM Contexts**        | Get CRM context information with filtering                              |
| **List Users**               | Get list of users with filtering and pagination                         |
| **Get User Details**         | Get detailed information for a specific user                            |
| **Get My Favorites**         | Get your favorite content items                                         |
| **Get My Recents**           | Get your recently accessed content items                                |
| **Get My Teamsites**         | Get your assigned teamsites                                             |
| **Get My Profiles**          | Get content profiles assigned to you                                    |
| **Create DSR**               | Create a new Digital Sales Room                                         |
| **List DSRs**                | Get list of Digital Sales Rooms                                         |
| **Get DSR Details**          | Get comprehensive details for a specific DSR                            |
| **Get DSR Comments**         | Get comments from Digital Sales Rooms                                   |
| **Get Reports**              | Get various types of reporting data                                     |
| **Get Users (Reporting)**    | Get list of users from reporting API                                    |
| **Get Group Members**        | Get details on users who are members of a group                         |
| **Get Groups**               | List all user groups in the platform                                    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search for content:**

```text theme={"dark"}
Search for all content items about product pricing updated in the last month
```

**Get file information:**

```text theme={"dark"}
Get the details for the file with ID abc123 in teamsite xyz
```

**Create a LiveSend link:**

```text theme={"dark"}
Create a LiveSend link for the Q4 presentation to share with john@company.com
```

**List folder contents:**

```text theme={"dark"}
Show me all items in the Marketing Materials folder
```

**Get reporting data:**

```text theme={"dark"}
Get the content activity report for the last 30 days
```

## Troubleshooting

| Issue                               | Solution                                                                                                                                   |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right content | Use specific search terms and filters                                                                                                      |
| Action not completing               | Check that you've authenticated with Seismic                                                                                               |
| Unexpected results                  | The agent may chain multiple tools (e.g., searching first, then getting details). Review the agent's reasoning to understand its approach. |
| Tool not available                  | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |
| Teamsite ID required                | Many operations require a teamsite ID - use "Get My Teamsites" first to find available teamsites                                           |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Share the latest pricing deck with the sales team" will search for the content first, then create a LiveSend link. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Seismic MCP server](https://www.gumloop.com/mcp/seismic) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
