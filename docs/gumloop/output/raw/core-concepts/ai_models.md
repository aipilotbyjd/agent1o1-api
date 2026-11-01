> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# AI Models

Gumloop gives your agents access to the top models from every major provider. You choose the model in **Agent Preferences**, at the top of the agent's configuration.

<Info>
  AI models evolve rapidly. New models are usually available in Gumloop within a day of their public release, so you have the latest options even if this page has not caught up yet.
</Info>

## Choosing a model

Open the model dropdown in **Agent Preferences**. The fastest way to choose is one of the three presets at the top:

<Frame>
  <img src="https://mintcdn.com/agenthub/tcZMB0KlIBZ9VBi6/images/agents/model_picker.webp?fit=max&auto=format&n=tcZMB0KlIBZ9VBi6&q=85&s=019d6b050b1a6238e51b2a8cd68bbd1b" alt="Agent model picker showing Recommended, Smartest, and Fastest presets, a provider list, and a model detail card for Claude 4.8 Opus" width="520" data-path="images/agents/model_picker.webp" />
</Frame>

* **Recommended**: the best balance of speed, quality, and cost. This is the default for new agents, and currently points to **Grok 4.6**.
* **Smartest**: maximum intelligence for complex reasoning and agentic work.
* **Fastest**: optimized for speed and low latency on simple, high-volume tasks.

Each preset maps to a current best-in-class model that Gumloop keeps up to date, so you do not have to track model releases yourself. On **Enterprise** plans, your organization can choose which model each preset points to, so you may see different models than the defaults. See [AI Model Governance & Configuration](/enterprise-features/ai_model_control).

