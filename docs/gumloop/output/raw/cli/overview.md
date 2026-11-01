> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gumloop for Terminal

> Drive Gumloop agents, sessions, chat completions, MCP servers, Company Brain, skills, and artifacts from your shell.

`gumloop` is the command line for Gumloop. Sign in once, then drive your agents, sessions, chat completions, MCP integrations, Company Brain, skills, and artifacts from the terminal. Every command has a `--json` mode so you can pipe results into scripts, cron jobs, or any other tool you already use.

## Install

```bash theme={"dark"}
curl -fsSL https://gumloop.com/cli/install.sh | sh
```

<Accordion title="Or paste this prompt into your coding agent and it sets everything up for you">
  The prompt is self-contained — the agent installs the CLI, sorts out authentication with you, and installs the [Gumloop CLI skill](#use-with-coding-agents) on its own.

  ````markdown theme={"dark"}
  # Set up the Gumloop CLI

  Set up the Gumloop CLI in this environment so you can operate Gumloop (run agents,
  call MCP integration tools, search Company Brain). Follow these steps in order.

  ## Step 1: Install the CLI

  Skip this step if `gumloop --version` already works. Otherwise install it
  (macOS, Linux, or WSL only — it does not run on native Windows):

  ```bash
  curl -fsSL https://gumloop.com/cli/install.sh | sh
  ```

  On an interactive machine the installer ends by offering `gumloop login` — tell me
  to accept it. On a headless machine or CI, ask me for a Gumloop API key
  (from https://www.gumloop.com/personal/connectors) and my user ID
  (from https://www.gumloop.com/settings/profile/general), then export both as
  `GUMLOOP_API_KEY` and `GUMLOOP_USER_ID`. Never print or log the API key.

  ## Step 2: Verify authentication

  ```bash
  gumloop agents list
  ```

  If this fails with an authentication error, fix the credentials from Step 1
  before continuing.

  ## Step 3: Install the Gumloop CLI skill

  ```bash
  gumloop plugin install gumloop
  ```

  This installs a `gumloop-cli` skill with the full command reference into your
  skill directories. Read it before using unfamiliar commands.

  ## Step 4: Learn the surface

  - List what is available: `gumloop agents list`, `gumloop mcp list`.
  - Always pass `--json` when you parse output.
  - Use `gumloop <command> --help` before using a command; do not invent flags.
  - Discover MCP tools with `gumloop mcp tools <server> --json` instead of guessing
    tool names.

  When all steps pass, tell me what agents and MCP servers you found and ask what
  I want to run first.
  ````
</Accordion>

The installer is fully self-contained under `~/.gumloop` — it ships its own Python, never touches your system Python, and needs no sudo. Verify the install:

```bash theme={"dark"}
gumloop --version
```

Update any time with:

```bash theme={"dark"}
gumloop update
```

Using Gumloop as a library instead? See the [Python SDK](/api-reference/sdk/python).

<Warning>
  **Windows is not supported.** The Gumloop CLI runs on **macOS** and **Linux** only. This applies to every command — including `gumloop login` and syncing skills or artifacts — because credential storage and the OAuth callback server rely on POSIX-only paths.

  Windows users have two options:

  * **Use [WSL](https://learn.microsoft.com/windows/wsl/install)** (Windows Subsystem for Linux) and install the CLI inside your Linux distribution. This is the recommended path.
  * **Use the Python SDK instead** — it works natively on Windows. Import `from gumloop import Gumloop` directly. See the [Python SDK reference](/api-reference/sdk/python).
</Warning>

### Linux prerequisites

The CLI stores credentials in your OS keychain. macOS Keychain is always available, but on Linux you need one of:

```bash theme={"dark"}
sudo apt install gnome-keyring libsecret-1-0
sudo apt install kwalletmanager
```

On a headless box without a keychain, skip `gumloop login` entirely and pass credentials per invocation via [environment variables](/cli/authentication#environment-variables).

## Sign in

```bash theme={"dark"}
gumloop login
```

Pick **OAuth (browser)** at the prompt. The CLI opens your browser, you click "Allow" on the Gumloop consent screen, and you're signed in. Tokens are stored in your OS keychain and the CLI refreshes them for you when they expire.

Prefer an API key, or running on a headless box? See [Authentication](/cli/authentication).

## Your first command

List the agents you can see:

```bash theme={"dark"}
gumloop agents list
```

```text theme={"dark"}
ID                NAME                MODEL                       TEAM            ACTIVE
agent_g6f1a2b3    Sales research      anthropic/claude-sonnet-4   team_4f8c92ab   yes
agent_h7e9c1d4    Support triage      openai/gpt-5                team_4f8c92ab   yes
```

Grab an ID from that table and start a chat:

```bash theme={"dark"}
gumloop sessions create agent_g6f1a2b3 --input "Summarize this week's pipeline."
```

That's it. From here, every command works the same way — `gumloop <thing> <action>`, with `--help` on anything to see all the flags.

## Use with coding agents

Now that you're set up, your coding agent (Claude Code, Cursor, Codex, ...) can drive Gumloop for you. One command installs the `gumloop-cli` skill — setup, every command, and the sharp edges — into each coding agent detected on your machine:

```bash theme={"dark"}
gumloop plugin install gumloop
```

Restart your agent (or start a new session) and it picks the skill up whenever a task involves Gumloop. Haven't installed the CLI yet? The [setup prompt in Install](#install) has your agent do all of this for you.

<Note>
  The skill ships inside the CLI as an [Agent Plugin](https://agent-plugins.org). To write the raw plugin package (`plugin.json` + `skills/`) somewhere explicit — for a plugin-aware client or a project checkout — use `gumloop plugin install gumloop --dir <path>`.
</Note>

## Commands

| Command                                                                               | What it does                                                                       |
| ------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| [`gumloop login`](/cli/authentication#login) / [`logout`](/cli/authentication#logout) | Manage stored credentials                                                          |
| [`gumloop agents`](/cli/agents)                                                       | List, inspect, create, and update agents, and export agent versions                |
| [`gumloop sessions`](/cli/sessions)                                                   | Create, inspect, send messages to, and cancel agent sessions                       |
| [`gumloop chat`](/cli/chat)                                                           | Send chat completions to any supported model (unary, streaming, structured, image) |
| [`gumloop mcp`](/cli/mcp)                                                             | Explore connected MCP servers and execute their tools                              |
| [`gumloop brain`](/cli/brain)                                                         | Search your Company Brain's indexed knowledge sources                              |
| [`gumloop skills`](/cli/skills)                                                       | List, upload, update, and download skill files                                     |
| [`gumloop artifacts`](/cli/artifacts)                                                 | List and download artifacts produced by agents                                     |

## Global flags

These work on every command:

| Flag              | Env var            | Description                                                         |
| ----------------- | ------------------ | ------------------------------------------------------------------- |
| `--team-id`       | `GUMLOOP_TEAM_ID`  | Scope the command to a single team (workspace).                     |
| `--base-url`      | `GUMLOOP_BASE_URL` | Override the Gumloop API base URL (useful for self-hosted/staging). |
| `--version`, `-V` | —                  | Print the CLI version and exit.                                     |
| `--help`, `-h`    | —                  | Show contextual help for any command or subcommand.                 |

Most subcommands additionally accept `--json` to print the raw response payload instead of the human-friendly table, which is handy for piping into `jq`, scripts, or other tools.
