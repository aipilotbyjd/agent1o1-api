> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Who Pays for a Shared Agent?

Credits come from your **organization's shared pool**, no matter who in the org chats with the agent. The agent owner is not billed separately.

## Inside your organization

Every org has one credit pool. Web chat, Slack, and API usage all draw from it.

Per-user caps (set by admins in [permission groups](https://www.gumloop.com/settings/organization/groups)) limit how much one person can spend. The credits still come from the org pool.

## External users

If you share an agent with someone **outside** your organization, their usage bills to **their** account or org, not yours.

## Slack workflow exception

This page is about **agents**. A workflow with a Slack trigger bills the **trigger owner**, not the person who sent the Slack message.

## FAQ

<AccordionGroup>
  <Accordion title="Does the owner get a separate bill when teammates use the agent?">
    No. Org members spend the shared org pool.
  </Accordion>

  <Accordion title="What if I pin agent-owned credentials?">
    Everyone uses that shared login for tool calls. Credit billing still follows the person who ran the chat (org pool if they are in your org).
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Credits" icon="coins" href="/core-concepts/credits">
    How a chat is priced
  </Card>

  <Card title="Share Permissions" icon="share-nodes" href="/core-concepts/share_permissions">
    How sharing works
  </Card>
</CardGroup>
