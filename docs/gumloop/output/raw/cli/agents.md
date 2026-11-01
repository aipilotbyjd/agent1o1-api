> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Agents

> List, inspect, create, and update agents from the terminal.

`gumloop agents` lets you list, inspect, create, and update your [agents](/core-concepts/agents) without leaving the terminal. Every command accepts `--json` to print the raw response payload.

## List agents

```bash theme={"dark"}
gumloop agents list
gumloop agents list --search support --limit 50
```

Returns a tab-separated table with `ID`, `NAME`, `MODEL`, `TEAM`, and `ACTIVE`. If the response is paginated, the next cursor is printed at the bottom — pass it back with `--cursor`.

| Flag       | Description                                  |
| ---------- | -------------------------------------------- |
| `--search` | Filter agents by name or description.        |
| `--limit`  | Maximum number of agents to return.          |
| `--cursor` | Pagination cursor from a previous list call. |
| `--json`   | Print the raw response payload.              |

The `--team-id` global flag scopes the listing to a single team.

## Get an agent

```bash theme={"dark"}
gumloop agents get agent_abc
```

Prints the agent's name as a header followed by `id`, `model_name`, `team_id`, `is_active`, `folder_id`, `description`, `created_at`, and the system prompt (if set).

<Tip>Grab the agent ID from the first column of `gumloop agents list`.</Tip>

## List agent versions

```bash theme={"dark"}
gumloop agents versions agent_abc
gumloop agents versions agent_abc --limit 50 --json
```

Returns a tab-separated table with `ID`, `VERSION`, `NAME`, `DEPLOYED`, and `CREATED`, newest first. If the response is paginated, the next cursor is printed at the bottom — pass it back with `--cursor`.

| Flag       | Description                                      |
| ---------- | ------------------------------------------------ |
| `--limit`  | Maximum number of versions to return.            |
| `--cursor` | Pagination cursor from a previous versions call. |
| `--json`   | Print the raw response payload.                  |

Versions are read-only snapshots of the agent's configuration; the CLI cannot deploy or restore one.

## Export an agent version

```bash theme={"dark"}
gumloop agents export agent_abc gv_a1b2c3d4
gumloop agents export agent_abc gv_a1b2c3d4 --output agent-version.json
```

Writes one version as JSON: the version's full configuration (`composition`) plus the structured `changes` against the version before it. `changes` is `null` for an agent's first version.

| Flag             | Description                                         |
| ---------------- | --------------------------------------------------- |
| `-o`, `--output` | File to write. Omit or pass `-` to print to stdout. |

<Tip>Grab the version ID from the first column of `gumloop agents versions <id>`.</Tip>

## Create an agent

```bash theme={"dark"}
gumloop agents create --name "Support bot" --model auto
```

| Flag                   | Required | Description                                                                             |
| ---------------------- | -------- | --------------------------------------------------------------------------------------- |
| `--name`               | yes      | Display name for the new agent.                                                         |
| `--model`              | yes      | Model name (for example `auto`, `anthropic/claude-sonnet-4`).                           |
| `--description`        |          | Short description.                                                                      |
| `--system-prompt`      |          | Inline system prompt text.                                                              |
| `--system-prompt-file` |          | Path to a file containing the system prompt. Mutually exclusive with `--system-prompt`. |
| `--tools-json`         |          | Inline JSON array of tool config objects.                                               |
| `--tools-file`         |          | Path to a JSON file containing the tools array. Mutually exclusive with `--tools-json`. |
| `--json`               |          | Print the raw response payload.                                                         |

Pass the system prompt from a file:

```bash theme={"dark"}
gumloop agents create --name "Sales research" --model auto \
  --system-prompt-file ./prompts/sales.md
```

Attach tools (each entry in the array is one tool config; the shape varies by type):

```bash theme={"dark"}
gumloop agents create --name "Email reader" --model auto \
  --tools-json '[{"type":"gumcp_server","server":"gmail"}]'
```

<Tip>To see the exact tool config shape an agent uses, run `gumloop agents get <id> --json` on an existing agent and copy the `tools` array out of the response.</Tip>

## Update an agent

```bash theme={"dark"}
gumloop agents update agent_abc --name "Better bot"
gumloop agents update agent_abc --system-prompt-file new-prompt.md
```

Only the flags you pass are changed; everything else is left untouched. The flag surface matches `agents create` and adds:

| Flag                         | Description                                                                           |
| ---------------------------- | ------------------------------------------------------------------------------------- |
| `--is-active` / `--inactive` | Set the agent's active state. `--inactive` retires the agent — see the warning below. |

<Warning>
  `--inactive` (and `is_active: false` on the API) is **not** a pause switch. It retires the agent: it stops appearing in `gumloop agents list`, and `gumloop agents get`/`update` return a `404` afterwards, so you cannot turn it back on yourself. Contact [support@gumloop.com](mailto:support@gumloop.com) if you need a retired agent restored.

  To stop an agent from running on its own while keeping it fully reachable, disable its triggers instead — open the agent's **Triggers** section in the app and use the three-dot menu on each trigger, or ask the agent to pause the schedule in chat. See [Managing active triggers](/core-concepts/agent_triggers#managing-active-triggers).
</Warning>
