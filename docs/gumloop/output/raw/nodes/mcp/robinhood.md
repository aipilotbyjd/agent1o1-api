> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Robinhood

> Trade and manage your Robinhood portfolio.

Robinhood is a commission-free trading platform for stocks, ETFs, options, and crypto. The Robinhood MCP server lets you trade and manage your portfolio using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Robinhood. Authentication uses OAuth — just connect your Robinhood account and start using it immediately.
</Info>

## What Can It Do?

* **Trade stocks and ETFs** with market or limit orders
* **Manage your portfolio** and view holdings
* **Check account balances** and buying power
* **View market data** and stock quotes

## Where to Use It

### In Agents (Recommended)

Add Robinhood as a tool to any agent. The agent can then manage your portfolio conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Robinhood account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Robinhood tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Robinhood uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Robinhood to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Check portfolio:**

```text theme={"dark"}
Show me my current portfolio holdings and their performance
```

**Place a trade:**

```text theme={"dark"}
Buy 10 shares of AAPL at market price
```

**View account:**

```text theme={"dark"}
What's my current buying power?
```

## Troubleshooting

| Issue               | Solution                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect      | Ensure your Robinhood account is active and in good standing                                                        |
| Trade not executing | Check that you have sufficient buying power and the market is open                                                  |
| Tool not available  | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
