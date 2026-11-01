> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Managed Tunnels

> Securely connect MCP servers running inside your private network to Gumloop, without opening any inbound ports.

A managed tunnel gives Gumloop a secure path to MCP servers that only exist inside your network — on a VPC, a corporate network, or a developer machine. You run a lightweight connector next to your servers, it dials out to Gumloop, and each server becomes usable in Gumloop like any other [Proxied MCP](/enterprise-features/proxied_mcps).

No inbound firewall rules, no public IP, and no changes to the MCP servers themselves.

<Frame>
  <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/list.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=868dcc6cbb9905acfe8c64c810983b4b" alt="Managed Tunnels page under Settings → Organization showing the tunnel table and Add tunnel button" width="700" data-path="images/enterprise-features/managed-tunnels/list.png" />
</Frame>

## Where to find it

Go to **Settings → Organization → Managed Tunnels** at
[gumloop.com/settings/organization/managed-tunnels](https://gumloop.com/settings/organization/managed-tunnels).

<Warning>
  Managed Tunnels is an **Enterprise** feature, and creating, editing, or
  deleting a tunnel requires the same organization-admin permission as
  [Proxied MCPs](/enterprise-features/proxied_mcps). Contact your organization
  admin if you don't see it under **Settings → Organization**.
</Warning>

## How it works

1. You create a tunnel in Gumloop. Gumloop provisions it for you and assigns it a domain — you don't need a Cloudflare account of your own.
2. You run the Cloudflare connector (`cloudflared`) on a machine that can reach your MCP servers, using the tunnel's connector token. The connector makes an **outbound** connection only.
3. You attach each MCP server to the tunnel by giving it a name and the local address it runs on (for example `http://localhost:8000/mcp`).
4. Gumloop publishes each attached server on its own subdomain under `gumlooptunnels.com` and routes requests through the tunnel to that local address. Every hostname is protected by its own access credential that Gumloop manages, so only Gumloop can call it.

### When to use a tunnel

|                                  | Proxied MCP (plain URL)       | Proxied MCP over a managed tunnel | [Hosted MCP](/enterprise-features/hosted_mcps) |
| -------------------------------- | ----------------------------- | --------------------------------- | ---------------------------------------------- |
| **Where the server runs**        | Third party / anywhere public | Inside your network               | On Gumloop's infrastructure                    |
| **Reachable from the internet?** | Yes                           | No                                | N/A                                            |
| **What you run**                 | Nothing                       | The `cloudflared` connector       | Nothing (deployed from GitHub)                 |
| **Best for**                     | Third-party MCP servers       | Internal or on-prem MCP servers   | Custom MCP servers you build                   |

## Prerequisites

Before you begin, ensure you have:

* **Organization admin access**: The same permission required for [Proxied MCPs](/enterprise-features/proxied_mcps).
* **A connector host**: An always-on machine that can reach your MCP servers, where you run `cloudflared`.

### Network requirements

Allow outbound traffic from the connector host to [Cloudflare's tunnel edge](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/configure-tunnels/tunnel-with-firewall/) on port `7844`, over both TCP and UDP:

```text theme={"dark"}
198.41.192.0/19
2606:4700:a0::/44
```

`cloudflared` prefers QUIC over UDP and falls back to HTTP/2 over TCP, so allow both. No inbound port is opened, and you don't need to allowlist Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) on your MCP servers.

## Create a tunnel

<Steps>
  <Step title="Add the tunnel">
    On the Managed Tunnels page, click **Add tunnel** and give it a name that describes the network it reaches into — for example, "Prod network". A domain is assigned automatically once the tunnel is created.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/create-dialog.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=4b67e949089488d4d820f0c73463de2c" alt="Create tunnel dialog with a Name field" width="440" data-path="images/enterprise-features/managed-tunnels/create-dialog.png" />
    </Frame>

    <Note>Each organization can have one managed tunnel. You can attach as many MCP servers to it as you need.</Note>
  </Step>

  <Step title="Start the connector">
    Gumloop shows the connection steps as soon as the tunnel is created, including the exact command to run with the tunnel's token.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/created-steps.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=a88bfbed121f544347ac3acfb7c86638" alt="Tunnel created dialog with three steps: install cloudflared, start the connector, attach your MCP servers" width="440" data-path="images/enterprise-features/managed-tunnels/created-steps.png" />
    </Frame>

    1. Install `cloudflared` on a machine that can reach your MCP servers — follow Cloudflare's [install guide](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) for your OS.

    2. Run the connector with your tunnel token:

       ```bash theme={"dark"}
       cloudflared tunnel run --token <your-tunnel-token>
       ```

    3. Keep it running. The tunnel stays online only while the connector is running, so run it as a service on a long-lived host rather than an ad-hoc terminal.

    <Tip>You can reopen these instructions and reveal or copy the token any time from the tunnel's **Settings** tab.</Tip>
  </Step>

  <Step title="Attach your MCP servers">
    Open the tunnel and, on the **Overview** tab, click **Attach server**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/overview-empty.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=f0e0656f07a30efcaa591928a2ab0ec7" alt="Tunnel detail page Overview tab with no MCP servers attached yet" width="700" data-path="images/enterprise-features/managed-tunnels/overview-empty.png" />
    </Frame>

    Give the server a name and the **local address** the connector should forward to — the address the server listens on from the connector machine's point of view, including the scheme and the MCP path.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/attach-server.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=6e7c1b3068108199704d588e77d20f04" alt="Attach MCP server dialog with Name and Local address fields" width="440" data-path="images/enterprise-features/managed-tunnels/attach-server.png" />
    </Frame>

    Two things are easy to get wrong:

    * **The address resolves from the connector machine.** If `cloudflared` runs in a container, `localhost` is that container's loopback, not the host's.
    * **The path comes from your server, not the tunnel.** FastMCP's streamable HTTP transport serves at `/mcp`; others use `/` or a custom path.

    Each attached server gets its own subdomain and MCP URL, listed on the Overview tab next to the local address it points at.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/attached-server.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=0585c0fd9ff132c5dea4ddadd9b33e78" alt="Tunnel Overview tab listing an attached MCP server with its MCP URL and local address" width="700" data-path="images/enterprise-features/managed-tunnels/attached-server.png" />
    </Frame>
  </Step>

  <Step title="Load its tools">
    An attached server appears in **Proxied MCPs**, with a **Tunneled via *tunnel name*** link back to the tunnel and the local address shown on its Overview tab.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/server-detail.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=a7f232dff1fd2c92808ce17506d1846e" alt="Proxied MCP detail page for a tunneled server showing Tunneled via Prod network, the MCP Server URL, and the local address" width="700" data-path="images/enterprise-features/managed-tunnels/server-detail.png" />
    </Frame>

    Once the connector is running, click **Fetch New Tools** to discover the server's tools. From there it behaves like any other proxied server: per-role [tool access control](/enterprise-features/user_groups), activity and usage stats, and availability to agents and flows.

    <Warning>
      The tunnel carries traffic to your MCP server but does not authenticate
      to it. Unlike other [Proxied MCPs](/enterprise-features/proxied_mcps), a
      tunneled server can't be given its own OAuth or API key credentials.
    </Warning>
  </Step>
</Steps>

## Tunnel status

The status badge on the tunnel list and detail page reflects the live state of your connector:

| Status       | What it means                                                                                           |
| ------------ | ------------------------------------------------------------------------------------------------------- |
| **Healthy**  | The connector is connected and traffic can flow.                                                        |
| **Degraded** | Only some of the connector's connections are up. Traffic may still work, but with reduced redundancy.   |
| **Down**     | The connector has connected before but isn't connected now — usually it stopped or lost network access. |
| **Inactive** | No connector has connected to this tunnel yet. Run the command from the tunnel's Settings tab.          |
| **Unknown**  | Gumloop couldn't read the tunnel's status just now. Refresh and try again.                              |

## Manage a tunnel

The tunnel's **Settings** tab is where you rename it, retrieve the connector token, and delete it.

<Frame>
  <img src="https://mintcdn.com/agenthub/GRm6s6p1JoJYWREq/images/enterprise-features/managed-tunnels/settings.png?fit=max&auto=format&n=GRm6s6p1JoJYWREq&q=85&s=53120e52a0bed8e4b305fa9dab9cae24" alt="Tunnel Settings tab showing Tunnel Name, Connector token, Delete Tunnel, and the connect instructions" width="700" data-path="images/enterprise-features/managed-tunnels/settings.png" />
</Frame>

* **Tunnel Name** — the display name; renaming doesn't affect the domain or any attached server.
* **Connector token** — masked by default. Reveal or copy it when you need to start the connector on a new machine. Treat it like a password: anyone with it can connect a connector to your tunnel.
* **Delete Tunnel** — permanently removes the tunnel and its assigned domain.

<Warning>
  You must delete the MCP servers attached to a tunnel before you can delete the
  tunnel itself; Gumloop blocks the deletion and lists what's still attached.
  After deleting, stop the `cloudflared` process on your machine — it can no
  longer connect.
</Warning>

## Security

* **Outbound only.** The connector opens a connection from your network to the tunnel, so you never open an inbound port. "Outbound" describes the connection, not the requests, which travel over it from Gumloop into your network.
* **Per-server access control.** Each attached server is published on its own hostname behind its own access credential, which Gumloop provisions and stores for you. Requests that don't carry it are rejected at the edge, before they ever reach your network. That credential gates the hostname; it isn't authentication to the MCP server itself.
* **Your local addresses stay internal.** The local address is only used by the connector inside your network; it's never the address MCP clients talk to.
* **Admin-gated.** Only organization admins can create tunnels, read the connector token, attach servers, or delete a tunnel.

## Troubleshooting

<AccordionGroup>
  <Accordion title="The tunnel shows Inactive or Down">
    The connector isn't connected. Check that `cloudflared` is running on the
    machine, that it was started with the current token from the tunnel's
    Settings tab, and that the machine has outbound internet access. The status
    updates when you reload the page.
  </Accordion>

  <Accordion title="An attached server says “Connect to load tools”">
    Its tools haven't been discovered yet. Make sure the connector is running
    and the local address is correct, then click **Fetch New Tools** on the
    server's detail page.
  </Accordion>

  <Accordion title="Tool calls fail even though the tunnel is Healthy">
    A healthy tunnel means the connector is reachable — not that your MCP server
    is. Start with the local address, the most common cause: confirm the scheme,
    host, port, and path match what your server listens on from the connector
    machine. Then `curl` it there and check the connector's logs.
  </Accordion>

  <Accordion title="I can't find Managed Tunnels or the Attach server button">
    Managing tunnels requires the organization permission for external MCP
    servers. Ask an organization admin to create the tunnel and attach servers,
    or to grant you the permission.
  </Accordion>
</AccordionGroup>

## FAQ

<AccordionGroup>
  <Accordion title="Do I need my own Cloudflare account?">
    No. Gumloop provisions and manages the tunnel. You only install
    `cloudflared` and run it with the token Gumloop gives you.
  </Accordion>

  <Accordion title="Where should I run the connector?">
    On any always-on machine that can reach your MCP servers — a VM in the same
    VPC, a container in your cluster, or a bastion host. Run it as a service so
    it restarts with the machine.
  </Accordion>

  <Accordion title="Can one tunnel serve multiple MCP servers?">
    Yes. Attach as many as you like; each gets its own subdomain and its own MCP
    URL, and each shows up separately under Proxied MCPs.
  </Accordion>

  <Accordion title="Can I run more than one connector for the same tunnel?">
    Yes. Running the same command on a second machine adds another connection to
    the tunnel, which is a common way to avoid a single point of failure.
  </Accordion>

  <Accordion title="How do I detach a server from a tunnel?">
    Delete the server from its Proxied MCPs settings. That removes its tunnel
    route and its subdomain; the tunnel and its other servers are unaffected.
  </Accordion>
</AccordionGroup>
