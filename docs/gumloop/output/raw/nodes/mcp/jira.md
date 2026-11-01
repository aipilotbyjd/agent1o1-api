> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Jira

> Manage projects and issues with AI-powered development workflow automation.

Jira is Atlassian's project tracking platform for agile teams. The Jira MCP server lets you create projects, file issues, update tickets, and manage users using natural language.

## What Can It Do?

* **Create and manage projects** for teams or initiatives
* **File, update, and transition issues** without opening Jira
* **Search with JQL** to find issues across your workspace
* **Add and download file attachments** between issues and Gumloop storage
* **Post internal comments** on Jira Service Management issues
* **Write descriptions and comments in markdown** with automatic conversion to Atlassian Document Format (ADF) for rich text rendering
* **Manage users and groups** for permissions
* **Create and manage service desk requests**

## Where to Use It

### In Agents (Recommended)

Add Jira as a tool to any agent. The agent can then manage your development workflow conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Jira tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a bug in project APP")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                                                                                                        |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| **Create Project**          | Set up a new Jira project                                                                                                          |
| **Get Project**             | Retrieve project metadata                                                                                                          |
| **Update Project**          | Modify project details                                                                                                             |
| **Delete Project**          | Delete a project                                                                                                                   |
| **List Projects**           | List all accessible projects                                                                                                       |
| **Create Issue**            | Create a new issue, bug, or story                                                                                                  |
| **Get Issue**               | Retrieve issue details                                                                                                             |
| **Update Issue**            | Modify issue fields                                                                                                                |
| **Delete Issue**            | Remove an issue                                                                                                                    |
| **Transition My Issue**     | Move an issue to a new status                                                                                                      |
| **Search Issues**           | Search using JQL                                                                                                                   |
| **Comment On Issue**        | Add a comment (supports internal/private notes for JSM issues and markdown input that is converted to ADF for rich text rendering) |
| **Add Attachment**          | Add a file attachment to an issue from Gumloop storage                                                                             |
| **Download Attachment**     | Download a file attachment from a Jira issue to Gumloop storage                                                                    |
| **List Fields**             | List all available fields including custom fields                                                                                  |
| **Get Edit Metadata**       | Get editable fields and allowed values for an issue                                                                                |
| **List Issues**             | List issues by JQL query                                                                                                           |
| **Execute JQL**             | Execute a raw JQL query for advanced searching and filtering                                                                       |
| **Add User To Issue**       | Add a user as assignee, reporter, or watcher                                                                                       |
| **List Users**              | List all users                                                                                                                     |
| **Add User To Group**       | Add user to a group                                                                                                                |
| **Remove User From Group**  | Remove a user from a group                                                                                                         |
| **List Groups**             | List all user groups                                                                                                               |
| **Create Group**            | Create a new user group                                                                                                            |
| **Get Myself**              | Get info about the authenticated user                                                                                              |
| **Get My Issues**           | Get your assigned issues                                                                                                           |
| **Get My Recent Activity**  | View recently updated issues you interacted with                                                                                   |
| **Get My Permissions**      | Check what actions you can perform in a project                                                                                    |
| **List Issue Link Types**   | List available link types (Blocks, Duplicate, Relates, etc.)                                                                       |
| **Create Issue Link**       | Link two issues together (e.g., blocks, duplicates, relates to)                                                                    |
| **Delete Issue Link**       | Remove a link between issues                                                                                                       |
| **Get Issue Links**         | Get all links for a specific issue                                                                                                 |
| **List Service Desks**      | List all Jira Service Management service desks                                                                                     |
| **Get Request Types**       | Get available request types for a service desk                                                                                     |
| **Create Customer Request** | Create a new customer request in a service desk                                                                                    |
| **Get Customer Request**    | Retrieve a customer request by issue key                                                                                           |
| **List Customer Requests**  | List customer requests from service desks                                                                                          |
| **Get Request Type Fields** | Get fields required to create a specific request type                                                                              |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create an issue:**

```text theme={"dark"}
Create a bug in project APP with summary "Login button unresponsive"
```

**Find issues:**

```text theme={"dark"}
Search for high-priority issues in the CRM project
```

**Update status:**

```text theme={"dark"}
Move issue APP-234 to In Progress
```

**Add a comment:**

```text theme={"dark"}
Add comment "Verified in staging" to issue CRM-89
```

**Add an internal note (JSM):**

```text theme={"dark"}
Add an internal comment "Escalated to engineering" to service desk issue SD-45
```

**Attach a file:**

```text theme={"dark"}
Attach the file "report.pdf" from my storage to issue APP-100
```

**Download an attachment:**

```text theme={"dark"}
Download all attachments on issue APP-100 to my Gumloop storage
```

**Write a rich comment in markdown:**

```text theme={"dark"}
Comment on CRM-89 with "**Status:** verified in staging. See [runbook](https://wiki/runbook) for rollback steps."
```

**Check my work:**

```text theme={"dark"}
What issues are assigned to me?
```

**List service desks:**

```text theme={"dark"}
List all service desks and show me the available request types for each
```

**Create a service request:**

```text theme={"dark"}
Create a customer request in service desk 1 for request type 5 with summary "New laptop setup"
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Use specific issue keys (e.g., APP-234) or project keys                                                                                    |
| Action not completing            | Check that you've authenticated with Jira                                                                                                  |
| Unexpected results               | The agent may chain multiple tools (e.g., finding an issue first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Close all bugs in project WEB" will search first, then transition each. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Jira MCP server](https://www.gumloop.com/mcp/jira) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
