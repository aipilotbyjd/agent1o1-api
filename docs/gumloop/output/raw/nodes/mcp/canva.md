> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Canva

> Design and manage Canva assets, designs, and brand content.

Canva is the visual design platform for creating graphics, presentations, social media content, and more. The Canva MCP server lets you design and manage your assets and brand content using natural language.

<Info>
  This is a **third-party managed** MCP server operated by Canva. Authentication uses OAuth — just connect your Canva account and start using it immediately.
</Info>

## What Can It Do?

* **Create and manage designs** across various formats
* **Access brand assets** like logos, colors, and fonts
* **Manage design content** and templates

## Where to Use It

### In Agents (Recommended)

Add Canva as a tool to any agent. The agent can then manage your design assets conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Canva account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Canva tools. This gives you the flexibility of an agent within a deterministic workflow.

## Authentication

Canva uses **OAuth 2.0** for authentication. When you connect the integration, you'll be redirected to Canva to authorize access. No API keys or manual configuration required.

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a design:**

```text theme={"dark"}
Create a new Instagram post design with our brand colors
```

**Manage assets:**

```text theme={"dark"}
Show me all my recent designs from this week
```

**Access brand kit:**

```text theme={"dark"}
What fonts and colors are in our brand kit?
```

## Troubleshooting

| Issue              | Solution                                                                                                            |
| ------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Cannot connect     | Ensure you have an active Canva account (Pro or Teams recommended)                                                  |
| Design not saving  | Check that you have edit permissions for the target folder                                                          |
| Tool not available | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance
