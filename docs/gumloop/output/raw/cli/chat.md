> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Chat

> Send chat completions to any Gumloop-supported model from the terminal.

`gumloop chat completions create` is the terminal counterpart of the Python SDK call `client.chat.completions.create(...)`. Every flag maps 1:1 to the matching SDK kwarg.

## Create a completion

```bash theme={"dark"}
gumloop chat completions create "Capital of Canada?" -m claude-sonnet-4-5
```

By default, output streams to your terminal when stdout is a TTY and is buffered into a single response when it isn't (e.g. piped to a file or another command). Pass `--stream` or `--no-stream` to be explicit.

| Flag                       | Description                                                                                                                                  |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `-m`, `--model`            | **Required.** Model slug (for example `claude-sonnet-4-5`, `gpt-4o-mini`, `gemini-2.5-pro`).                                                 |
| `-s`, `--system`           | System message prepended to the conversation. Repeatable.                                                                                    |
| `--message-stdin -`        | Read the user message from stdin instead of the positional argument.                                                                         |
| `--max-completion-tokens`  | Cap on completion tokens.                                                                                                                    |
| `--temperature`            | Sampling temperature.                                                                                                                        |
| `--modality`               | Output modality. Repeatable — e.g. `--modality image --modality text` for image-generation models.                                           |
| `--schema-file`            | Path to a JSON Schema file. Sent as `response_format={"type": "json_schema", ...}` for structured output.                                    |
| `--schema-name`            | Name to attach to the schema (defaults to `schema`).                                                                                         |
| `--stream` / `--no-stream` | Force or suppress streaming. When omitted, streams only if stdout is a TTY. `--json` implies `--no-stream` unless `--stream` is also passed. |
| `--json`                   | Print the response as JSON. With `--stream`, emits newline-delimited JSON (one chunk per line).                                              |

## Pipe from stdin

```bash theme={"dark"}
cat draft.md | gumloop chat completions create -m claude-sonnet-4-5 \
  --message-stdin - \
  --system "Summarize the input in three bullets."
```

## Structured output

```bash theme={"dark"}
gumloop chat completions create "Return JSON with the capital of Canada." \
  -m claude-sonnet-4-5 \
  --schema-file ./capital.schema.json \
  --schema-name capital \
  --json
```

`capital.schema.json`:

```json theme={"dark"}
{
  "type": "object",
  "properties": { "capital": { "type": "string" } },
  "required": ["capital"],
  "additionalProperties": false
}
```

## Image generation

```bash theme={"dark"}
gumloop chat completions create "A red maple leaf on white" \
  -m gpt-image-1.5 \
  --modality image --modality text \
  --json
```

The response carries one or more image attachments on `choices[0].message.images`. Each entry is a data URL the caller can decode or render directly.

## Streaming with machine output

`--stream --json` emits ndjson — one full `chat.completion.chunk` per line — so consumers can stitch deltas without re-parsing the full SSE wire format.

```bash theme={"dark"}
gumloop chat completions create "stream me" -m claude-sonnet-4-5 --stream --json
```

<Tip>The CLI streams to TTYs by default. When redirecting stdout (`> out.txt`, `| jq`, CI pipes) it switches to unary so output is byte-stable.</Tip>
