> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Workday

> Download custom reports from Workday and access employee data with AI-powered automation.

Workday is a leading enterprise cloud platform for human capital management, financial management, and planning. The Workday MCP server lets you download custom reports from Workday using natural language.

## What Can It Do?

* **Download custom reports** from Workday using report URLs
* **Export data** to your workspace for further processing

<Info>
  The Workday integration downloads report files from Workday. To parse the file contents, perform data analysis, or do any operations on the downloaded data, add the [Code Sandbox tool](/core-concepts/agent_sandbox_and_secrets) to your agent. This enables your agent to read and process the downloaded files using Python.
</Info>

## Where to Use It

### In Agents (Recommended)

Add Workday as a tool to any agent. The agent can then fetch reports conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Enter your Workday credentials

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Workday tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Download the employee roster report")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

Workday uses Basic Authentication with your Workday username and password. When connecting your Workday account, you'll need to provide:

* **Username**: Your Workday username (typically your email or employee ID)
* **Password**: Your Workday password

<Warning>
  Make sure your Workday account has the necessary permissions to access the reports you want to download. Contact your Workday administrator if you need additional access.
</Warning>

## Available Tools

| Tool           | Description                                                     |
| -------------- | --------------------------------------------------------------- |
| **Get Report** | Download a custom report file from Workday using the report URL |

<Tip>
  The Get Report tool downloads the report file to your workspace. To parse, analyze, or transform the data in the downloaded file, enable the [Code Sandbox tool](/core-concepts/agent_sandbox_and_secrets) for your agent. This allows the agent to use Python to read JSON, CSV, or other file formats and perform operations on the data.
</Tip>

## Example Prompts

Use these with your agent or in the Agent Node:

**Download a report:**

```text theme={"dark"}
Download the employee roster report from https://wd5.myworkday.com/company/d/task/report.htmld
```

**Save report to workspace:**

```text theme={"dark"}
Fetch the Q4 headcount report and save it to my workspace as headcount_q4.json
```

## Report URL Format

Workday custom reports are accessed via URLs that follow this pattern:

```text theme={"dark"}
https://{tenant}.myworkday.com/{company}/d/task/{report_path}
```

You can find your report URLs in Workday by:

1. Navigating to the report you want to access
2. Looking for the "Web Service" or "REST API" URL in the report settings
3. Copying the full URL to use with this integration

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Workday username and password are correct                                                               |
| Report not found      | Check that the report URL is correct and accessible                                                                 |
| Permission denied     | Ensure your Workday account has access to the requested report                                                      |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |
| Timeout errors        | Large reports may take longer to download - try again or contact support                                            |

<Tip>
  If you're having trouble accessing reports, work with your Workday administrator to ensure your account has the "Web Service" permission for the reports you need.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Workday MCP server](https://www.gumloop.com/mcp/workday) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
