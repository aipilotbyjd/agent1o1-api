> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Static Egress IPs

> Fixed outbound IP addresses for firewall and allowlist configuration.

Gumloop routes all outbound traffic through a fixed set of static IP addresses. If your organization requires firewall allowlisting or network-level access controls, add the following IPs to your allowlist.

<Info>
  These IPs cover all Gumloop services that make outbound requests on your behalf, including workflow execution, agent execution, trigger polling, and MCP integrations.
</Info>

## IP Addresses

```text theme={"dark"}
8.229.10.233
8.231.186.254
8.235.126.180
34.11.198.142
34.19.1.9
34.19.116.218
34.53.28.46
34.53.76.64
34.53.111.40
34.82.238.205
34.83.79.220
34.105.93.38
34.127.37.129
34.168.17.238
34.168.215.172
34.169.30.92
34.169.86.55
136.66.32.248
136.66.36.233
136.66.113.246
136.66.131.36
136.67.43.83
136.117.108.82
136.118.248.115
```

All 24 IPs are individual addresses, not a contiguous CIDR block. Each one should be added as a `/32` entry in your firewall rules.

## Usage Notes

<Warning>
  Do not allowlist a subset of these IPs. Gumloop distributes traffic across all 24 addresses, and any individual request may originate from any one of them.
</Warning>

* **Region**: All IPs are located in `us-west1` (Oregon, USA).
* **Ports**: These IPs apply to all outbound traffic regardless of destination port. Most requests will be HTTPS (TCP 443), but the same IPs are used for any protocol or port.
* **Stability**: These IPs are static and will not change on a regular basis. Any changes will be reflected on this page.

## Related Resources

<CardGroup cols={2}>
  <Card title="SSO, SAML & SCIM" icon="key" href="/enterprise-features/sso_saml_scim">
    Configure single sign-on and user provisioning
  </Card>

  <Card title="Audit Logging" icon="clipboard-list" href="/enterprise-features/audit_logging">
    Track user actions across your organization
  </Card>

  <Card title="Security & Compliance" icon="shield-check" href="https://trust.gumloop.com/">
    View our security certifications
  </Card>

  <Card title="Rate Limits" icon="gauge-high" href="/core-concepts/rate_limits">
    Understand platform rate limiting
  </Card>
</CardGroup>
