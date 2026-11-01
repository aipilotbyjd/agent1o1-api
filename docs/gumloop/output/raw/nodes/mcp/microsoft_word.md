> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Microsoft Word

> Manage Word documents with AI-powered document automation.

Microsoft Word is part of Microsoft 365 for document creation and editing. The Microsoft Word MCP server lets you list, search, read, edit, and download Word documents from OneDrive using natural language.

## What Can It Do?

* **List and search documents** across your OneDrive
* **Read document contents** for analysis or processing
* **Create and edit documents** with new content
* **Generate download links** for sharing

## Where to Use It

### In Agents (Recommended)

Add Microsoft Word as a tool to any agent. The agent can then manage your documents conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Microsoft Word tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Read the contents of the project charter")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                  | Description                       |
| --------------------- | --------------------------------- |
| **List Documents**    | List Word documents from OneDrive |
| **Create Document**   | Create a new Word document        |
| **Read Document**     | Get the full text of a document   |
| **Write Document**    | Append text to a document         |
| **Search Documents**  | Find documents by keyword         |
| **Download Document** | Get a download URL                |
| **Delete Document**   | Remove a document                 |

## Example Prompts

Use these with your agent or in the Agent Node:

**List documents:**

```text theme={"dark"}
Show me all Word documents in the Proposals folder
```

**Read content:**

```text theme={"dark"}
Read the contents of the Annual Report 2024 document
```

**Create a document:**

```text theme={"dark"}
Create a new document called "Meeting Notes" with the text "Q3 Planning Session"
```

**Search documents:**

```text theme={"dark"}
Find documents containing "budget forecast"
```

**Get download link:**

```text theme={"dark"}
Generate a download link for the Contract Final Version document
```

## Troubleshooting

| Issue                            | Solution                                                                                                                           |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use exact document names or folder paths                                                                                           |
| Action not completing            | Check that you've authenticated with Microsoft 365                                                                                 |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then reading). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Read the latest proposal" will search for it first, then read the contents. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Microsoft Word MCP server](https://www.gumloop.com/mcp/word) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