<Note>On **Enterprise** plans, admins can restrict which models a [Custom Role](/enterprise-features/user_groups#models-tab) may use. Models your role does not allow are hidden from this picker (and from workflow AI nodes), so you only see the models you are permitted to select.</Note>

To pick a specific model instead, search by name or browse by provider: **Anthropic**, **OpenAI**, **Google**, **DeepSeek**, **MiniMax**, **Z.ai**, and more.

<Tip>Start with **Recommended** for most agents. Switch to **Smartest** when a task needs deeper reasoning, or **Fastest** for simple, high-volume runs where latency matters.</Tip>

## Reading the model card

Selecting or hovering a model opens a detail card so you can compare options at a glance:

* **Description**: what the model is best at.
* **Speed** and **Intelligence**: relative ratings across the catalog.
* **Provider** and **Context**: who makes the model and how much it can read at once (in tokens and approximate words).
* **Tool Calling** and **Vision**: capability checks.
* **Badges**: extra labels that call out how a model is served. Models marked **US-provider hosted** are served by US providers under Zero Data Retention (ZDR) policies.

<Note>For agents that read images or screenshots, pick one with **Vision**.</Note>

## Available models

These are the models you can choose for an agent, grouped by provider. Every one supports tool calling, so it can use your apps and workflows. Models marked **Vision** can also read images and screenshots. New models are added continually, so the in-product picker is always the most up-to-date list.

| Provider  | Model             | Vision |
| --------- | ----------------- | ------ |
| Anthropic | Claude 5 Opus     | Yes    |
| Anthropic | Claude 5 Sonnet   | Yes    |
| Anthropic | Claude 4.8 Opus   | Yes    |
| Anthropic | Claude 4.7 Opus   | Yes    |
| Anthropic | Claude 4.6 Opus   | Yes    |
| Anthropic | Claude 4.6 Sonnet | Yes    |
| Anthropic | Claude 4.5 Sonnet | Yes    |
| Anthropic | Claude 4.5 Haiku  | Yes    |
| OpenAI    | GPT-5.6 Sol       | Yes    |
| OpenAI    | GPT-5.6 Terra     | Yes    |
| OpenAI    | GPT-5.6 Luna      | Yes    |
| OpenAI    | GPT-5.5           | Yes    |
| OpenAI    | GPT-5.4           | Yes    |
| OpenAI    | GPT-5.4 Mini      | Yes    |
| OpenAI    | GPT-5.4 Nano      | Yes    |
| OpenAI    | GPT-5.3 Codex     | Yes    |
| OpenAI    | GPT-5.2           | Yes    |
| OpenAI    | GPT-OSS 120B      | No     |
| Moonshot  | Kimi K3           | Yes    |
| Moonshot  | Kimi K2.7 Code    | Yes    |
| Moonshot  | Kimi K2.6         | Yes    |
| Google    | Gemini 3.1 Pro    | Yes    |
| Google    | Gemini 3.7 Flash  | Yes    |
| Google    | Gemini 3.6 Flash  | Yes    |
| Google    | Gemini 3 Flash    | Yes    |
| Google    | Gemma 4 26B       | Yes    |
| DeepSeek  | DeepSeek V4 Flash | No     |
| DeepSeek  | DeepSeek V4 Pro   | No     |
| MiniMax   | MiniMax M3        | Yes    |
| Qwen      | Qwen3.8 Max       | No     |
| Qwen      | Qwen3.5 397B      | Yes    |
| Z.ai      | GLM-5.2           | No     |
| SpaceXAI  | Grok 4.6          | Yes    |
| SpaceXAI  | Grok 4.5          | Yes    |

<Note>GPT-OSS 120B, Qwen3.5 397B, and Kimi K2.6 are exclusive to agents and are not available in workflow AI nodes. The reverse also happens: a few models don't support tool calling, so they only appear in [workflow AI nodes](#using-these-models-in-workflows) and never in the agent picker.</Note>

<Note>All open-source models (such as GPT-OSS 120B, LLaMA, DeepSeek, Qwen, Kimi, MiniMax, and GLM) are accessed through US-based providers under Zero Data Retention (ZDR) policies, and carry a **US-provider hosted** badge in the model picker. Your data is never used for model training and is not stored after inference.</Note>

## How agents are charged

In agents, model cost is **token-based and variable**. You are charged for the tokens each message uses, which depends on the model, the length of the conversation, and the tools available. There are no fixed per-message tiers. Model calls bill at cost, converted to credits at **\$0.005 per credit**, and each run also carries **Compute** and an **Orchestration Fee**. Open **Chat Details** on any conversation to see its exact usage, and see [Credits](/core-concepts/credits) for the full breakdown.

## Bring your own key (BYOK)

Provide your own provider API key to cut model costs. BYOK makes AI model calls cost **0 credits** in both agents and workflow AI nodes, and it also applies to image generation and voice transcription. Since the model spend leaves your credit balance entirely, agent chats on BYOK carry a **16% orchestration fee instead of 8%**, calculated on what the run would have been worth — still a large net saving. See [Credits](/core-concepts/credits#reducing-credit-costs).

<Note>**Each key only waives the models it can actually serve.** A **Fireworks AI** key covers the open models Gumloop serves through Fireworks — DeepSeek V4 Flash, DeepSeek V4 Pro, Kimi K3, Kimi K2.7 Code, GLM-5.2, MiniMax M3, and Qwen3.8 Max. The other open models in the picker (GPT-OSS 120B, Qwen3.5 397B, Kimi K2.6, Gemma 4 26B) run elsewhere, so a Fireworks key does not affect them.</Note>

<AccordionGroup>
  <Accordion title="Setting up a key" icon="key" defaultOpen={true}>
    Requires a **Pro plan or higher** and your own OpenAI, Anthropic, Google AI, Perplexity, SpaceXAI, or Fireworks AI account.

    <Tabs>
      <Tab title="Pro">
        Add a key under your **personal credentials** at [Connectors](https://www.gumloop.com/personal/connectors) so your own calls route through it, or add a **shared team key** so the whole team can use it without managing individual keys.

        Pro users cannot set keys at the organization level.
      </Tab>

      <Tab title="Enterprise">
        Admins can set **organization API keys** (OpenAI, Anthropic, Google Gemini, Perplexity, xAI, and Fireworks AI) at [Organization > API keys](https://gumloop.com/settings/organization/api-keys). These override personal and team keys for everyone, and all AI requests can be routed through a **custom proxy**.

        See [AI Model Governance & Configuration](/enterprise-features/ai_model_control) for model access control, proxy setup, and model name mapping.
      </Tab>
    </Tabs>
  </Accordion>
</AccordionGroup>

## Using these models in workflows

The same models power workflow AI nodes, and they are billed the **same way as agents**: by token usage. Nodes that don't need tool calling also offer a few models the agent picker leaves out.

<AccordionGroup>
  <Accordion title="How workflow AI nodes are charged" icon="diagram-project">
    Workflow AI nodes (such as Ask AI, Analyze Image, and Generate Report) are billed by **token usage**, based on the model you pick and how many input and output tokens each call uses. There are no fixed per-call tiers, so a short prompt costs far less than a long-context one.

    * Smaller, faster models cost less per token than frontier models.
    * With **BYOK**, workflow AI node calls cost **0 credits**.
    * **Image generation** is billed at a flat **30 credits per image** (free with BYOK), regardless of size, quality, or model. **Audio transcription** is billed by audio length at a small per-minute rate that depends on the model (roughly 1 to 2 credits per minute), and is also free with BYOK.

    For the full workflow credit breakdown, see [Credits](/core-concepts/credits).
  </Accordion>
</AccordionGroup>
