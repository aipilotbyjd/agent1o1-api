> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Reducto

> Parse, extract, split, and edit documents with AI-powered document processing automation.

Reducto is a document processing API that converts complex documents — PDFs, scanned files, and more — into structured, machine-readable content. The Reducto MCP server lets you parse documents, extract structured data, split documents into sections, and fill forms using natural language.

## What Can It Do?

* **Parse documents** — convert PDFs and other files into structured text, tables, and figures
* **Extract structured data** — pull specific fields from documents using a JSON schema
* **Split documents** — divide a document into logical sections based on descriptions
* **Edit documents** — fill forms or modify documents with natural language instructions
* **Manage jobs** — track, monitor, and cancel document processing jobs

## Where to Use It

### In Agents (Recommended)

Add Reducto as a tool to any agent. The agent can then process and extract data from documents conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Reducto API key

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Reducto tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Extract invoice data from a PDF")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                  | Description                                                                  |
| --------------------- | ---------------------------------------------------------------------------- |
| **Upload Document**   | Upload a file from Gumloop storage to Reducto for processing                 |
| **Download Document** | Download a Reducto result file back to Gumloop storage                       |
| **Parse Document**    | Parse a document into structured content including text, tables, and figures |
| **Extract Data**      | Extract structured data from a document using a JSON schema                  |
| **Split Document**    | Split a document into logical sections based on descriptions                 |
| **Edit Document**     | Fill forms or modify a document with natural language instructions           |
| **List Jobs**         | List processing jobs with pagination support                                 |
| **Get Job Status**    | Get the status and result of a processing job                                |
| **Cancel Job**        | Cancel a running processing job                                              |

## Example Prompts

Use these with your agent or in the Agent Node:

**Parse a document:**

```text theme={"dark"}
Upload and parse the contract PDF, then give me a summary of the key terms
```

**Extract structured data:**

```text theme={"dark"}
Extract the invoice number, date, line items, and total from this PDF invoice
```

**Split a document:**

```text theme={"dark"}
Split this 50-page report into sections: Executive Summary, Methodology, Results, and Appendix
```

**Fill a form:**

```text theme={"dark"}
Fill in the application form with the applicant's name, address, and date of birth from the provided data
```

**Check job status:**

```text theme={"dark"}
What's the status of my document processing jobs?
```

## Troubleshooting

| Issue                     | Solution                                                                                                            |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Upload failing            | Ensure the file exists in Gumloop storage before uploading                                                          |
| Extraction missing fields | Refine your JSON schema to be more specific about the fields you need                                               |
| Job taking too long       | Use `Get Job Status` to monitor progress; large documents may take longer                                           |
| Tool not available        | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  For best extraction results, provide a detailed JSON schema that describes exactly what fields you need and their expected data types. The more specific your schema, the more accurate the extraction.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Reducto MCP server](https://www.gumloop.com/mcp/reducto) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
