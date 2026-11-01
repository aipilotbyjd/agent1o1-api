> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# MCP Servers

> Explore the MCP servers available to your account and execute their tools.

An [MCP server](/nodes/mcp/custom_mcp_servers) is an integration — Gmail, Slack, Linear, Notion, your own custom one — that exposes a set of tools your agents (or you, directly) can call. `gumloop mcp` lets you list the servers connected to your account, browse their tools, and invoke them on demand.

## List servers

```bash theme={"dark"}
gumloop mcp list
```

Prints `SERVER_ID`, `NAME`, `TYPE`, `STATUS`, `TOOLS` (tool count), and `AUTH_URL`. If a server's `STATUS` is anything other than `connected`, the `AUTH_URL` column has a one-click link to finish connecting it — open it in your browser, approve, and you're done.

## Inspect a server

```bash theme={"dark"}
gumloop mcp get gmail
```

Shows the server's full configuration — `server_id`, `type`, `status`, `tool_count`, `description`, the Gumloop auth URL, and the underlying MCP endpoint.

## List the tools a server exposes

```bash theme={"dark"}
gumloop mcp tools gmail
```

Returns a table of `NAME`, `TOOL_CALL_ID`, and `DESCRIPTION`. If the server isn't connected yet, the CLI prints the auth URL you need to open instead.

<Note>You pass the tool's `NAME` (not `TOOL_CALL_ID`) to `gumloop mcp call`. `TOOL_CALL_ID` is the internal identifier used when an agent invokes the tool through a workflow.</Note>

## Call a tool

```bash theme={"dark"}
gumloop mcp call gmail list_emails --args-json '{"max_results": 5}'
```

The arguments can come from three places (pick one):

| Flag          | Description                                                                                                       |
| ------------- | ----------------------------------------------------------------------------------------------------------------- |
| `--args-json` | Inline JSON object.                                                                                               |
| `--args-file` | Path to a JSON file.                                                                                              |
| `--args -`    | Read JSON from stdin (use the literal `-`).                                                                       |
| `--ref`       | Optional client-side ref string. The server echoes it back on each result so you can match responses to requests. |
| `--json`      | Print the raw response payload.                                                                                   |

Examples:

```bash theme={"dark"}
gumloop mcp call gmail send_email --args-file ./email.json
```

```bash theme={"dark"}
cat email.json | gumloop mcp call gmail send_email --args -
```

The default text output groups results by tool, prints the status and any error, then the content of each result. MCP execution failures are reported per result, so check each printed `status` or use `--json` to pipe the structured response into another tool.
