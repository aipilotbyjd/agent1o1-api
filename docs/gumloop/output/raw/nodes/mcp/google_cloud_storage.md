> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Cloud Storage

> Manage buckets, files, and storage operations with AI-powered cloud storage automation.

Google Cloud Storage is a scalable object storage service. The Google Cloud Storage MCP server lets you manage buckets, upload and download files, and organize your storage using natural language.

## What Can It Do?

* **Upload and download files** to and from GCS buckets
* **Create and manage buckets** with custom configurations
* **Search and browse files** with filtering and pagination
* **Copy and move files** within or across buckets

## Where to Use It

### In Agents (Recommended)

Add Google Cloud Storage as a tool to any agent. The agent can then interact with Google Cloud Storage conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Google account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Cloud Storage tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all files in my data bucket")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool               | Description                                                                                       |
| ------------------ | ------------------------------------------------------------------------------------------------- |
| **List Buckets**   | List all accessible GCS buckets with optional filtering by prefix and project                     |
| **Search Files**   | List files/objects in a bucket with filtering by prefix, date range, and pagination support       |
| **File Details**   | Get detailed metadata for a specific file including size, content type, checksums, and timestamps |
| **Download File**  | Generate signed or public download URLs for files with configurable expiration time               |
| **Bucket Details** | Get comprehensive bucket information including versioning, lifecycle rules, and IAM settings      |
| **Upload File**    | Upload files from local filesystem to GCS buckets with optional content type specification        |
| **Delete File**    | Delete files/objects from GCS buckets                                                             |
| **Copy File**      | Copy files within the same bucket or across different buckets                                     |
| **Move File**      | Move or rename files within or across buckets (copy then delete)                                  |
| **Create Bucket**  | Create new GCS buckets with configurable location and storage class                               |

## Example Prompts

Use these with your agent or in the Agent Node:

**List files:**

```text theme={"dark"}
Show me all files in my data-exports bucket
```

**Upload a file:**

```text theme={"dark"}
Upload report.csv to the reports bucket
```

**Download a file:**

```text theme={"dark"}
Generate a download link for backup.zip in the archives bucket
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Google Cloud Storage credentials and that you have the required permissions                             |
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

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Cloud Storage MCP server](https://www.gumloop.com/mcp/gcs) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
