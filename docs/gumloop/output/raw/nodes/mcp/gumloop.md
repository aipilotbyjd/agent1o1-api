> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gumloop

> Manage workflows and agents programmatically with AI-powered automation control.

Gumloop is an AI automation platform for building agents and workflows. The Gumloop MCP server lets you manage flows, trigger runs, monitor executions, interact with agents, manage skills and artifacts, connect MCP servers, and search documentation using natural language.

## What Can It Do?

* **List and manage saved flows** and workbooks in your account
* **Trigger flow runs** with optional input parameters
* **Monitor run status** and retrieve detailed execution results
* **Create, configure, and manage agents** with full lifecycle control
* **Run agent sessions** and send follow-up messages conversationally
* **Manage skills** by creating, updating, downloading, and deleting skill packs
* **Attach and detach skills and MCP servers** on agents to change their capabilities
* **Access agent artifacts** produced during sessions
* **List teams** (workspaces) you belong to
* **Connect and interact with MCP servers** including listing tools, calling tools, reading resources, and using prompts
* **Search documentation** and get AI-powered answers
* **Access audit logs** for organization-level activity tracking
* **Export organization data** for workflows, agents, agent interactions, or credit logs and poll export status

## Where to Use It

### In Agents (Recommended)

Add Gumloop as a tool to any agent. The agent can then manage your automations conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Gumloop tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Start my daily report flow")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Workflow Management

| Tool                 | Description                                                                           |
| -------------------- | ------------------------------------------------------------------------------------- |
| **List Saved Flows** | List saved flows/items in your account for a specific user or project                 |
| **List Workbooks**   | List workbooks and their associated saved flows with nested flow information          |
| **Start Flow Run**   | Trigger a flow execution with optional input parameters                               |
| **Get Run Details**  | Retrieve detailed flow run information including state, outputs, logs, and timestamps |
| **Get Run History**  | Retrieve automation run history for workbooks or saved items with execution details   |

### Agent Management

| Tool                        | Description                                                                                                             |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **List Agents**             | List agents in your account, with optional search and workspace filtering                                               |
| **Get Agent**               | Fetch a single agent's configuration by its ID                                                                          |
| **List Agent Versions**     | List immutable versions of an agent                                                                                     |
| **Get Agent Version**       | Fetch an immutable agent version and its configuration                                                                  |
| **Create Agent**            | Create a new agent with a name, model, and optional configuration                                                       |
| **Update Agent**            | Update an existing agent's metadata or configuration                                                                    |
| **Attach Agent MCP Server** | Attach an MCP server (connector) to an agent, or update its approval mode and restricted tools if it's already attached |
| **Detach Agent MCP Server** | Detach an MCP server (connector) from an agent                                                                          |
| **List Models**             | List the model groups available to agents                                                                               |

### Agent Sessions

| Tool                     | Description                                                                              |
| ------------------------ | ---------------------------------------------------------------------------------------- |
| **Start Agent**          | Send a message to a Gumloop agent and start an asynchronous interaction                  |
| **Get Agent Status**     | Poll the status of an agent interaction and retrieve the agent's response when completed |
| **Create Agent Session** | Start a session on an agent and return the completed response                            |
| **Get Session**          | Fetch the state and result of an agent session by ID                                     |
| **Send Session Message** | Send a follow-up message to an existing agent session and return the completed response  |
| **Cancel Session**       | Cancel an in-progress agent session                                                      |
| **List Agent Sessions**  | List an agent's sessions with search, filters, sort, and pagination                      |

### Skills

| Tool                    | Description                                                                  |
| ----------------------- | ---------------------------------------------------------------------------- |
| **List Skills**         | List skills in your account, with optional search, filtering, and pagination |
| **Create Skill**        | Create a skill from one or more files stored in your workspace               |
| **Update Skill**        | Replace a skill's files with files stored in your workspace                  |
| **Delete Skill**        | Delete a skill from your account                                             |
| **Download Skill**      | Get a download URL for a skill archive                                       |
| **Attach Agent Skills** | Attach one or more existing skills to an agent                               |
| **Detach Agent Skills** | Detach one or more skills from an agent                                      |

### Artifacts

