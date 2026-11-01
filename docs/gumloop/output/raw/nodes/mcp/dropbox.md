> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Dropbox

> Manage files, folders, sharing, and file requests with AI-powered cloud storage automation.

Dropbox is a cloud storage and file sharing platform used by millions of individuals and teams. The Dropbox MCP server lets you manage files and folders, create shared links, collaborate with team members, and collect files via file requests using natural language.

## What Can It Do?

* **Browse and manage files** — list, search, create, move, copy, and delete files and folders
* **Upload and download files** — transfer files between Gumloop and your Dropbox
* **Share content** — create shared links and share folders with specific users
* **Collect files** — create and manage file requests to gather files from anyone
* **Check account info** — view storage usage and account details

## Where to Use It

### In Agents (Recommended)

Add Dropbox as a tool to any agent. The agent can then manage your cloud storage conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Dropbox account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Dropbox tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Upload a file to Dropbox")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Files & Folders

| Tool                      | Description                                     |
| ------------------------- | ----------------------------------------------- |
| **List Folder**           | List files and folders in a Dropbox directory   |
| **Get File Metadata**     | Get metadata for a file or folder               |
| **Search Files**          | Search for files and folders by name or content |
| **Create Folder**         | Create a new folder                             |
| **Delete File Or Folder** | Delete a file or folder                         |
| **Move File Or Folder**   | Move or rename a file or folder                 |
| **Copy File Or Folder**   | Copy a file or folder                           |
| **Upload File**           | Upload a file from Gumloop storage to Dropbox   |
| **Download File**         | Download a file from Dropbox to Gumloop storage |
| **Get Temporary Link**    | Get a temporary direct download link for a file |
| **List Revisions**        | List the revision history of a file             |

### Sharing

| Tool                    | Description                                               |
| ----------------------- | --------------------------------------------------------- |
| **Create Shared Link**  | Create a shared link for a file or folder                 |
| **List Shared Links**   | List shared links for a file, folder, or all shared links |
| **Share Folder**        | Share a folder with other users                           |
| **Add Folder Member**   | Add a member to a shared folder by email                  |
| **List Folder Members** | List members of a shared folder                           |
| **Share File**          | Share a file with specific users by email                 |

### File Requests

| Tool                     | Description                                        |
| ------------------------ | -------------------------------------------------- |
| **Create File Request**  | Create a file request to collect files from anyone |
| **List File Requests**   | List all file requests owned by the current user   |
| **Get File Request**     | Get details of a specific file request             |
| **Delete File Requests** | Delete one or more file requests                   |

### Account

| Tool                    | Description                                          |
| ----------------------- | ---------------------------------------------------- |
| **Get Current Account** | Get information about the authenticated Dropbox user |
| **Get Space Usage**     | Get current storage space usage and allocation       |

## Example Prompts

Use these with your agent or in the Agent Node:

**Browse files:**

```text theme={"dark"}
List all files in my /Projects/2026 folder
```

**Share a file:**

```text theme={"dark"}
Create a shared link for the file at /Reports/Q1-2026.pdf
```

**Upload content:**

```text theme={"dark"}
Upload the generated report to /Reports/ in my Dropbox
```

**Collect files from others:**

```text theme={"dark"}
Create a file request for client deliverables and send me the link
```

**Check storage:**

```text theme={"dark"}
How much Dropbox storage am I using?
```

## Troubleshooting

| Issue               | Solution                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------- |
| File not found      | Use `List Folder` or `Search Files` to confirm the exact path                                                       |
| Upload failing      | Ensure the destination folder exists before uploading                                                               |
| Sharing not working | Verify the email addresses are correct and the users have Dropbox accounts                                          |
| Tool not available  | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Dropbox paths are case-sensitive and must start with `/`. Use `Search Files` to find the exact path of a file before moving or sharing it.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Dropbox MCP server](https://www.gumloop.com/mcp/dropbox) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
