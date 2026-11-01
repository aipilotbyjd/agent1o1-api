> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Roll Out an Agent

Roll out in phases. Test the agent in a 1:1 chat first, add it to one shared Slack channel, then open it up.

## Before you start

* The agent already works in a private chat.
* Slack (and any other tools it needs) are connected. See [Give an agent a connector](/help/using-agents/give-agent-access-to-a-connector).
* You have decided whose connectors the agent should use. By default it uses each person's own accounts.

## Phase 1: Pilot in one channel

1. Add the agent to a single Slack channel. See [Using Agents in Slack](/core-concepts/agents_slack).
2. Start with a few people and real tasks.
3. Fix instructions and tools before you invite more people.

## Phase 2: Expand

* Open the channel to the wider team once the agent behaves the way you want.
* If you want a custom name, avatar, and faster @mentions, connect a [Custom Slack App](/core-concepts/custom_slack_app).
* Add more channels as other teams ask for it.

## Phase 3: DMs

Direct messages and group DMs need a Custom Slack App. The standard @Gumloop bot cannot be added to DMs.

## How people reach the agent

| Entry point                                     | Who needs a Gumloop account     | Whose connectors run                                                                                                          |
| ----------------------------------------------- | ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **@mention** (standard bot or Custom Slack App) | Everyone who mentions the agent | The person who sent the message, unless you pin [agent-owned credentials](/core-concepts/credentials#agent-owned-credentials) |
| **Slack channel trigger**                       | Nobody in the channel           | The trigger owner (one shared connection)                                                                                     |

Use a channel trigger when you want a whole channel to use the agent with no onboarding. If you do that, remove the @mention bot from the channel (`/gummie remove`) and keep **Ignore Bot Messages** on so the agent does not loop.

## Whose connectors the agent uses

By default, a shared agent uses the **invoker's** connectors. To make everyone share one inbox or CRM login, pin [agent-owned credentials](/core-concepts/credentials#agent-owned-credentials).

For @mention rollouts:

* Turn on SSO or domain auto-join in [organization settings](https://www.gumloop.com/settings/organization/general) so people are provisioned on first sign-in.
* Set the agent's [General Access](/core-concepts/share_permissions#general-access) to **Organization** with **Use Only** so members can chat without seeing the setup.

## Operating at scale

* Many people can message the same agent at once. You do not need extra triggers for parallelism.
* Attach [subagents](/core-concepts/agents#subagents) for specialized work instead of stuffing one agent with every tool.
* In Slack Preferences, keep **Only on Mentions** in busy channels.
* Usage comes from the org credit pool. See [Who pays for a shared agent](/help/credits/who-gets-charged-shared-agent).

## FAQ

<AccordionGroup>
  <Accordion title="Should I create a team just to roll this out?">
    No. Keep the agent personal and share it as Use Only unless several people need to own the setup.
  </Accordion>

  <Accordion title="Why does each person get asked to connect Slack or Gmail?">
    The agent is using each invoker's connectors. Pin agent-owned credentials if everyone should act as one shared account.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Using Agents in Slack" icon="slack" href="/core-concepts/agents_slack">
    Add an agent to a channel
  </Card>

  <Card title="Share Permissions" icon="share-nodes" href="/core-concepts/share_permissions">
    Roles and General Access
  </Card>
</CardGroup>
