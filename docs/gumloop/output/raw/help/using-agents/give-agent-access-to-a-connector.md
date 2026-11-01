> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Give an Agent a Connector

To give an agent access to Gmail, Slack, Notion, or any other app, add the connector on the agent **and** sign in. Connecting the app in Settings alone does not attach it to the agent.

## Add a built-in connector

Gumloop includes 150+ built-in connectors.

1. Open the [agent](https://www.gumloop.com/agents).
2. In the right-hand panel, find **Connectors**.
3. Click **+ Connector**.

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5159427690_1.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=58b37815a10ae65723ce00d766545d74" alt="Agent Connectors section with the + Connector button" width="802" height="216" data-path="images/help/5159427690_1.png" />
</Frame>

4. Search for the app (for example, Gmail) and select it.

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5159427690_2.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=271726f585a9a9223a386c357d061a9e" alt="Add Connectors dialog with a search box" width="1074" height="560" data-path="images/help/5159427690_2.png" />
</Frame>

5. If you have not signed in yet, complete the OAuth or API-key flow.

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5159427690_3.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=54e22874633d56d104dc2e47915df111" alt="Click to connect the selected integration" width="790" height="344" data-path="images/help/5159427690_3.png" />
</Frame>

6. Confirm the connector appears on the agent with the account you expect.

<Tip>
  Need a specific inbox, not your default? After the connector is on the agent, open the connector's three-dot menu and pin **Use Specific Account**.
</Tip>

## What else an agent can use

| Type                           | When to use it                                                                                                                                |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Built-in connector             | Gmail, Slack, Sheets, Notion, Salesforce, and other first-party apps                                                                          |
| Custom, proxied, or hosted MCP | An MCP server that is not in the built-in catalog. See [Custom, proxied, and hosted MCP](/help/skills-triggers-mcp/custom-proxied-hosted-mcp) |
| Workflow as a tool             | A fixed multi-step flow the agent should call                                                                                                 |
| Skill                          | A playbook or script the agent should follow                                                                                                  |

## Limit which actions a connector can take

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5159427690_4.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=fdcd23384e6db03990408dfc65ee03eb" alt="Connected Gmail account listed on the agent" width="1252" height="1602" data-path="images/help/5159427690_4.png" />
</Frame>

Open the connector on the agent and turn off tools you do not want, or add an [app rule](/help/skills-triggers-mcp/skills-vs-app-rules) if you need a block or approval.

## Related

<CardGroup cols={2}>
  <Card title="Connectors" icon="plug" href="/core-concepts/credentials">
    Personal vs team connectors
  </Card>

  <Card title="Agents" icon="robot" href="/core-concepts/agents">
    How connectors, skills, and triggers fit together
  </Card>
</CardGroup>
