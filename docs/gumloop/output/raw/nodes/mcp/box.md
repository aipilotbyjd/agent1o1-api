> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Box

> Manage your cloud storage with AI-powered file and folder automation.

Box is a leading cloud content management and file sharing platform for businesses. The Box MCP server lets you search, manage, and access files and folders stored in your Box account using natural language.

## What Can It Do?

* **Search files and folders** with advanced filtering by type, date, and content
* **Manage files** including upload, download, copy, move, and delete operations
* **Organize folders** by creating, listing, and managing folder structures
* **Share content** by creating shared links and adding collaborators
* **Manage users and groups** for enterprise collaboration

## Where to Use It

### In Agents (Recommended)

Add Box as a tool to any agent. The agent can then manage your cloud storage conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Box account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Box tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for PDF files in the Reports folder")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

Box uses OAuth 2.0 for authentication. When connecting your Box account, you'll be redirected to Box to authorize Gumloop to access your files and folders. The integration will automatically handle token refresh to maintain your connection.

## Available Tools

### Search

| Tool             | Description                                                             |
| ---------------- | ----------------------------------------------------------------------- |
| **Search Files** | Search for files, folders, and web links in Box with advanced filtering |

### File Operations

| Tool            | Description                                                            |
| --------------- | ---------------------------------------------------------------------- |
| **Get File**    | Get file metadata by ID, optionally download file content to workspace |
| **Upload File** | Upload a file from Gumloop workspace to Box                            |
| **Delete File** | Delete a file from Box                                                 |
| **Copy File**   | Copy a file to a destination folder                                    |
| **Move File**   | Move and optionally rename a file                                      |

### Folder Operations

| Tool              | Description                        |
| ----------------- | ---------------------------------- |
| **List Files**    | List files and folders in a folder |
| **List Folders**  | List only folders in a folder      |
| **Get Folder**    | Get folder metadata by ID          |
| **Create Folder** | Create a new folder                |

### User and Group Management

| Tool              | Description                      |
| ----------------- | -------------------------------- |
| **Search Users**  | List or search enterprise users  |
| **Search Groups** | List or search enterprise groups |

### Sharing and Collaboration

| Tool                   | Description                                         |
| ---------------------- | --------------------------------------------------- |
| **Create Shared Link** | Create or update a shared link for a file or folder |
| **Add Collaborator**   | Add a collaborator to a file or folder              |
| **Update Tags**        | Add or remove tags on a file or folder              |

### AI Tools

| Tool                      | Description                                                                            |
| ------------------------- | -------------------------------------------------------------------------------------- |
| **AI Ask**                | Ask a question about files or a Box Hub using Box AI                                   |
| **AI Extract**            | Extract metadata from files using a freeform prompt with Box AI                        |
| **AI Extract Structured** | Extract structured metadata from files using fields or a metadata template with Box AI |
| **AI Text Gen**           | Generate text based on file content using Box AI                                       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search for files:**

```text theme={"dark"}
Find all PDF files in my Box account that contain "quarterly report" in the name
```

**List folder contents:**

```text theme={"dark"}
Show me all files in the Marketing folder
```

**Upload a file:**

```text theme={"dark"}
Upload the sales_data.csv file to the Reports folder in Box
```

**Create a shared link:**

```text theme={"dark"}
Create a shared link for the Q4 presentation that expires in 7 days
```

**Move files:**

```text theme={"dark"}
Move all files from the Inbox folder to the Archive folder
```

**Add a collaborator:**

```text theme={"dark"}
Add john@company.com as an editor to the Project Alpha folder
```

## Search Options

The search tool supports advanced filtering options:

* **Type**: Filter by `file`, `folder`, or `web_link`
* **Scope**: Search `user_content` (your files) or `enterprise_content` (requires admin)
* **File Extensions**: Filter by specific file types (e.g., `pdf`, `docx`, `xlsx`)
* **Ancestor Folders**: Limit search to specific folder IDs
* **Owner**: Filter by owner user IDs
* **Content Types**: Search within `name`, `description`, `file_content`, `comments`, or `tag`
* **Size Range**: Filter by file size in bytes
* **Date Ranges**: Filter by creation or modification date
* **Trash Content**: Include or exclude trashed items

## Troubleshooting

| Issue                   | Solution                                                                                                                               |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding files | Use specific search terms and check folder permissions                                                                                 |
| Action not completing   | Check that you've authenticated with Box                                                                                               |
| Permission denied       | Ensure you have access to the file or folder                                                                                           |
| Upload failed           | Verify the file exists in your Gumloop workspace                                                                                       |
| Tool not available      | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                    |
| Unexpected results      | The agent may chain multiple tools (e.g., searching first, then downloading). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Download the latest sales report" will search for the file first, then download it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Working with Folder IDs

Many Box operations require folder IDs. Here are some tips:

* The root folder ID is always `0`
* Use "List Files" or "List Folders" to discover folder IDs
* Use "Search Files" to find specific files and their IDs
* Folder IDs are returned in the response when creating new folders

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Box MCP server](https://www.gumloop.com/mcp/box) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
