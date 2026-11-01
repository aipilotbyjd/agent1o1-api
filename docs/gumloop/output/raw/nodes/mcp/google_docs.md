> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Docs

> Create, read, comment on, and update Google Docs with AI-powered document automation.

Google Docs is the go-to collaborative document editor. The Google Docs MCP server lets you search, read, comment on, create, and update documents using natural language - including rich text formatting with Markdown and HTML.

## What Can It Do?

* **Search documents** in Google Drive by keyword
* **Read document content** including body text, lists, images, footnotes, headers, footers, and styles
* **Create new documents** with plain text, Markdown, or HTML content
* **Update existing documents** by appending, prepending, replacing, or inserting content
* **Manage tables** by inserting new tables and updating individual cells
* **Navigate multi-tab documents** by listing and reading individual tabs
* **Create and rename tabs** in multi-tab documents
* **Work with comments** by reading, creating, replying to, and resolving them

## Where to Use It

### In Agents (Recommended)

Add Google Docs as a tool to any agent. The agent can then search and edit your documents conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Docs tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Read the full content of a Google Doc")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Read Tools

| Tool            | Description                                                                                                                                                          |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Search Docs** | Search for Google Docs in Drive by keyword                                                                                                                           |
| **Read Doc**    | Read content from a Google Doc including body, lists, inline objects, footnotes, headers, footers, and styles. Supports reading specific tabs in multi-tab documents |
| **List Tabs**   | List all tabs in a Google Doc with their IDs and titles                                                                                                              |

### Write Tools

| Tool                  | Description                                                                                                                                                                 |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Create Doc**        | Create a new Google Doc with content in plain text, Markdown, or HTML format, optionally inside a specific Drive folder (by folder ID or URL)                               |
| **Update Doc**        | Update content in an existing Google Doc. Supports append, prepend, replace, insert at index, and find-and-replace operations. Content can be plain text, Markdown, or HTML |
| **Insert Table**      | Insert a table into a Google Doc with optional data and header styling                                                                                                      |
| **Update Table Cell** | Update the content of a specific table cell                                                                                                                                 |
| **Create Tab**        | Create a new tab in an existing Google Doc, optionally nested under a parent tab and at a specific position                                                                 |
| **Rename Tab**        | Rename an existing tab in a Google Doc                                                                                                                                      |

### Comment Tools

| Tool                 | Description                                           |
| -------------------- | ----------------------------------------------------- |
| **Get Doc Comments** | Get comments on a Google Doc, including replies       |
| **Create Comment**   | Add a new comment to a Google Doc                     |
| **Reply To Comment** | Reply to an existing comment thread                   |
| **Update Comment**   | Update a comment, including resolving or reopening it |

<Info>
  Comment tools and **Search Docs** require the full Google Drive scope. If you connected Google Docs before these tools were available, reconnect the integration from your [Connectors page](https://www.gumloop.com/personal/connectors) to grant the updated scope.
</Info>

<Info>
  **Create Doc** and **Update Doc** support a `content_format` parameter with three options: `plain` (default), `markdown` (CommonMark), and `html` (rich formatting including bold, italic, colors, headings, lists, tables, code blocks, and images). When using Markdown or HTML, manual style parameters are ignored.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Search for documents:**

```text theme={"dark"}
Search for Google Docs matching "quarterly report"
```

**Read a document:**

```text theme={"dark"}
Read the full content of this Google Doc including all tabs
```

**Create a document with Markdown:**

```text theme={"dark"}
Create a new Google Doc titled "Sprint Retrospective" with markdown content including headings and bullet points
```

**Create a document with HTML:**

```text theme={"dark"}
Create a new Google Doc titled "Styled Report" with HTML content including bold headings, colored text, and a bulleted list
```

**Append content:**

```text theme={"dark"}
Append a new section to this Google Doc with a summary in markdown format
```

**Find and replace:**

```text theme={"dark"}
Find all occurrences of "DRAFT" and replace with "FINAL" in this document
```

**Insert a table:**

```text theme={"dark"}
Insert a 4x3 table with a header row containing Name, Status, and Due Date
```

**List tabs:**

```text theme={"dark"}
List all tabs in this Google Doc with their IDs and titles
```

**Create a tab:**

```text theme={"dark"}
Create a new tab titled "Q3 Planning" in this Google Doc
```

**Rename a tab:**

```text theme={"dark"}
Rename the tab with ID t.abc123 to "Archived" in this Google Doc
```

**Review comments:**

```text theme={"dark"}
List all open comments on this Google Doc and summarize what needs my attention
```

**Reply to a comment:**

```text theme={"dark"}
Reply to the latest comment on this doc confirming the change was made, then resolve it
```

## Troubleshooting

| Issue                                | Solution                                                                                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right document | Use specific document titles or IDs                                                                                                        |
| Action not completing                | Check that you've authenticated and have edit access to the document                                                                       |
| Unexpected results                   | The agent may chain multiple tools (e.g., searching first, then reading content). Review the agent's reasoning to understand its approach. |
| Tool not available                   | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |
| Rich text not rendering              | Make sure to set `content_format` to `markdown` or `html` when using formatted content                                                     |
| Comment or search tools failing      | Reconnect Google Docs so the connection has the full Google Drive scope                                                                    |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find and update my meeting notes" will search first, then update the content. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Docs MCP server](https://www.gumloop.com/mcp/gdocs) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
