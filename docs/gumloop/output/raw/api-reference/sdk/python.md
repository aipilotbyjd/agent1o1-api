> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Python SDK

The Gumloop Python SDK ships two clients:

* **`Gumloop`** — the modern resource client used for chat completions, agents, sessions, MCP, skills, artifacts, and teams.
* **`GumloopClient`** — the legacy flows client used to start saved automations and poll for outputs. Emits a `DeprecationWarning` at construction.

Pick `Gumloop` for new code. Use `GumloopClient` only if you need `run_flow` against an existing saved automation.

## Installation

```bash theme={"dark"}
uv add gumloop
```

## Chat completions

`client.chat.completions.create(...)` is an OpenAI-compatible chat surface that routes to every model Gumloop supports (Anthropic, OpenAI, Google Gemini, OpenRouter routes). The streaming variant returns an iterator of `ChatStreamChunk`; the unary variant returns a `ChatResult`.

```python theme={"dark"}
from gumloop import Gumloop

client = Gumloop(access_token="your_access_token")

result = client.chat.completions.create(
    model="claude-sonnet-4-5",
    messages=[{"role": "user", "content": "Capital of Canada?"}],
)
print(result.choices[0].message.content)
```

### Streaming

```python theme={"dark"}
for chunk in client.chat.completions.create(
    model="claude-sonnet-4-5",
    messages=[{"role": "user", "content": "Write a haiku about Toronto."}],
    stream=True,
):
    delta = chunk.choices[0].delta
    if delta.content:
        print(delta.content, end="", flush=True)
```

### Structured output

Pass `response_format={"type": "json_schema", "json_schema": {...}}` to constrain the response to a JSON Schema. The SDK accepts the same shape the OpenAI API documents.

```python theme={"dark"}
result = client.chat.completions.create(
    model="claude-sonnet-4-5",
    messages=[{"role": "user", "content": "Return JSON with the capital of Canada."}],
    response_format={
        "type": "json_schema",
        "json_schema": {
            "name": "answer",
            "strict": True,
            "schema": {
                "type": "object",
                "properties": {"capital": {"type": "string"}},
                "required": ["capital"],
                "additionalProperties": False,
            },
        },
    },
)
print(result.choices[0].message.content)  # '{"capital":"Ottawa"}'
```

### Image generation

Request an image-generation model (`gpt-image-*`, `gemini-*-image-preview`, `dall-e-*`) with `modalities=["image", "text"]`. The response carries image attachments on `choices[0].message.images` as data URLs. Streaming variants emit partial frames natively for OpenAI gpt-image models.

```python theme={"dark"}
result = client.chat.completions.create(
    model="gpt-image-1.5",
    messages=[{"role": "user", "content": "A red maple leaf on white"}],
    modalities=["image", "text"],
    image_config={"size": "1024x1024"},
)
for image in result.choices[0].message.images:
    print(image.image_url.url[:64], "...")
```

### Tool calling

Pass OpenAI-shape tool definitions; the SDK forwards them unchanged so any LLM that supports function calling can invoke them.

```python theme={"dark"}
result = client.chat.completions.create(
    model="claude-sonnet-4-5",
    messages=[{"role": "user", "content": "What's the weather in Toronto?"}],
    tools=[{
        "type": "function",
        "function": {
            "name": "get_weather",
            "description": "Get current weather for a city",
            "parameters": {
                "type": "object",
                "properties": {"city": {"type": "string"}},
                "required": ["city"],
            },
        },
    }],
)
for tc in result.choices[0].message.tool_calls or []:
    print(tc.function.name, tc.function.arguments)
```

### Bring your own provider key

When the calling user has configured their own provider API key (OpenAI, Anthropic, etc.) in Gumloop, every completion they make is automatically charged at half the standard credit cost. No SDK change required — pricing is set server-side from the user's account.

## MCP tools

`client.mcp.execute(...)` returns an `McpExecuteResponse` with one result per tool call. MCP execution failures, such as target server authentication errors or upstream connection failures, are reported on each result instead of being raised as `APIStatusError`. Gumloop request errors, such as missing credentials, invalid request bodies, or endpoint permission failures, can still raise `APIStatusError`.

```python theme={"dark"}
from gumloop import APIStatusError, Gumloop

client = Gumloop(access_token="your_access_token")

try:
    response = client.mcp.execute(
        server_id="gumloop_slack",
        tool_name="slack_send_message",
        arguments={"channel": "#general", "text": "Hello from Gumloop"},
    )
except APIStatusError as error:
    print(f"Gumloop API request failed: {error}")
    raise

result = response.results[0]
if result.status != "success":
    print(result.error)
else:
    print(result.decoded_content)
```

`decoded_content` is a Python SDK convenience property. Raw REST responses include the underlying `content` array.

## Agent versions

Every agent keeps an immutable history of its configuration. `client.agents.list_versions(...)` pages through those snapshots newest-first, and `client.agents.get_version(...)` returns one version's full configuration plus the structured diff against the version before it.

```python theme={"dark"}
from gumloop import Gumloop

client = Gumloop(access_token="your_access_token")

response = client.agents.list_versions("abc123DEFghiJKL", page_size=20)
for version in response.versions:
    print(version.id, version.major_version, version.is_deployed, version.created_at)

detail = client.agents.get_version("abc123DEFghiJKL", response.versions[0].id)
print(detail.version.composition.model_name)
print(detail.version.composition.system_prompt)
print(detail.changes)  # None for the agent's first version
```

Both calls require configuration access on the agent, and they are read-only — deploying or restoring a version has to happen in the app.

## Company Brain

`client.brain.search(...)` runs a hybrid (semantic + keyword) search across the knowledge sources indexed in your [Company Brain](/core-concepts/brain) and returns ranked results scoped to what you can access. Brain is available on the Pro and Enterprise plans, and each search consumes credits.

```python theme={"dark"}
from gumloop import Gumloop

client = Gumloop(access_token="your_access_token")

response = client.brain.search(
    "what is our refund policy?",
    limit=8,                                 # optional, 1–50 (default 8)
    source_type=["notion", "google_drive"],  # optional filter
)
for result in response.results:
    print(result.score, result.source, result.title, result.url)
```

Each result carries `document_id`, `source`, `title`, `content`, `url`, `score`, `updated_at`, `owner_name`, `owner_email`, `parent_title`, and `metadata`. Omit `source_type` to search every source you can access; valid values are `notion`, `google_drive`, `slack`, `github`, `confluence`, `direct_file_uploads`, and `gumloop_artifacts`.

## Flows (legacy client)

```python theme={"dark"}
from gumloop import GumloopClient

# Initialize the client
client = GumloopClient(
    api_key="your_api_key",
    user_id="your_user_id"
)

# Run a workflow and wait for outputs
output = client.run_flow(
    flow_id="your_flow_id",
    inputs={
        "recipient": "example@email.com",
        "subject": "Hello",
        "body": "World"
    }
)

print(output)
```

Optionally add a `project_id` when creating the client if running automations in a workspace:

```python theme={"dark"}
from gumloop import GumloopClient

# Initialize the client
client = GumloopClient(
    api_key="your_api_key",
    user_id="your_user_id",
    project_id="your_project_id"
)
```
