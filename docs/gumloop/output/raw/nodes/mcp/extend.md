> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Extend

> Process documents with AI-powered extraction, classification, and parsing.

Extend is a document processing platform that turns PDFs, images, and scans into structured data. The Extend MCP server lets you run processors, parse files, and manage document workflows using natural language.

## What Can It Do?

* **Run document workflows** with files or text inputs
* **Parse files** into clean, chunked content for processing
* **Extract and classify** data from documents with processors
* **Monitor runs** with filtering and lifecycle management

## Where to Use It

### In Agents (Recommended)

Add Extend as a tool to any agent. The agent can then process documents conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Extend tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Parse this PDF to markdown")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                            | Description                               |
| ------------------------------- | ----------------------------------------- |
| **List Workflow Runs**          | List runs with filtering by status        |
| **Get Workflow Run**            | Get details of a specific run             |
| **Run Workflow**                | Run a workflow with files or text         |
| **Run Processor**               | Run extraction or classification          |
| **List Processor Runs**         | List processor runs with filtering        |
| **Get Processor Run**           | Get details of a processor run            |
| **Cancel/Delete Processor Run** | Manage processor runs                     |
| **Parse File**                  | Parse files to markdown or spatial format |
| **Get Parser Run**              | Get parser run status and results         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Run a workflow:**

```text theme={"dark"}
Run the invoice processing workflow on this PDF
```

**Parse a document:**

```text theme={"dark"}
Parse this PDF to markdown and return the text chunks
```

**Extract data:**

```text theme={"dark"}
Run the contract extraction processor on this document
```

**Check run status:**

```text theme={"dark"}
Get the status of my latest workflow runs
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific workflow or processor names                                                                                            |
| Action not completing            | Check that you've authenticated and the file URL is accessible                                                                      |
| Unexpected results               | The agent may chain multiple tools (e.g., parsing first, then extracting). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Extract invoice details from this PDF" will parse the file first, then run the extractor. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Extend MCP server](https://www.gumloop.com/mcp/extend) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
