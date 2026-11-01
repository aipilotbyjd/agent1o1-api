> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sessions

> Start agent conversations, send follow-ups, and cancel running sessions.

A **session** is a single conversation thread with one agent — start it, send messages back and forth, then cancel or let it finish. `gumloop sessions` is how you do all of that from the terminal.

## Create a session

Start a new conversation with an agent, optionally with the first user message:

```bash theme={"dark"}
gumloop sessions create agent_abc --input "Hello!"
```

Read the message from a file or stdin to avoid escaping:

```bash theme={"dark"}
echo "Summarize this thread:" | gumloop sessions create agent_abc --input-stdin -
```

```bash theme={"dark"}
gumloop sessions create agent_abc --input-stdin - < ./prompt.md
```

| Flag              | Description                                                             |
| ----------------- | ----------------------------------------------------------------------- |
| `--input`         | Initial user message text.                                              |
| `--input-stdin -` | Read the initial message from stdin. Mutually exclusive with `--input`. |
| `--session-id`    | Pre-assign a client-side session ID (otherwise the server assigns one). |
| `--json`          | Print the raw response payload.                                         |

The output shows the new session ID, the agent it's running against, its state, the creation timestamp, and the last few messages. Hang on to the session ID — you'll need it to send follow-ups.

<Tip>Don't have an agent ID yet? Run `gumloop agents list` to see all the agents you can talk to.</Tip>

## Get a session

```bash theme={"dark"}
gumloop sessions get session_abc
```

Prints the session metadata and the last five messages. Add `--json` for the full transcript.

## Send a follow-up

```bash theme={"dark"}
gumloop sessions send session_abc --input "follow-up question"
```

Same input options as `sessions create`:

```bash theme={"dark"}
cat next-turn.txt | gumloop sessions send session_abc --input-stdin -
```

## Cancel a session

```bash theme={"dark"}
gumloop sessions cancel session_abc
```

Stops a session that's currently running.

<Note>
  The CLI returns the agent's final response after each `create` / `send` call but does not stream tokens as they're generated. If you need token-by-token streaming, use [`client.sessions.stream()`](/api-reference/sdk/python) from the Python SDK directly.
</Note>
