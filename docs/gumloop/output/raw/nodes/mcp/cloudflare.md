> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Cloudflare

> Manage your Cloudflare account, DNS, and Workers.

Cloudflare is a web infrastructure and security company providing CDN, DNS, DDoS protection, and serverless computing. The Cloudflare MCP server lets you manage your account, DNS records, and Workers using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Cloudflare. Authentication uses OAuth — just connect your Cloudflare account and start using it immediately.
</Info>

## What Can It Do?

* **Manage DNS records** across your domains
* **Configure Workers** and serverless functions
* **View account settings** and zone configurations
* **Monitor site performance** and security events

## Where to Use It

### In Agents (Recommended)

Add Cloudflare as a tool to any agent. The agent can then manage your infrastructure conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Cloudflare account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Cloudflare tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Cloudflare uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Cloudflare to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Manage DNS:**

```text theme={"dark"}
Add an A record for api.example.com pointing to 192.0.2.1
```

**Check Workers:**

```text theme={"dark"}
List all my deployed Cloudflare Workers
```

**View zones:**

```text theme={"dark"}
Show me the DNS records for my domain example.com
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have admin access to your Cloudflare account                                                             |
| Permission denied  | Check that the OAuth scope includes the resources you're trying to manage                                           |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
