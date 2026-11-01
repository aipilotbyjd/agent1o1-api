> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# GitHub

> Automate repositories, issues, PRs, and projects with AI-powered development workflows.

GitHub is the world's leading code hosting platform for version control and collaboration. The GitHub MCP server lets you manage repositories, issues, pull requests, and projects using natural language.

## What Can It Do?

* **Create and manage repositories** and branches on demand
* **Find and filter** issues, PRs, and commits for reporting
* **Organize projects** with fields and items
* **Inspect CI/CD logs** from GitHub Actions workflows and jobs
* **Automate releases** and collaborator management

## Where to Use It

### In Agents (Recommended)

Add GitHub as a tool to any agent. The agent can then interact with your repositories conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with GitHub tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List open issues in my repo")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                                           | Description                                                                            |
| ---------------------------------------------- | -------------------------------------------------------------------------------------- |
| **Create/List/Search Repositories**            | Manage repositories                                                                    |
| **Get Contents**                               | Retrieve file or directory contents                                                    |
| **Create or Update File**                      | Create or update one or more files in a repository in a single commit                  |
| **Delete Files**                               | Delete one or more files from a repository in a single commit                          |
| **List/Get Commits**                           | Access commit history                                                                  |
| **List/Search Issues**                         | Find and filter issues                                                                 |
| **Create/Update Issue**                        | Manage issues                                                                          |
| **Add Comment To Issue**                       | Comment on issues                                                                      |
| **List/Create Branches**                       | Manage branches                                                                        |
| **List/Get/Create/Update/Merge Pull Requests** | Manage PRs                                                                             |
| **Add Comment to Pull Request**                | Comment on PRs                                                                         |
| **List Pull Request Files**                    | View changed files in a PR                                                             |
| **List Pull Request Review Comments**          | List inline review comments on a PR (code-level comments on specific lines/files)      |
| **Request Pull Request Reviewers**             | Request reviews from users or teams                                                    |
| **List/Get Projects**                          | Access project boards                                                                  |
| **List/Create/Update/Delete Project Fields**   | Manage project fields                                                                  |
| **List/Add/Update/Delete Project Items**       | Manage project items                                                                   |
| **List/Get Tags and Releases**                 | Access releases                                                                        |
| **List/Add/Remove Collaborators**              | Manage access                                                                          |
| **List Labels, Milestones, Teams**             | Organization tools                                                                     |
| **List Deployments and Workflows**             | CI/CD access                                                                           |
| **List Workflow Runs**                         | List GitHub Actions workflow runs with optional filters (branch, status, event, actor) |
| **List Workflow Runs for Workflow**            | List GitHub Actions workflow runs for a specific workflow definition                   |
| **Get Workflow Run**                           | Get a specific GitHub Actions workflow run by its ID                                   |
| **List Workflow Jobs**                         | List jobs for a specific workflow run                                                  |
| **Get Job Logs**                               | Get plain-text logs for a workflow job, or download them to storage                    |
| **Get Workflow Run Logs**                      | Download the full log archive for a workflow run                                       |
| **Search Code**                                | Search across repositories                                                             |
| **List Vulnerability Alerts**                  | List Dependabot vulnerability alerts for a repository                                  |
| **Create Gist**                                | Create a new GitHub gist (public or secret) with one or more files                     |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search repositories:**

```text theme={"dark"}
Find repositories about "LLM chatbot" with more than 5000 stars
```

**Manage issues:**

```text theme={"dark"}
Create an issue in octocat/Hello-World titled "Bug: login fails"
```

**List pull requests:**

```text theme={"dark"}
Show me all open PRs in facebook/react
```

**Merge a pull request:**

```text theme={"dark"}
Merge PR #42 in my-org/my-repo using squash
```

**Request reviewers:**

```text theme={"dark"}
Request a review from @octocat on PR #15 in my-org/my-repo
```

**Get commit details:**

```text theme={"dark"}
Get the details of the latest commit in my repo
```

**Create or update files:**

```text theme={"dark"}
Create a README.md file in my-org/my-repo with the content "# My Project"
```

**Search code:**

```text theme={"dark"}
Search for "def get_queryset" in the Django repository
```

**Create a gist:**

```text theme={"dark"}
Create a secret gist called "debug notes" with a file notes.md containing my troubleshooting steps
```

**List vulnerability alerts:**

```text theme={"dark"}
List open Dependabot vulnerability alerts in my-org/my-repo
```

**View CI logs:**

```text theme={"dark"}
Show me the logs from the latest failed CI job in my-org/my-repo
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                       |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific repository names in owner/repo format                                                                                             |
| Action not completing            | Check that you've authenticated and have permissions for the repository                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., finding a repo first, then listing issues). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                            |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Create an issue in the marketing repo" will find the repository first, then create the issue. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [GitHub MCP server](https://www.gumloop.com/mcp/github) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