| Tool                     | Description                                                                              |
| ------------------------ | ---------------------------------------------------------------------------------------- |
| **List Agent Artifacts** | List the artifacts an agent has produced, with optional session filtering and pagination |
| **Download Artifact**    | Get a download URL for an agent artifact                                                 |

### Teams

| Tool           | Description                                                   |
| -------------- | ------------------------------------------------------------- |
| **List Teams** | List the teams (workspaces) the authenticated user belongs to |

### MCP Server Management

| Tool                      | Description                                                                |
| ------------------------- | -------------------------------------------------------------------------- |
| **List MCP Servers**      | List the MCP servers connected to your account                             |
| **Get MCP Server**        | Fetch a single connected MCP server's configuration by ID                  |
| **List MCP Server Tools** | List the tools exposed by a connected MCP server                           |
| **Call MCP Tool**         | Execute a single tool on a connected MCP server                            |
| **List MCP Resources**    | List the resources exposed by a connected MCP server                       |
| **Read MCP Resource**     | Read the contents of a resource from a connected MCP server by URI         |
| **List MCP Prompts**      | List the prompts exposed by a connected MCP server                         |
| **Get MCP Prompt**        | Get a rendered prompt from a connected MCP server, with optional arguments |

### Documentation & Admin

| Tool                     | Description                                                                                                                                                  |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Search Documentation** | Search Gumloop documentation using semantic and keyword search with filtering options                                                                        |
| **Ask Gummie**           | Ask questions and get AI-powered answers from Gumloop documentation with citations                                                                           |
| **Search Brain**         | Search your Company Brain's indexed knowledge sources for relevant content                                                                                   |
| **Get Audit Logs**       | Retrieve organization audit logs with event details and filtering by time period (admin only)                                                                |
| **Export Data**          | Create and initiate an organization data export for workflows, agents, agent interactions, or credit logs, returning a `data_export_id` to poll (admin only) |
| **Get Export Status**    | Poll a data export's status and optionally get a signed download URL for the completed CSV                                                                   |

## Example Prompts

Use these with your agent or in the Agent Node:

**List flows:**

```text theme={"dark"}
Show me all my saved flows
```

**Trigger a run:**

```text theme={"dark"}
Start the "Daily Report" flow with input parameter date set to today
```

**Check run status:**

```text theme={"dark"}
What's the status of my latest flow run?
```

**Create an agent:**

```text theme={"dark"}
Create a new agent called "Research Assistant" using the GPT-4o model
```

**Start a session:**

```text theme={"dark"}
Start a session with my Research Assistant agent and ask "Summarize the latest quarterly report"
```

**Send a follow-up message:**

```text theme={"dark"}
Send a follow-up message to my active session: "Can you break that down by region?"
```

**Manage skills:**

```text theme={"dark"}
List all skills in my account
```

**Attach a skill to an agent:**

```text theme={"dark"}
Attach my "Competitor Research" skill to the Research Assistant agent
```

**Attach an MCP server to an agent:**

```text theme={"dark"}
Attach the Gmail MCP server to my Research Assistant agent
```

**Download an artifact:**

```text theme={"dark"}
Show me the artifacts from my last agent session and download the report
```

**List connected MCP servers:**

```text theme={"dark"}
What MCP servers are connected to my account?
```

**Call an MCP tool:**

```text theme={"dark"}
Use my connected Slack MCP server to send a message to #general
```

**Search docs:**

```text theme={"dark"}
Search the Gumloop documentation for how to set up webhooks
```

**Ask Gummie:**

```text theme={"dark"}
How do I configure a Slack trigger for my workflow?
```

**View audit logs:**

```text theme={"dark"}
Show me the organization audit logs from the past week
```

**Export data:**

```text theme={"dark"}
Export all workflow runs from January 1 to March 31 and give me the download link when it's ready
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                        |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Specify flow names or project IDs explicitly                                                                                                    |
| Action not completing            | Check that you've authenticated with Gumloop                                                                                                    |
| Unexpected results               | The agent may chain multiple tools (e.g., listing flows first, then triggering a run). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Run my latest flow and show me the results" will list flows, trigger a run, and poll for completion. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Gumloop MCP server](https://www.gumloop.com/mcp/gumloop) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
