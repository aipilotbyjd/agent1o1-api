> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# MCP Server

> Connect to the Gumloop MCP server from Claude, Cursor, VS Code, and other MCP-compatible clients.

Gumloop exposes a remote [Model Context Protocol (MCP)](https://www.gumloop.com/blog/what-is-mcp-model-context-protocol-a-simple-guide) server that lets any compatible AI client manage your workflows, agents, sessions, skills, and more.

Connect to the Gumloop MCP server natively in Claude, Cursor, and other clients, or use [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) for clients that don't yet support remote MCP.

```text theme={"dark"}
https://mcp.gumloop.com/gumloop/mcp
```

<Info>
  You'll need a Gumloop API key. Get one from [Connectors page](https://www.gumloop.com/personal/connectors). The server handles OAuth automatically: you'll be prompted to sign in and authorize access the first time you connect.
</Info>

## Setup

### Claude

Navigate to **Settings > Connectors** and add a new MCP integration with the URL:

```text theme={"dark"}
https://mcp.gumloop.com/gumloop/mcp
```

### Cursor

1. Open Cursor Settings (Cmd/Ctrl + Shift + J)
2. Go to **MCP** and click **Add new MCP server**
3. Select **Type: SSE** and enter the server URL:

```text theme={"dark"}
https://mcp.gumloop.com/gumloop/mcp
```

### VS Code

Add the following to your `.vscode/mcp.json`:

```json theme={"dark"}
{
  "mcpServers": {
    "gumloop": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://mcp.gumloop.com/gumloop/mcp"]
    }
  }
}
```

### Windsurf

1. Open Windsurf settings (Cmd/Ctrl + ,)
2. Scroll to **Cascade > MCP servers**
3. Select **Add Server > Add custom server**
4. Add:

```json theme={"dark"}
{
  "mcpServers": {
    "gumloop": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://mcp.gumloop.com/gumloop/mcp"]
    }
  }
}
```

### Gumloop CLI

You can also explore and call tools from your terminal:

```bash theme={"dark"}
gumloop mcp list                                    # List connected servers
gumloop mcp tools gumloop                           # Browse available tools
gumloop mcp call gumloop list_agents --args-json '{}' # Call a tool
```

See the [CLI MCP docs](/cli/mcp) for the full reference.

## Available Tools

The Gumloop MCP server exposes 45 tools across these categories:

### Agent Management

| Tool                      | Description                                                                                                |
| ------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `list_agents`             | List agents in your account, with optional search and workspace filtering                                  |
| `get_agent`               | Fetch a single agent's configuration by its ID                                                             |
| `create_agent`            | Create a new agent with a name, model, and optional configuration (including `skill_ids` to attach skills) |
| `update_agent`            | Update an existing agent's metadata or configuration                                                       |
| `attach_agent_skills`     | Attach one or more existing skills to a Gumloop agent                                                      |
| `detach_agent_skills`     | Detach one or more skills from a Gumloop agent                                                             |
| `attach_agent_mcp_server` | Attach an MCP server (connector) to an agent, or update its configuration if already attached              |
| `detach_agent_mcp_server` | Detach an MCP server (connector) from an agent                                                             |
| `list_models`             | List the model groups available to agents                                                                  |
| `list_agent_versions`     | List the immutable versions of an agent                                                                    |
| `get_agent_version`       | Fetch an immutable agent version and its configuration                                                     |

### Agent Sessions

| Tool                   | Description                                                                       |
| ---------------------- | --------------------------------------------------------------------------------- |
| `start_agent`          | Send a message to an agent and start an asynchronous interaction                  |
| `get_agent_status`     | Poll the status of an agent interaction and retrieve the response when completed  |
| `create_agent_session` | Start a session on an agent and return the completed response                     |
| `get_session`          | Fetch the state and result of an agent session by ID                              |
| `send_session_message` | Send a follow-up message to an existing session and return the completed response |
| `cancel_session`       | Cancel an in-progress agent session                                               |
| `list_agent_sessions`  | List an agent's sessions, with search, filters, sort, and pagination              |

### Skills

| Tool             | Description                                                                  |
| ---------------- | ---------------------------------------------------------------------------- |
| `list_skills`    | List skills in your account, with optional search, filtering, and pagination |
| `create_skill`   | Create a skill from one or more files stored in your workspace               |
| `update_skill`   | Replace a skill's files with files stored in your workspace                  |
| `delete_skill`   | Delete a skill from your account                                             |
| `download_skill` | Get a download URL for a skill archive                                       |

### Artifacts

| Tool                   | Description                                                                              |
| ---------------------- | ---------------------------------------------------------------------------------------- |
| `list_agent_artifacts` | List the artifacts an agent has produced, with optional session filtering and pagination |
| `download_artifact`    | Get a download URL for an agent artifact                                                 |

### Teams

| Tool         | Description                                                   |
| ------------ | ------------------------------------------------------------- |
| `list_teams` | List the teams (workspaces) the authenticated user belongs to |

### MCP Server Management

| Tool                    | Description                                                                |
| ----------------------- | -------------------------------------------------------------------------- |
| `list_mcp_servers`      | List the MCP servers connected to your account                             |
| `get_mcp_server`        | Fetch a single connected MCP server's configuration by ID                  |
| `list_mcp_server_tools` | List the tools exposed by a connected MCP server                           |
| `call_mcp_tool`         | Execute a single tool on a connected MCP server                            |
| `list_mcp_resources`    | List the resources exposed by a connected MCP server                       |
| `read_mcp_resource`     | Read the contents of a resource from a connected MCP server by URI         |
| `list_mcp_prompts`      | List the prompts exposed by a connected MCP server                         |
| `get_mcp_prompt`        | Get a rendered prompt from a connected MCP server, with optional arguments |

### Company Brain

| Tool           | Description                                                                |
| -------------- | -------------------------------------------------------------------------- |
| `search_brain` | Search your Company Brain's indexed knowledge sources for relevant content |

### Documentation & Admin

| Tool                   | Description                                                                                   |
| ---------------------- | --------------------------------------------------------------------------------------------- |
| `search_documentation` | Search Gumloop documentation using semantic and keyword search with filtering options         |
| `ask_gummie`           | Ask questions and get AI-powered answers from Gumloop documentation with citations            |
| `get_audit_logs`       | Retrieve organization audit logs with event details and filtering by time period (admin only) |

### Data Exports

| Tool                | Description                                                                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `export_data`       | Create an organization data export for workflows, agents, agent interactions, credit logs, or Gumstack usage over a date range (admin only) |
| `get_export_status` | Poll an export's status and get a signed download URL for the completed CSV                                                                 |

Use these two together to pull your organization's agent and usage data into an MCP client: call `export_data` with a `data_type` (`workflows`, `agents`, `agent_interactions`, `credit_logs`, or `gumstack`), a date range, and the columns you want, then poll `get_export_status` with the returned `data_export_id` until it reports `COMPLETED` and request the download URL.

<Note>
  The aggregated charts on the Organization Insights dashboard (overview totals, model usage, leaderboards, skill adoption, artifact analytics) aren't available over MCP or the REST API. Use data exports for the underlying rows.
</Note>

### Workflow Management

| Tool               | Description                                                                           |
| ------------------ | ------------------------------------------------------------------------------------- |
| `list_saved_flows` | List saved flows/items in your account for a specific user or project                 |
| `list_workbooks`   | List workbooks and their associated saved flows with nested flow information          |
| `start_flow_run`   | Start/trigger flow execution with optional input parameters                           |
| `get_run_details`  | Retrieve detailed flow run information including state, outputs, logs, and timestamps |
| `get_run_history`  | Retrieve automation run history for workbooks or saved items with execution details   |

## Example Prompts

Once connected, try these with your AI client:

```text theme={"dark"}
Show me all my saved flows
```

```text theme={"dark"}
Start the "Daily Report" flow with input parameter date set to today
```

```text theme={"dark"}
Create a new agent called "Research Assistant" using the GPT-4o model
```

```text theme={"dark"}
Start a session with my Research Assistant agent and ask it to summarize the latest quarterly report
```

```text theme={"dark"}
What MCP servers are connected to my account?
```

```text theme={"dark"}
Search my Company Brain for our refund policy and summarize what it says
```

```text theme={"dark"}
Export my organization's agent interactions for the last 30 days and tell me when the CSV is ready
```

## Related Resources

<CardGroup cols={2}>
  <Card title="Gumloop MCP Integration" icon="robot" href="/nodes/mcp/gumloop">
    Full tool reference and usage examples.
  </Card>

  <Card title="API Reference" icon="code" href="/api-reference/mcp/list-servers">
    Programmatically manage MCP servers and call tools via the REST API.
  </Card>

  <Card title="CLI Reference" icon="terminal" href="/cli/mcp">
    Explore and call MCP tools from your terminal.
  </Card>

  <Card title="Custom MCP Servers" icon="plug" href="/nodes/mcp/custom_mcp_servers">
    Connect your own MCP servers to Gumloop.
  </Card>
</CardGroup>

## Need Help?

* [Agents documentation](/core-concepts/agents) for using MCP tools with agents
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Browse our [Knowledge Base](https://support.gumloop.com/) for common questions
