> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# SharePoint

> Manage SharePoint sites, lists, and documents with AI-powered automation.

Microsoft SharePoint is Microsoft 365's platform for team sites, document libraries, and lists. The SharePoint MCP server lets you browse sites, work with lists and list items, manage files and folders in document libraries, and create and publish site pages using natural language.

## What Can It Do?

* **Find and browse sites** across your tenant, including subsites
* **Work with lists** by listing, creating lists, and reading their columns
* **Manage list items** by creating, updating, and deleting them
* **Manage documents** by listing, searching, downloading, uploading, copying, moving, and deleting files and folders
* **Share and version files** with sharing links, version history, and check-in/check-out
* **Create and publish site pages**

## Where to Use It

### In Agents (Recommended)

Add SharePoint as a tool to any agent. The agent can then manage your sites, lists, and documents conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with SharePoint tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Upload a file to the Marketing document library")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Sites

| Tool              | Description                                                               |
| ----------------- | ------------------------------------------------------------------------- |
| **List Sites**    | Search SharePoint sites across the tenant that you can access             |
| **Get Site**      | Get a site's details by site ID or site URL                               |
| **List Subsites** | List the subsites of a site                                               |
| **List Users**    | List Microsoft 365 users, for resolving people in list item person fields |

### Lists

| Tool                 | Description                                                       |
| -------------------- | ----------------------------------------------------------------- |
| **List Lists**       | List all lists in a site, or get one list when a list ID is given |
| **Create List**      | Create a new list in a site                                       |
| **List Columns**     | List the column definitions of a list                             |
| **List List Items**  | List items in a list, or get one item when an item ID is given    |
| **Create List Item** | Create a new item in a list                                       |
| **Update List Item** | Update the field values of an existing list item                  |
| **Delete List Item** | Delete an item from a list                                        |

### Documents

| Tool                     | Description                                                          |
| ------------------------ | -------------------------------------------------------------------- |
| **List Drives**          | List the document libraries (drives) of a site                       |
| **List Files**           | List files and folders in a document library, folder, or folder path |
| **Search Files**         | Search for files within one document library                         |
| **Get File**             | Get a file's metadata and download URL by item ID or SharePoint URL  |
| **Download File**        | Download a file from SharePoint into Gumloop storage                 |
| **Upload File**          | Upload a file from Gumloop storage to a document library             |
| **Create Folder**        | Create a folder in a document library                                |
| **Copy File**            | Copy a file or folder, optionally to another drive or folder         |
| **Move File**            | Move or rename a file or folder                                      |
| **Delete File**          | Delete a file or folder (moves it to the recycle bin)                |
| **Create Sharing Link**  | Create a sharing link for a file or folder                           |
| **List File Versions**   | List the version history of a file                                   |
| **Restore File Version** | Restore a file to a previous version                                 |
| **Checkout File**        | Check out a file to prevent others from editing it                   |
| **Checkin File**         | Check in a file, making its latest changes available to others       |

### Pages

| Tool             | Description                                                                        |
| ---------------- | ---------------------------------------------------------------------------------- |
| **List Pages**   | List the pages of a site, or get one page with its content when a page ID is given |
| **Create Page**  | Create a new page in a site                                                        |
| **Publish Page** | Publish the latest version of a page, making it visible to all users               |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find a site:**

```text theme={"dark"}
Find the "Marketing" SharePoint site and show me its document libraries
```

**Work with list items:**

```text theme={"dark"}
Add a new item to the "Project Tracker" list with title "Q4 Launch" and status "In Progress"
```

**Upload a document:**

```text theme={"dark"}
Upload the report from my Gumloop storage to the Marketing site's Documents library
```

**Search for a file:**

```text theme={"dark"}
Search the Documents library for files with "budget" in the name
```

**Share a file:**

```text theme={"dark"}
Create a view-only sharing link for the Q4 budget spreadsheet
```

**Publish a page:**

```text theme={"dark"}
Create a new page called "Team Updates" on the Marketing site and publish it
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                           |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right site | Provide the site URL or exact site name                                                                                                            |
| Action not completing            | Check that you've authenticated with your Microsoft 365 account and have access to the site                                                        |
| Permission errors                | Some actions (like creating lists) require elevated SharePoint permissions on the site                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a site first, then listing its drives). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Upload this file to the Marketing site" will find the site, locate its document library, and upload the file. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [SharePoint MCP server](https://www.gumloop.com/mcp/sharepoint) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
