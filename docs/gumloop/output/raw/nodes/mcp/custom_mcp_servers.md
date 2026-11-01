> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Custom MCP Servers

> Connect your own MCP servers to Gumloop for extended AI capabilities.

Gumloop supports connecting to any [Model Context Protocol (MCP)](https://www.gumloop.com/blog/what-is-mcp-model-context-protocol-a-simple-guide) server. This lets you extend your agents and workflows with specialized tools, internal services, or any MCP-compatible API.

<Info>
  Gumloop already has **50+ pre-built MCP servers** for popular services like GitHub, Slack, Notion, HubSpot, and more. These work out of the box with agents and workflows. [Browse available integrations](/nodes/mcp) before setting up a custom server.
</Info>

## Adding a Custom MCP Server

Setting up a custom MCP server takes just a few steps.

<Steps>
  <Step title="Go to Connectors">
    Navigate to **Settings > [Connectors](https://www.gumloop.com/personal/connectors?provider=mcp%20server)**, click the arrow next to **Add Connector**, and choose **Add MCP Connector**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/cosEBQC-WVXPGYKJ/images/custom-mcp-add-connector-menu.png?fit=max&auto=format&n=cosEBQC-WVXPGYKJ&q=85&s=24239dd8524c1b8c4ee55bfb8e40664b" alt="Add Connector dropdown with the Add MCP Connector option" width="826" height="328" data-path="images/custom-mcp-add-connector-menu.png" />
    </Frame>
  </Step>

  <Step title="Enter the Server URL">
    Pick a **Connection type** and enter your MCP server's URL, then click **Connect**.

    | Connection type          | When to use                                                                                                         |
    | ------------------------ | ------------------------------------------------------------------------------------------------------------------- |
    | **Public URL**           | A publicly reachable MCP server. The URL must use HTTPS.                                                            |
    | **Private (via tunnel)** | A server inside your network, routed through a [Managed Tunnel](/enterprise-features/managed_tunnels) (Enterprise). |

    <Frame>
      <img src="https://mintcdn.com/agenthub/cosEBQC-WVXPGYKJ/images/custom-mcp-connect-server.png?fit=max&auto=format&n=cosEBQC-WVXPGYKJ&q=85&s=2396b20ed114a9e94a12d8dcaab82bb5" alt="Connect MCP Server dialog with connection type and server URL" width="1378" height="948" data-path="images/custom-mcp-connect-server.png" />
    </Frame>
  </Step>

  <Step title="Configure Authentication">
    Gumloop probes the server and shows the **Detected Auth** method. If the server supports OAuth, click **Authenticate** and sign in to connect your account. Otherwise, open the **Authentication** dropdown to supply a token, API key, or custom header instead.

    <Frame>
      <img src="https://mintcdn.com/agenthub/cosEBQC-WVXPGYKJ/images/custom-mcp-configure-auth.png?fit=max&auto=format&n=cosEBQC-WVXPGYKJ&q=85&s=3578d8680cdb61265a904871e75613df" alt="Configure dialog showing detected OAuth 2.0 authentication" width="1272" height="742" data-path="images/custom-mcp-configure-auth.png" />
    </Frame>
  </Step>

  <Step title="Add It to Your Agent">
    Go back to your agent, open the **Connectors** section, and click **Add Connector**. Search for the server you just added and select it to give the agent access to its tools.

    <Frame>
      <img src="https://mintcdn.com/agenthub/cosEBQC-WVXPGYKJ/images/custom-mcp-agent-connectors.png?fit=max&auto=format&n=cosEBQC-WVXPGYKJ&q=85&s=ae9456375aeea10a990bb741b539cb49" alt="Connectors section of an agent with the Add Connector button" width="802" height="216" data-path="images/custom-mcp-agent-connectors.png" />
    </Frame>
  </Step>
</Steps>

<Tip>
  **Team vs Personal credentials**: Credentials can be stored at the personal level (only you can use them) or team level (shared with your team). Choose the appropriate scope when setting up. [Learn more about credentials here](/core-concepts/credentials#personal-vs-team-connectors)
</Tip>

## Requirements

Custom MCP servers must meet these requirements:

| Requirement       | Details                                           |
| ----------------- | ------------------------------------------------- |
| **Protocol**      | HTTPS only (HTTP not supported)                   |
| **Accessibility** | Must be publicly accessible on the internet       |
| **Transport**     | Streamable HTTP or Server-Sent Events (SSE)       |
| **Local servers** | Not supported (no STDIO or localhost connections) |

<Warning>
  **Local MCP servers won't work.** Your server must be deployed to a publicly accessible URL. Services like [Cloudflare Tunnels](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) or [ngrok](https://ngrok.com/) can expose local servers if needed.
</Warning>

<Tip>
  **On Enterprise?** [Managed Tunnels](/enterprise-features/managed_tunnels) reaches MCP servers inside your network without opening inbound ports or running your own tunnel. Each becomes an organization-wide [Proxied MCP](/enterprise-features/proxied_mcps) rather than a personal credential.
</Tip>

### Authentication Options

Gumloop supports multiple authentication methods:

* **Bearer tokens**: Standard OAuth/API key authentication. When you provide an **Access Token / API Key**, Gumloop sends it as an `Authorization: Bearer <token>` header with every request to your MCP server.
* **Custom headers**: For services requiring specific header formats. The **Additional Header** field accepts a single header in `Header-Name: value` format (e.g., `X-API-Key: my-secret-key`). This is useful for MCP servers that expect authentication in a non-standard header.
* **OAuth discovery**: Automatic OAuth flow discovery (RFC 8414) for compatible servers

## Where You Can Use Custom MCP Servers

Once configured, your custom MCP servers can be used in two places: **Agents** and the **Ask AI node**.

### Using MCP Servers with Agents

Agents offer the most flexible way to use custom MCP servers. The AI can discover all available tools and use them naturally in conversation.

<Steps>
  <Step title="Open Agent Configuration">
    Go to your agent's settings and find the **Connectors** section.
  </Step>

  <Step title="Select MCP Server">
    Click **Add Connector** and search for your configured MCP server.

    <Frame>
      <img src="https://mintcdn.com/agenthub/cosEBQC-WVXPGYKJ/images/custom-mcp-agent-connectors.png?fit=max&auto=format&n=cosEBQC-WVXPGYKJ&q=85&s=ae9456375aeea10a990bb741b539cb49" alt="Connectors section of an agent with the Add Connector button" width="802" height="216" data-path="images/custom-mcp-agent-connectors.png" />
    </Frame>
  </Step>

  <Step title="Use Your Agent">
    Your agent now has access to all tools from the MCP server. It will automatically discover and use them based on conversation context.
  </Step>
</Steps>

**Why agents are more flexible:**

* **Conversational context**: The agent maintains conversation history and can use tools across multiple turns
* **Automatic tool selection**: The agent chooses the right tool based on your request
* **Multi-server support**: Connect multiple MCP servers and let the agent orchestrate between them
* **No workflow required**: Use immediately in chat, Slack, or embedded interfaces

### Using MCP Servers with Ask AI Node

For deterministic workflows, you can connect MCP servers to the Ask AI node.

<Steps>
  <Step title="Add Ask AI Node">
    Drag an Ask AI node onto your canvas.
  </Step>

  <Step title="Enable MCP">
    Click **Show more options**, then toggle **Connect MCP Server?** to ON.
  </Step>

  <Step title="Select Server(s)">
    Choose your configured MCP server(s) from the dropdown. You can select multiple servers.

    <Frame>
      <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/ask_ai_mcp_support.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=7913bb835bbe380610fc9992d8c4dab2" alt="Enabling MCP in Ask AI Node" width="1768" height="1214" data-path="images/ask_ai_mcp_support.png" />
    </Frame>
  </Step>
</Steps>

**When to use Ask AI node with MCP:**

* Building repeatable, production workflows
* Need specific tool calls as part of a larger automation
* Want to combine MCP tools with other Gumloop nodes

### Comparison: Agents vs Ask AI Node

| Capability           | Agents                             | Ask AI Node                     |
| -------------------- | ---------------------------------- | ------------------------------- |
| **Flexibility**      | High: conversational, multi-turn   | Medium: single prompt execution |
| **Tool discovery**   | Automatic                          | Automatic                       |
| **Multi-server**     | Yes                                | Yes                             |
| **Best for**         | Interactive use, complex reasoning | Workflows, batch processing     |
| **Approval prompts** | Not available                      | Not available                   |

## Model-Specific Differences

Custom MCP servers work across all models in Gumloop, but how they run depends on the provider:

| Model             | Provider  | How MCP Tools Run                               |
| ----------------- | --------- | ----------------------------------------------- |
| GPT-5.5           | OpenAI    | Native MCP                                      |
| GPT-5.4           | OpenAI    | Native MCP                                      |
| Claude 4.6 Sonnet | Anthropic | Native MCP                                      |
| Claude 4.5 Sonnet | Anthropic | Native MCP                                      |
| Gemini            | Google    | Backend connector (Gumloop executes tool calls) |
| Groq models       | Groq      | Backend connector (Gumloop executes tool calls) |

* **Native MCP**: The provider (OpenAI/Anthropic) connects directly to your MCP server and executes tools.
* **Backend connector (Gumloop executes tool calls)**: Gumloop connects to your server and presents tools as regular function calls; when invoked, Gumloop executes them and returns results to the model.

### Header Handling by Model

| Execution Method                    | Bearer Token                            | Additional Header |
| ----------------------------------- | --------------------------------------- | ----------------- |
| **OpenAI (Native MCP)**             | Sent as `Authorization: Bearer <token>` | Sent as-is        |
| **Anthropic (Native MCP)**          | Sent as authorization token             | Not forwarded     |
| **Gemini/Groq (backend connector)** | Sent as `Authorization: Bearer <token>` | Sent as-is        |

<Warning>
  Anthropic models do not forward custom headers. If your MCP server relies on a custom header (e.g., `X-API-Key`), use the **Access Token / API Key** field with a Bearer token instead, or choose OpenAI, Gemini, or Groq.
</Warning>

## Security Considerations

<AccordionGroup>
  <Accordion title="Data sharing">
    Information in your prompts may be sent to your MCP server. Be mindful of sensitive data and review your server's data handling policies.
  </Accordion>

  <Accordion title="Direct tool access">
    All tools exposed by your MCP server are immediately available to the AI. There are no approval prompts before tool execution. Use appropriate authorization scopes to limit access.
  </Accordion>

  <Accordion title="Multi-server implications">
    When using multiple MCP servers, consider that data retrieved from one server could be passed to another. Design your prompts accordingly.
  </Accordion>
</AccordionGroup>

## Troubleshooting

| Issue                 | Solution                                                 |
| --------------------- | -------------------------------------------------------- |
| Cannot connect        | Verify URL is HTTPS and publicly accessible              |
| Authentication failed | Check token validity and expiration                      |
| Tools not appearing   | Ensure the server implements MCP tool discovery          |
| AI ignoring tools     | Be more explicit in your prompt about which tools to use |
| Timeout errors        | Server may be slow or unreachable. Check server status.  |

<Tip>
  **Test with discovery first.** Ask your agent or Ask AI node to "list available tools" to verify the connection is working before building complex workflows.
</Tip>

## Further Reading

* [What is MCP? A Simple Guide](https://www.gumloop.com/blog/what-is-mcp-model-context-protocol-a-simple-guide)
* [Introducing MCP Workflows in Gumloop](https://www.gumloop.com/blog/introducing-mcp-workflows)
* [MCP Nodes Best Practices](https://www.gumloop.com/university/video/mcp-nodes-best-practices)
