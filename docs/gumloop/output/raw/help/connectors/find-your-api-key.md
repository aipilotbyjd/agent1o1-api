> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Find Your Gumloop API Key

Your Gumloop API key authenticates [the API](/api-reference/authentication), the [CLI](/cli/authentication), and the SDKs. It is **not** an LLM provider key. For those, see [Use your own LLM key](/help/connectors/use-your-own-llm-key).

## Create or view the key

1. Go to [Connectors](https://www.gumloop.com/personal/connectors) (or [Settings → Profile → Connectors](https://www.gumloop.com/settings/profile/connectors)).
2. Click **Add Connector** and search for **Gumloop API Key**.
3. Click **Generate API Key**.
4. Copy the key and store it somewhere safe. You can reveal, copy, or regenerate it later.

The [API authentication](/api-reference/authentication) page links to the same key.

## Personal vs team

|                     | Personal key             | Team key                                     |
| ------------------- | ------------------------ | -------------------------------------------- |
| Acts as             | You                      | Any team member (set `user_id` per request)  |
| Default credentials | Your personal connectors | Each node's Personal Default or Team Default |
| Use when            | Local CLI, solo scripts  | Team automations, CI/CD                      |

Do not paste a personal key into a shared CI secret if a team key will do.

## FAQ

<AccordionGroup>
  <Accordion title="What happens if I regenerate the key?">
    The old key stops working immediately. Update the CLI, GitHub Actions, and any SDK clients.
  </Accordion>

  <Accordion title="Is this the same as an OpenAI or Anthropic key?">
    No. This key authenticates Gumloop's API. Provider keys go on [Use your own LLM key](/help/connectors/use-your-own-llm-key).
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="API Authentication" icon="key" href="/api-reference/authentication">
    How to send the key
  </Card>

  <Card title="CLI Authentication" icon="terminal" href="/cli/authentication">
    `gumloop auth`
  </Card>
</CardGroup>
