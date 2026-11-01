> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Does My Agent Remember Conversations?

A new conversation starts empty. The agent keeps context inside the **current chat**. To look up earlier work, use **Search Past Conversations** — it lives under **Abilities** and is **on by default**.

## What is remembered

* Everything in this chat, until the context window fills up
* Files and notes in the sandbox for this conversation
* Nothing from other chats, unless a memory tool is on

## Search past conversations

1. Open the agent and confirm **Search Past Conversations** is on in **Abilities**.
2. Ask it to find a prior chat, decision, or file.

The agent pulls relevant snippets. It does not dump every old chat into context.

## Other ways to carry knowledge forward

| Method                                             | What it stores                                     | Best for                                          |
| -------------------------------------------------- | -------------------------------------------------- | ------------------------------------------------- |
| [Skills](/core-concepts/skills)                    | Playbooks, templates, and domain knowledge         | Processes that should stay the same every time    |
| [Self-improve instructions](/core-concepts/agents) | Updates the agent writes into its own instructions | Preferences it should keep after you approve them |
| [Brain](/core-concepts/brain)                      | Company knowledge from connected sources           | Shared facts the whole team should reuse          |

<Tip>
  Keep Search Past Conversations on and put the repeatable process in a skill. Do not paste long history into every new chat.
</Tip>

## Related

<CardGroup cols={2}>
  <Card title="Agent Skills" icon="wand-magic-sparkles" href="/core-concepts/skills">
    Reusable knowledge packs
  </Card>

  <Card title="Agents" icon="robot" href="/core-concepts/agents">
    Chat, abilities, and self-improve
  </Card>
</CardGroup>
