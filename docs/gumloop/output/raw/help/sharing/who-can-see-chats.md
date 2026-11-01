> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Who Can See Chats?

Most people only see their own chats. Seeing everyone else's chats requires **both**:

1. The agent lives in a **team** (not a personal space).
2. The person is a **member of that team**.

Use Only never sees another person's chats. Sharing a personal agent as Editor also does not.

## Visibility

| Situation                                          | What they see                                                                                               |
| -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Use Only, any agent                                | Only their own chats                                                                                        |
| Anyone on a **personal** agent you shared          | Only their own chats                                                                                        |
| Team member on a **team** agent (Viewer or Editor) | All chats with that agent                                                                                   |
| Owner of a team agent                              | All chats with that agent                                                                                   |
| Shared with someone who is **not** on the team     | Only their own chats                                                                                        |
| Slack channel                                      | The Slack thread is visible to everyone in the channel. Gumloop chat history still follows the table above. |
| Hosted page visitors                               | Their own hosted-page session only                                                                          |

## FAQ

<AccordionGroup>
  <Accordion title="I shared my personal agent as Editor and they still cannot see other chats">
    Move the agent to a team, or create it there. Personal-space Editors do not get other people's history.
  </Accordion>

  <Accordion title="I am on the team but I only see my chats">
    Confirm the agent lives **on that team**, not in someone's personal space.
  </Accordion>

  <Accordion title="Will Use Only ever see another user's chat?">
    No.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Sharing roles" icon="user-lock" href="/help/sharing/sharing-roles">
    Editor vs Viewer vs Use Only
  </Card>

  <Card title="Share Permissions" icon="share-nodes" href="/core-concepts/share_permissions">
    Full sharing reference
  </Card>
</CardGroup>
