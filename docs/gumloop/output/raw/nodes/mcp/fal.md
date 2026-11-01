> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Fal

> Run AI models for image, video, audio, speech, and 3D generation using fal.ai.

Fal is an AI inference platform that hosts hundreds of generative AI models. The Fal MCP server lets you search for models, inspect their schemas, submit inference jobs, and retrieve results across image, video, audio, speech, and 3D generation categories.

## What Can It Do?

* **Search AI models** by category (text-to-image, text-to-video, text-to-audio, text-to-speech, text-to-3D)
* **Inspect model schemas** to understand accepted input parameters before running a model
* **Submit inference requests** that queue on fal.ai and return a request ID for polling
* **Poll for results** including generated images, videos, audio files, and 3D assets with download URLs

## Where to Use It

### In Agents (Recommended)

Add Fal as a tool to any agent. The agent can search for the right model, check its schema, submit a generation request, and poll for results conversationally.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Fal account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Fal tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Generate an image using fal-ai/flux/dev")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                 | Description                                                                                                                                                           | Credits            |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| **Search Models**    | Search available fal.ai models by category (text-to-image, text-to-video, text-to-audio, text-to-speech, text-to-3D). Supports free-text filtering and result limits. | 3                  |
| **Get Model Schema** | Get the OpenAPI input/output schema for a specific model by endpoint ID. Use this before `run_model` to see what parameters the model accepts.                        | 3                  |
| **Run Model**        | Submit an inference request to fal.ai's queue. Returns a `request_id` for polling with `get_result`.                                                                  | Varies by category |
| **Get Result**       | Poll the status and result of a submitted request. Returns status (`IN_QUEUE`, `IN_PROGRESS`, `COMPLETED`) and the output when done.                                  | 3                  |

### Run Model Credit Costs

The `run_model` tool cost depends on the model category:

| Category       | Credits |
| -------------- | ------- |
| Text to Image  | 24      |
| Text to Audio  | 12      |
| Text to Speech | 60      |
| Text to 3D     | 96      |
| Text to Video  | 2,400   |

## How It Works — Asynchronous Generation

Fal does **not** return results instantly. It uses an asynchronous queue system, which means generation happens in the background and you retrieve the output separately once it's ready.

Here's the full flow:

1. **Search** for a model using `search_models` with a category like `text_to_image`
2. **Inspect** the model's accepted parameters using `get_model_schema`
3. **Submit** a request using `run_model` — this queues the job on fal.ai and immediately returns a `request_id`. The generation has started, but the result is **not available yet**.
4. **Wait and poll** using `get_result` with the `request_id`. The status will progress through `IN_QUEUE` → `IN_PROGRESS` → `COMPLETED`. You need to keep polling until the status reaches `COMPLETED`.
5. **Retrieve the output** — once `COMPLETED`, the response contains download URLs for the generated content (images, videos, audio, 3D assets).

<Warning>
  Generation is not real-time. After submitting a request, there is a waiting period while fal.ai processes the job. Image generation typically takes a few seconds, but video and 3D generation can take several minutes. The agent will submit the job, then poll periodically until the result is ready.
</Warning>

<Info>
  The download URLs returned in the result are temporary. Make sure to download or use the generated files promptly after retrieval.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a product hero image:**

```text theme={"dark"}
Generate a clean product photo of a pair of white sneakers on a marble surface with soft studio lighting
```

**Generate a social media video:**

```text theme={"dark"}
Create a 5-second animated video of a logo reveal with a dark background for my brand intro
```

**Create a voiceover for a demo:**

```text theme={"dark"}
Generate a professional voiceover saying "Welcome to our platform. Let's walk through the key features."
```

**Generate background music:**

```text theme={"dark"}
Create a 30-second upbeat lo-fi background track for a product walkthrough video
```

**Create a 3D asset:**

```text theme={"dark"}
Generate a 3D model of a minimalist desk lamp for use in a product render
```

**Batch-generate marketing visuals:**

```text theme={"dark"}
Generate 4 variations of a banner image showing a futuristic cityscape for our landing page A/B test
```

## Troubleshooting

| Issue                  | Solution                                                                                                            |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Model not found        | Verify the endpoint ID is correct by searching models first                                                         |
| Request still in queue | Video and 3D generation can take minutes. Keep polling with `get_result` until status is `COMPLETED`                |
| Model type mismatch    | Ensure the `model_type` parameter matches the model's actual category                                               |
| Authentication failed  | Verify your Fal API key is connected and valid                                                                      |
| Tool not available     | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Agents handle the full async workflow automatically. When you ask "Generate a product photo of sneakers," the agent will search for an image model, check its schema, submit the request, wait for it to finish processing, and then return the download URL — all without you needing to manage the polling yourself. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Fal MCP server](https://www.gumloop.com/mcp/fal) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
