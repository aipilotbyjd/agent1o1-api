> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Revoke and Reconnect a Connector

Do this when the agent says a connector is disconnected, read-only, or still using an old account after you signed in again.

## Steps

1. Open the right Connectors page:
   * Personal: [Settings → Connectors](https://www.gumloop.com/settings/profile/connectors)
   * Team: the team's Connectors page
   * Org LLM keys: [Organization → API keys](https://www.gumloop.com/settings/organization/api-keys)
2. Open the connector menu and choose **Revoke**.

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/3695232151_1.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=c6780108d8cf4d06085fd7d41b161051" alt="Revoke connector menu" width="1970" height="380" data-path="images/help/3695232151_1.png" />
</Frame>

3. Click **Connect** and sign in with the account you want.
4. Re-attach it on the agent. Adding it in Settings is not enough. See [Give an agent a connector](/help/using-agents/give-agent-access-to-a-connector).

<Tip>
  If the agent still looks read-only, confirm you picked the new account in the three-dot menu, not the revoked one.
</Tip>

## Related

<CardGroup cols={2}>
  <Card title="Connectors" icon="plug" href="/core-concepts/credentials">
    How connections work
  </Card>

  <Card title="Use team connectors" icon="right-left" href="/help/connectors/use-team-connectors">
    Point the agent at the new account
  </Card>
</CardGroup>
