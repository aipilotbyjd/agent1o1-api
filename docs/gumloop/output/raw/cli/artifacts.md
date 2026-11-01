> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Artifacts

> List and download files produced by agents.

`gumloop artifacts` exposes the [files](/core-concepts/agent_artifacts) an agent has produced — reports, generated docs, exported data, etc. Artifacts are always scoped to an agent.

## List artifacts

```bash theme={"dark"}
gumloop artifacts list agent_abc
gumloop artifacts list agent_abc --session session_xyz --limit 50
```

Prints `ID`, `FILENAME`, `VERSION`, `SESSION`, and `CREATED`. If the response is paginated, the next cursor is printed at the bottom.

<Tip>Grab the agent ID from `gumloop agents list` and the session ID from `gumloop sessions get <id>` or the URL of the session in the Gumloop hub.</Tip>

| Flag        | Description                                             |
| ----------- | ------------------------------------------------------- |
| `--session` | Filter to artifacts produced inside a specific session. |
| `--limit`   | Maximum number of artifacts to return.                  |
| `--cursor`  | Pagination cursor from a previous list call.            |
| `--json`    | Print the raw response payload.                         |

## Download an artifact

```bash theme={"dark"}
gumloop artifacts download artifact_abc
```

By default the artifact is written to the current directory under its original filename. Use `-o` to change the destination:

| Flag             | Description                                                |
| ---------------- | ---------------------------------------------------------- |
| `-o`, `--output` | File or directory to write to. Use `-` to write to stdout. |
| `--version-id`   | Download a specific artifact version.                      |
| `--json`         | Print download metadata as JSON (path + bytes).            |

```bash theme={"dark"}
gumloop artifacts download artifact_abc -o ./downloads/
gumloop artifacts download artifact_abc -o -                  # stream to stdout
gumloop artifacts download artifact_abc --version-id av_xyz
```
