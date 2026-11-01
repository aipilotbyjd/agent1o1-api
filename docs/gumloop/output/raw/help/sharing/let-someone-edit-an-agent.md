> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Let Someone Edit Your Agent

Open **Share**, add the person, and set their role to **Editor**. Anything less cannot change instructions, tools, or triggers.

## Share the agent

1. Open the [agent](https://www.gumloop.com/agents).
2. Click **Share**.
3. Add the person by email and choose **Editor**.

<Frame>
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_user_share_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=61cdd8eca6d95e6f5258ddce934cac3a" alt="Share dialog with a person set to Editor" width="1336" height="778" data-path="images/agent_user_share_access.png" />
</Frame>

To let a whole team or the org edit it, change **General Access** to that group and set the role to Editor. Be careful: every Editor can change production behavior.

<Frame>
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_general_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=7e40da68b122bef47cd861b34eefd69a" alt="General Access set for a team or organization" width="1410" height="1140" data-path="images/agent_general_access.png" />
</Frame>

## If they still need to connect Gmail or Slack

Use **Copy setup link** in the Share dialog instead of only sending the agent URL. It walks them through authenticating the agent's required connectors.

## If they only need a copy

Use **Make a copy** instead of granting Editor on the original.

<Frame>
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_make_a_copy.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=5f85f844d5773a56c125e001f276123d" alt="Make a copy from the share dialog" width="800" height="110" data-path="images/agent_make_a_copy.png" />
</Frame>

## Team workspace alternative

If several people should own the agent long term, [create a team](/help/sharing/create-a-team) and put the agent there. Then grant Editor on the team agent.

<Note>
  Changing someone's role does not automatically rewrite existing triggers they already created. See [Share Permissions](/core-concepts/share_permissions) for trigger ownership.
</Note>

## Related

<CardGroup cols={2}>
  <Card title="Sharing roles" icon="user-lock" href="/help/sharing/sharing-roles">
    What Editor can do
  </Card>

  <Card title="Roll out an agent" icon="rocket" href="/help/sharing/roll-out-an-agent">
    Company-wide launch
  </Card>
</CardGroup>
