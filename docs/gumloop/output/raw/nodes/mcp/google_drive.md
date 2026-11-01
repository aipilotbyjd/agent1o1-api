> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Drive

> Search, organize, and share files with AI-powered cloud storage automation.

Google Drive is Google's cloud storage service for files and folders. The Google Drive MCP server lets you search, create, move, and share files using natural language.

## What Can It Do?

* **Search files and folders** by name, keyword, or date
* **List files** with folder, date, name, and file type filters, sorted and paginated
* **Create, copy, move, and delete** files and folders
* **Trash and restore** files and folders without deleting them permanently
* **Generate new documents** and folder structures on demand
* **Upload and download files** between Gumloop storage and Google Drive
* **Share files** with specific permissions and get sharing links

## Where to Use It

### In Agents (Recommended)

Add Google Drive as a tool to any agent. The agent can then manage your files conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Drive tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for files modified this week")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                            | Description                                                                                                    |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| **Search**                      | Search for files in Drive                                                                                      |
| **Copy File**                   | Create a copy of a file                                                                                        |
| **Create Folder Subfolder**     | Create folders and subfolders                                                                                  |
| **Move File**                   | Move files between folders                                                                                     |
| **Create Plain Text File**      | Create new text files                                                                                          |
| **Add File Sharing Preference** | Share files and get links                                                                                      |
| **Update Name**                 | Rename files or folders                                                                                        |
| **Get File**                    | Get file metadata and optionally read content (see [supported file types](#get-file-content-reading))          |
| **List Contents**               | List files in a folder (excludes trashed files by default)                                                     |
| **List Files**                  | List files with folder, created/modified date, name, and MIME type filters, with sorting and cursor pagination |
| **Upload File**                 | Upload a file from Gumloop storage to Google Drive with optional folder destination                            |
| **Download File**               | Download a file from Google Drive to Gumloop storage                                                           |
| **Delete**                      | Permanently delete files or folders                                                                            |
| **Trash File**                  | Move a file or folder to the trash, by file ID or Drive URL                                                    |
| **Untrash File**                | Restore a file or folder from the trash, by file ID or Drive URL                                               |

<Info>
  **List Contents** excludes trashed files by default. Set `trashed` to `true` to include trashed files alongside live ones, or `only_trashed` to `true` to return only trashed files (`only_trashed` takes precedence over `trashed`).
</Info>

## List Files

**List Files** is the tool to use when you want a filtered, ordered, and paginated view of a Drive rather than a keyword search.

**Filters:**

| Parameter                           | What it does                                                                                                |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `folder_url` or `folder_id`         | Restrict results to the direct children of a folder (`folder_url` is preferred)                             |
| `created_after`, `created_before`   | Filter by creation date (`YYYY-MM-DD` or `YYYY-MM-DDTHH:mm:ss`, UTC)                                        |
| `modified_after`, `modified_before` | Filter by last modified date (same formats)                                                                 |
| `mime_type`                         | Exact MIME type (`image/jpeg`) or a category prefix ending in `/` (`image/`) to match a whole file type     |
| `name_contains`                     | Match files whose name contains this text                                                                   |
| `trashed`                           | Set to `true` to list only trashed files (default `false`, live files only)                                 |
| `order_by`                          | `modifiedTime desc` (default), `modifiedTime`, `createdTime desc`, `createdTime`, `name`, or `recency desc` |
| `max_limit`                         | Maximum number of files to return (default 100)                                                             |
| `cursor`                            | Pagination cursor from a previous response's `next_cursor`                                                  |

Results come back as `items` plus a `next_cursor`. Pass `next_cursor` back in as `cursor` to fetch the next batch. When `next_cursor` is `null`, there are no more files.

<Tip>
  For large folders, keep the default `modifiedTime desc` ordering and page through results with the cursor instead of raising `max_limit`.
</Tip>

<Info>
  The **Search** tool also supports `created_after` and `created_before` alongside `modified_after` and `modified_before`, so you can narrow keyword searches by creation date.
</Info>

## Get File Content Reading

The **Get File** tool can return file metadata by default, and optionally read file content when the `read` parameter is set to `true`.

**Supported file types** (uploaded/binary files):

* Plain text files (`.txt`, `.csv`, `.json`, etc.)
* PDFs (`.pdf`)
* Microsoft Office documents (`.docx`, `.xlsx`, `.pptx`)
* Other common formats that store content as binary data

**Not supported** (native Google Workspace files):

* Google Docs
* Google Sheets
* Google Slides

Native Google Workspace files (Docs, Sheets, Slides) do not have downloadable binary content through the Drive API, so `Get File` cannot extract their content. To read or modify the content of these files, use their dedicated MCP integrations instead:

* **Google Docs** → [Google Docs MCP](/nodes/mcp/google_docs)
* **Google Sheets** → Google Sheets MCP

<Warning>
  If you use `Get File` with `read=true` on a native Google Workspace file (Doc, Sheet, or Slide), it will return metadata successfully but fail to extract the file content. Use the dedicated MCP integration for that file type instead (e.g., Google Docs MCP for Docs, Google Sheets MCP for Sheets).
</Warning>

## Example Prompts

Use these with your agent or in the Agent Node:

**Search files:**

```text theme={"dark"}
Find files containing "invoice" modified in the last week
```

**Create a folder:**

```text theme={"dark"}
Create a folder called "Q4 Reports" with subfolders for each month
```

**Share a file:**

```text theme={"dark"}
Share the budget spreadsheet with view access for anyone with the link
```

**Move files:**

```text theme={"dark"}
Move all files from the Inbox folder to the Archive folder
```

**Get file details:**

```text theme={"dark"}
Get the details and sharing link for the marketing presentation
```

**List files with filters:**

```text theme={"dark"}
List all PDFs created after 2026-01-01 in the Contracts folder, newest first
```

**List only trashed files:**

```text theme={"dark"}
List only the trashed files in my Reports folder
```

**Trash a file:**

```text theme={"dark"}
Move the "Old Pricing" doc to the trash
```

**Restore a file:**

```text theme={"dark"}
Restore the "Old Pricing" doc from the trash
```

## Troubleshooting

| Issue                            | Solution                                                                                                                          |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific file names or folder paths                                                                                           |
| Action not completing            | Check that you've authenticated and have access to the files                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then moving). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)               |

<Warning>
  **Delete** removes a file permanently and cannot be undone. Use **Trash File** instead when you may want to restore the file later with **Untrash File**.
</Warning>

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Share the Q4 report" will find the file first, then share it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Drive MCP server](https://www.gumloop.com/mcp/gdrive) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
