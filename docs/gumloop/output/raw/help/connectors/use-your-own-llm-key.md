> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Use Your Own LLM Key

Bring-your-own-key (BYOK) lets you send model calls through your own provider key.

You need an OpenAI, Anthropic, Google AI, Perplexity, SpaceXAI (xAI), or Fireworks account.

## What it changes

* Model calls that the key can serve cost **0 credits**.
* Image generation and transcription that the key covers are also free of model credits.
* Agent chats on BYOK use a **16% orchestration fee** (instead of 8%) on the run's value. That is still usually cheaper.
* A key only covers models that provider can serve. A Fireworks key does **not** cover every open model in the picker. See [AI Models](/core-concepts/ai_models).

## Where to add the key

| Plan           | Where                                                                                                                                                                   |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Standard       | Personal [Connectors](https://www.gumloop.com/settings/profile/connectors), or a shared team key                                                                        |
| **Enterprise** | [Organization → API keys](https://www.gumloop.com/settings/organization/api-keys). Org keys override personal and team keys. You can also route through a custom proxy. |

Non-Enterprise users cannot set org-level keys.

## Steps

1. Open the page in the table above.
2. Click **Add Key** (or connect the provider).
3. Paste the provider API key and save.
4. Run a short agent chat on a model that key covers.
5. Open **Chat Details**. Model credits for that call should be 0.

<Note>
  Enterprise admins can hide models and map names. See [AI Model Governance](/enterprise-features/ai_model_control).
</Note>

## Related

<CardGroup cols={2}>
  <Card title="AI Models" icon="microchip" href="/core-concepts/ai_models">
    Which keys cover which models
  </Card>

  <Card title="Credits" icon="coins" href="/core-concepts/credits">
    BYOK and orchestration fees
  </Card>
</CardGroup>
