> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Proxied MCPs

> Connect external MCP servers through a managed proxy for centralized monitoring, access control, and security.

Proxied MCPs let you connect existing, external MCP servers to Gumloop through a managed proxy. Instead of deploying your own code, you point Gumloop at a third-party MCP server URL and get full observability, tool access control, and security, all without changing anything on the remote server.

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/server-overview.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=bfd7a671187dc7af3138900f13e61a90" alt="Proxied MCP server detail page for Granola showing overview stats, MCP Server URL, Routing Status, and recent activity" width="2410" height="1548" data-path="images/enterprise-features/proxied-mcps/server-overview.png" />
</Frame>

## Where to find it

Go to **Settings → Organization → Proxied MCPs** at
[gumloop.com/settings/organization/proxied-mcps](https://gumloop.com/settings/organization/proxied-mcps).

<Warning>
  Proxied MCPs is an **Enterprise** feature. Contact your organization admin
  if you don't see it under **Settings → Organization**.
</Warning>

## Hosted vs. Proxied

Not sure which one to use? Here's a quick comparison:

|                                | Hosted MCPs                        | Proxied MCPs                                    |
| ------------------------------ | ---------------------------------- | ----------------------------------------------- |
| **You deploy code?**           | Yes, from a GitHub repo            | No, just provide a URL                          |
| **Where does the server run?** | On Gumloop's infrastructure        | On the third party's infrastructure             |
| **Deployment management**      | Full CI/CD with GitHub integration | Not applicable                                  |
| **Monitoring (pods, logs)**    | Yes                                | No                                              |
| **Activity tracking**          | Yes                                | Yes                                             |
| **Tool access control**        | Yes                                | Yes                                             |
| **Best for**                   | Custom internal tools              | Third-party MCP servers (Granola, Notion, etc.) |

Use **[Hosted MCPs](/enterprise-features/hosted_mcps)** when you want to build and deploy your own MCP server. Use **Proxied MCPs** when you want to connect an existing third-party server.

<Tip>
  Connecting a server that isn't reachable from the internet? Use a
  **[managed tunnel](/enterprise-features/managed_tunnels)**. It brings MCP
  servers running inside your network to Gumloop without opening inbound ports,
  and each one shows up here as a proxied server.
</Tip>

## Server List

The main Proxied MCPs page shows a table of all connected external servers. Each row displays:

* **Server name** with the remote URL (credentials automatically redacted for security)
* **Auth method**: None, API Key, or OAuth 2.0
* **Tool count**: How many tools were discovered on the server
* **Last Updated** timestamp

Use the search bar to filter by name or URL. Click **Connect** to add a new server.

## Connecting a New Proxied MCP

Click the **Connect** button to launch a multi-step wizard that connects an external MCP server through the proxy.

### Step 1: Enter the Server URL

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/connect-step-1.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=7d6664c303acbff68fbb6aafd495e192" alt="Connect wizard step 1 showing an input field for the MCP Server URL with a placeholder example" width="1524" height="648" data-path="images/enterprise-features/proxied-mcps/connect-step-1.png" />
</Frame>

Enter the URL of the MCP server you want to connect to. This should be the server's MCP endpoint (e.g., `https://mcp.example.com/mcp`).

Click **Next** to proceed. Gumloop will probe the server to detect its capabilities and authentication requirements.

### Step 2: Configure Connection

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/connect-step-2.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=ea0571b56f73300130b2105ee4e581f0" alt="Connect wizard step 2 showing Server Configuration with detected auth type, server name, authentication method selection, and credential fields" width="1644" height="1296" data-path="images/enterprise-features/proxied-mcps/connect-step-2.png" />
</Frame>

On this step, you configure:

* **Server Name**: A display name for this server in your organization
* **Detected Auth**: Gumloop automatically detects the server's auth requirements and shows it here
* **Authentication**: Choose the method that matches what the server expects:
  * **No Authentication**: No credentials required
  * **API Key / Credentials**: Provide headers or tokens. Define credential fields that become headers sent with every request.
  * **OAuth 2.0**: If the server supports OAuth, users authenticate via an OAuth flow
* **UI / Raw toggle**: Switch between a form-based UI and raw JSON configuration (using the standard `mcpServers` config format)

<Tip>
  If you have an existing MCP server config in JSON format (like you'd use in
  Claude Desktop or Cursor), switch to **Raw** mode and paste it directly.
</Tip>

Click **Create Server** (or **Next** if OAuth is detected) to proceed.

### Step 3: Discover & Activate

After creating the server connection, Gumloop connects to the remote server, discovers available tools and resources, and activates the proxy. For servers using API Key auth, you'll add your credentials at this step. For OAuth servers, you'll complete the OAuth authentication flow.

Once discovery completes, you'll see a summary of discovered tools and the server will be active.

## Server Detail Page

Every proxied MCP has a detail page with four tabs. Since Gumloop doesn't host the server, there are no Deployments or Monitoring tabs.

### Overview

The overview tab shows a snapshot of the server's last 7 days:

* **Total Calls**: Number of tool calls routed through the proxy
* **Unique Users**: How many distinct users made calls
* **Error Rate**: Percentage of calls that failed
* **Avg Latency**: Average response time
* **MCP Server URL**: The Gumloop proxy URL that clients should connect to
* **Routing Status**: Shows whether the remote server is reachable and accepting connections (displays "Live" when healthy)
* **Recent Activity**: A table of the most recent tool calls

The **Fetch New Tools** button in the header re-discovers tools from the remote server. Use this when the external server has added new tools or changed its capabilities.

### Tools

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/tools-tab.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=1417c488585b96964e855125c828f3ef" alt="Tools tab for a proxied MCP showing a custom-role matrix with tools listed and access toggles per role" width="2410" height="1370" data-path="images/enterprise-features/proxied-mcps/tools-tab.png" />
</Frame>

The Tools tab works the same as for hosted MCPs. You get a custom-role matrix where you can:

* See every discovered tool with its description and usage count
* Toggle access per [custom role](/enterprise-features/user_groups)
* Disable the entire server for a specific role

### Activity

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/activity-tab.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=0ae8d37660761453e4b62f4952443441" alt="Activity tab for a proxied MCP showing usage stats, a 24-hour activity histogram, and a table of recent tool calls" width="2388" height="1614" data-path="images/enterprise-features/proxied-mcps/activity-tab.png" />
</Frame>

The Activity tab shows a server-scoped activity view with:

* **Usage summary**: Total Calls, Unique Users, Error Rate, and Avg Latency for the period
* **Activity histogram**: 24-hour tool call volume with latency color coding (P25/P85-95/P95+)
* **Activity table**: Every tool call with Tool, Time, User, Source, Latency, and Status columns
* **Filters**: Same filtering options as the global [App Activity](/enterprise-features/app_activity) page
* **Export**: Download the filtered data as CSV

### Settings

<Frame>
  <img src="https://mintcdn.com/agenthub/Wy1PJiXBMWIPVNii/images/enterprise-features/proxied-mcps/settings-tab.png?fit=max&auto=format&n=Wy1PJiXBMWIPVNii&q=85&s=683d201a04aeb2dc37c2d7742cd99134" alt="Settings tab for a proxied MCP showing Server Name, Description, MCP Server URL, Authentication Method (OAuth 2.0), and Disable/Delete actions" width="2436" height="1656" data-path="images/enterprise-features/proxied-mcps/settings-tab.png" />
</Frame>

The Settings tab lets you manage:

* **Server Name**: Update the display name
* **Description**: Describe what the server does
* **MCP Server URL**: The proxy URL for MCP clients to connect to
* **Authentication Method**: Shows the configured auth type (set during creation)
* **Disable Server**: Temporarily disable the server. It can be re-enabled later.
* **Delete Server**: Permanently remove the server and all associated configuration

<Warning>
  Deleting a proxied MCP server is permanent. All activity history, tool access
  settings, and the proxy configuration will be removed. The external server
  itself is not affected.
</Warning>

## URL Security

When displaying proxied server URLs in the interface, Gumloop automatically **redacts credentials** embedded in URLs. If a server URL contains authentication tokens or API keys, they are masked in the UI to prevent accidental exposure.

### Allowlisting Gumloop

Gumloop connects from a fixed set of URLs and IP addresses. Servers or OAuth providers that restrict inbound traffic or redirect URLs should permit the following:

* **OAuth redirect URLs**:
  * `https://api.gumloop.com/auth/callback`
  * `https://api.gumstack.com/auth/callback`
* **Source IPs**: Gumloop's [static egress IPs](/enterprise-features/static_egress_ips)

## OAuth Compatibility

Gumloop implements the [MCP authorization spec](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization). For OAuth servers, it discovers the authorization-server metadata and registers as a client automatically through **Dynamic Client Registration (DCR)** or a **Client ID Metadata Document (CIMD)**. Manually pre-registered (static) client IDs are not used.

## Token Lifetimes

OAuth servers issue two types of tokens:

* **Access token**: a short-lived credential sent with each request, typically valid for about an hour.
* **Refresh token**: a long-lived credential used to obtain new access tokens, typically valid for weeks to months. Google's, for example, are valid for roughly six months.

Gumloop refreshes an access token when the connection is next used: if the access token has expired, it uses the refresh token to obtain a new one before making the request. Refreshes are not performed on a background schedule, so a connection that is not used is not refreshed in the meantime.

As a result, the refresh token's lifetime determines how long a connection can stay idle before it must be re-authenticated. If a connection goes unused for longer than the refresh token's lifetime, the refresh token expires before it can be used, and the user is prompted to reconnect. Short refresh-token lifetimes (for example, one day) are a common cause of unexpected reconnects, because a normal gap in activity can outlast the token.

## FAQ

<AccordionGroup>
  <Accordion title="Does the external server need to know about Gumloop?">
    No. The external server receives standard MCP requests from the Gumloop
    proxy. It doesn't need any special configuration or awareness of Gumloop.
  </Accordion>

  <Accordion title="What happens if the external server goes down?">
    Tool calls routed through the proxy will fail, and the **Routing Status** on
    the overview page will reflect the connection issue. Activity logs will show
    errors for the affected period. The proxy continues to check connectivity
    and will resume routing when the server comes back.
  </Accordion>

  <Accordion title="Can I change the authentication method after creation?">
    The authentication method is configured during server creation and cannot be
    changed afterward. If you need a different auth method, delete the server
    and create a new connection with the correct settings.
  </Accordion>

  <Accordion title="How do I update tools when the external server changes?">
    Click the **Fetch New Tools** button in the server header. Gumloop will
    re-discover tools from the remote server and update the tool list. Any new
    tools will be enabled for all custom roles by default.
  </Accordion>

  <Accordion title="What MCP transports are supported?">
    Proxied MCPs support MCP servers that expose an HTTP-based endpoint
    (Streamable HTTP or SSE). The URL you provide should be the server's MCP
    transport endpoint.
  </Accordion>

  <Accordion title="Why are a server's tools sometimes unavailable in an agent?">
    When an agent conversation starts, Gumloop connects to the agent's MCP
    servers and loads their tools within a time limit, so the agent can respond
    promptly. A server that is slow to respond may not finish connecting within
    that window and is skipped for that turn, leaving its tools temporarily
    unavailable.
  </Accordion>
</AccordionGroup>
