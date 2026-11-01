> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Snowflake Key-Pair Configuration

This guide walks you through connecting Gumloop to Snowflake using **RSA key-pair (JWT) authentication**. Key-pair auth uses a private/public key pair instead of a password or browser login, which makes it ideal for service users and for accounts where multi-factor authentication (MFA) is enforced.

<Note>
  **When to use Key-Pair:** Key-pair authentication is the best fit for **service (non-human) users** and for **accounts with MFA enforced**, since JWT-based connections are not prompted for an interactive MFA challenge. If you prefer browser-based login with automatic token refresh, use [Snowflake OAuth](/nodes/integrations/snowflake-oauth-config) instead.
</Note>

## What This Guide Covers

<CardGroup cols={2}>
  <Card title="Generate a key pair" icon="key">
    Create an RSA private/public key pair in PEM (PKCS#8) format.
  </Card>

  <Card title="Register the public key" icon="snowflake">
    Assign the public key to your Snowflake user with `ALTER USER`.
  </Card>

  <Card title="Configure Gumloop" icon="plug">
    Add your Snowflake Key-Pair credential in the Connectors page.
  </Card>

  <Card title="Verify & troubleshoot" icon="circle-check">
    Confirm the connection and resolve common errors.
  </Card>
</CardGroup>

***

## Which Authentication Method Should You Use?

Gumloop supports three ways to authenticate to Snowflake. You only need **one**.

<CardGroup cols={3}>
  <Card title="OAuth (Recommended)" icon="shield-check">
    Browser-based login with automatic token refresh and centralized control.
    [Set up OAuth →](/nodes/integrations/snowflake-oauth-config)
  </Card>

  <Card title="Key-Pair" icon="key">
    RSA JWT auth. Best for service users and MFA-enforced accounts. **You are here.**
  </Card>

  <Card title="PAT" icon="lock">
    Programmatic Access Token (username + token).
    [Set up PAT →](/nodes/integrations/snowflake-pat-config)
  </Card>
</CardGroup>

<Info>
  When multiple Snowflake credentials exist, Gumloop resolves them in this order: **OAuth → Key-Pair → PAT**. For a clean setup, configure just the one method you intend to use.
</Info>

***

## Prerequisites

Before you begin, ensure you have:

* **Snowflake account access** — a user account you can run `ALTER USER` on (or a Snowflake admin who can do it for you)
* **Account identifier** — the first part of your Snowflake URL, e.g. `myorg-account123` from `https://myorg-account123.snowflakecomputing.com`
* **OpenSSL** (or any tool that can generate an RSA key in PKCS#8 format) available on your machine
* **Public internet access to Snowflake** — Gumloop connects to your account over the public internet (see the [PrivateLink note](#privatelink-and-network-policies) below)

<Warning>
  **PrivateLink accounts are not supported with Gumloop's standard (cloud-hosted) deployment** unless you whitelist Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) in your Snowflake network policy. Key-pair authentication requires Gumloop to reach your Snowflake account over the public internet. If your account is behind AWS PrivateLink, Azure Private Link, or Google Cloud Private Service Connect and you cannot whitelist Gumloop's IPs, connections will fail.

  **The solution is a VPC deployment**, where Gumloop runs inside your network perimeter. Contact [support@gumloop.com](mailto:support@gumloop.com) to explore VPC deployment options.
</Warning>

***

## Step 1: Generate an RSA Key Pair

Run these commands locally to create the key pair. Gumloop requires the **private key** in PEM (PKCS#8) format.

### 1.1 Generate the private key

<CodeGroup>
  ```bash Encrypted (recommended) theme={"dark"}
  # Prompts for a passphrase — you'll enter this same passphrase in Gumloop
  openssl genrsa 2048 | openssl pkcs8 -topk8 -inform PEM -out rsa_key.p8
  ```

  ```bash Unencrypted theme={"dark"}
  openssl genrsa 2048 | openssl pkcs8 -topk8 -inform PEM -out rsa_key.p8 -nocrypt
  ```
</CodeGroup>

This creates `rsa_key.p8`, a PEM file that begins with `-----BEGIN PRIVATE KEY-----` (unencrypted) or `-----BEGIN ENCRYPTED PRIVATE KEY-----` (encrypted).

### 1.2 Generate the matching public key

```bash theme={"dark"}
openssl rsa -in rsa_key.p8 -pubout -out rsa_key.pub
```

This creates `rsa_key.pub`, which begins with `-----BEGIN PUBLIC KEY-----`.

<Tip>
  Store `rsa_key.p8` securely and never commit it to version control. You'll paste its contents into Gumloop in Step 3.
</Tip>

***

## Step 2: Register the Public Key in Snowflake

Assign the **public** key to the Snowflake user Gumloop will connect as.

### 2.1 Assign the public key

In a Snowflake worksheet, run `ALTER USER` with the contents of `rsa_key.pub` — **excluding** the `-----BEGIN PUBLIC KEY-----` / `-----END PUBLIC KEY-----` header lines:

```sql theme={"dark"}
ALTER USER my_service_user SET RSA_PUBLIC_KEY='MIIBIjANBgkqh...rest of key body...';
```

<Info>
  Assigning a public key requires the `SECURITYADMIN` role (or higher), or the `OWNERSHIP`/`ALTER` privilege on the user. If you don't have this, ask your Snowflake administrator to run the command for you.
</Info>

### 2.2 Verify the key was registered

```sql theme={"dark"}
DESC USER my_service_user;
```

Check that the `RSA_PUBLIC_KEY_FP` property (the key fingerprint) is populated.

***

## Step 3: Configure Gumloop

### 3.1 Add the Snowflake Key-Pair credential

1. Go to the [Connectors page (Snowflake Key-Pair)](https://www.gumloop.com/personal/connectors?search=snowflake+key-pair) — this link pre-filters the list to Snowflake Key-Pair
2. Click **Add credential** on the **Snowflake Key-Pair** option

### 3.2 Fill in the credential fields

<div align="center">
  <img src="https://mintcdn.com/agenthub/kTgZzVXbH6ePaktt/images/snowflake_keypair_gumloop.png?fit=max&auto=format&n=kTgZzVXbH6ePaktt&q=85&s=213d4b3e7a37bf1c588a1d3d7dfe6c45" width="420" data-path="images/snowflake_keypair_gumloop.png" />
</div>

| Field                      | Required          | What to enter                                                                                                       |
| -------------------------- | ----------------- | ------------------------------------------------------------------------------------------------------------------- |
| **Account Identifier**     | Yes               | The first part of your instance URL, e.g. `myorg-account123` from `https://myorg-account123.snowflakecomputing.com` |
| **Username**               | Yes               | The Snowflake user that has the matching RSA public key registered (from Step 2)                                    |
| **Private Key**            | Yes               | The full contents of your `rsa_key.p8` file, in PEM (PKCS#8) format. Line breaks are optional when pasting.         |
| **Private Key Passphrase** | Only if encrypted | The passphrase you set when generating an encrypted key. Leave blank for unencrypted keys.                          |

4. Click **Save**.

<Tip>
  Paste the entire private key including the `-----BEGIN PRIVATE KEY-----` and `-----END PRIVATE KEY-----` lines. Gumloop normalizes the formatting automatically, so it's fine if line breaks are lost when copying.
</Tip>

<Note>
  Only **one** Snowflake Key-Pair credential can be configured per account.
</Note>

***

## Step 4: Verify Your Connection

The quickest way to confirm the key-pair connection works is with an agent:

1. Create a new [agent](https://www.gumloop.com/agents)
2. Click **Add Connectors**, search for **Snowflake**, add it, and click **Save**
3. Ask it to run a simple query, for example:

```text theme={"dark"}
Run this query on Snowflake and show me the result: SELECT CURRENT_USER()
```

4. Confirm the agent returns your Snowflake username

Once verified, key-pair credentials work anywhere Snowflake authentication is used in Gumloop, including the [Snowflake MCP integration](/nodes/mcp/snowflake) and the [Snowflake Reader](/nodes/integrations/snowflake_reader) node.

***

## PrivateLink and Network Policies

Snowflake network policies restrict which IP addresses can connect. If your account has network policies enabled, key-pair connections from Gumloop may be blocked unless Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) are added to your allowed list.

* **Production:** Whitelist Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) in your Snowflake network policy.
* **PrivateLink:** Not supported on the standard cloud-hosted deployment unless the static egress IPs are whitelisted — otherwise a [VPC deployment](mailto:support@gumloop.com) is required.

***

## Troubleshooting

<AccordionGroup>
  <Accordion title="Could not parse the Snowflake private key" icon="triangle-exclamation">
    * Make sure you pasted the **private** key (`rsa_key.p8`), not the public key (`rsa_key.pub`).
    * The key must be in **PEM (PKCS#8)** format — it should start with `-----BEGIN PRIVATE KEY-----` or `-----BEGIN ENCRYPTED PRIVATE KEY-----`.
    * If the key is encrypted, confirm the **Private Key Passphrase** field matches the passphrase you set during generation.
  </Accordion>

  <Accordion title="JWT token is invalid / public key mismatch" icon="key">
    * The public key registered on the Snowflake user must be the pair of the private key you added to Gumloop. Re-run Step 2 with the `rsa_key.pub` that matches your `rsa_key.p8`.
    * Verify the fingerprint is set with `DESC USER my_service_user;` and check `RSA_PUBLIC_KEY_FP`.
    * Confirm the **Username** in Gumloop exactly matches the Snowflake user the key is registered on.
  </Accordion>

  <Accordion title="Connection refused or network policy error" icon="network-wired">
    * Whitelist Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) in your Snowflake network policy.
    * PrivateLink accounts require the static egress IPs to be whitelisted, or a VPC deployment.
  </Accordion>

  <Accordion title="Queries fail with a warehouse error" icon="warehouse">
    * Ensure your user's role has the `USAGE` privilege on the warehouse.
    * Specify the warehouse explicitly in the Snowflake Reader node, or set a default warehouse for the user.
  </Accordion>
</AccordionGroup>

***

## FAQs

<AccordionGroup>
  <Accordion title="How is Key-Pair different from PAT and OAuth?">
    All three authenticate Gumloop to Snowflake, but differ in mechanism:

    * **OAuth** — browser-based login with short-lived, auto-refreshed tokens. Best for interactive/human users.
    * **Key-Pair** — RSA JWT authentication using a private key. Best for service users and MFA-enforced accounts, since it isn't subject to an interactive MFA prompt.
    * **PAT** — a Programmatic Access Token used like a password. Simple, but tied to token expiry and network-policy rules.
  </Accordion>

  <Accordion title="Does key-pair auth bypass MFA?">
    Key-pair (JWT) authentication does not trigger an interactive MFA challenge, which is exactly why it's recommended for service users and accounts where MFA is enforced for interactive logins. Restrict the associated Snowflake user to least-privilege roles.
  </Accordion>

  <Accordion title="What key format does Gumloop require?">
    An RSA private key in **PEM (PKCS#8)** format — the output of `openssl pkcs8 -topk8`. Both encrypted and unencrypted keys are supported. Line breaks are optional when pasting; Gumloop normalizes the formatting.
  </Accordion>

  <Accordion title="Do I need the passphrase field?">
    Only if you generated an **encrypted** private key (without `-nocrypt`). For unencrypted keys, leave the passphrase blank.
  </Accordion>

  <Accordion title="Can I use more than one key-pair credential?">
    No — only one Snowflake Key-Pair credential can be configured per account.
  </Accordion>

  <Accordion title="How do I rotate my key?">
    Generate a new key pair, register the new public key on your Snowflake user with `ALTER USER ... SET RSA_PUBLIC_KEY`, then update the **Private Key** (and passphrase, if any) in your Gumloop credential. Snowflake also supports `RSA_PUBLIC_KEY_2` for zero-downtime rotation.
  </Accordion>
</AccordionGroup>

***

## Security Best Practices

<CardGroup cols={2}>
  <Card title="Least Privilege" icon="user-shield">
    Connect as a Snowflake user with only the minimum roles and warehouse access your workflows need.
  </Card>

  <Card title="Encrypt the Private Key" icon="lock">
    Prefer an encrypted key with a passphrase over an unencrypted one.
  </Card>

  <Card title="Rotate Keys Regularly" icon="arrows-rotate">
    Periodically rotate the key pair, using `RSA_PUBLIC_KEY_2` for zero-downtime rotation.
  </Card>

  <Card title="Secure Storage" icon="shield-check">
    Never share the private key in plain text. Gumloop encrypts stored credentials.
  </Card>
</CardGroup>

***

## Additional Resources

* [Snowflake OAuth Configuration](/nodes/integrations/snowflake-oauth-config) — recommended for interactive users
* [Snowflake PAT Configuration](/nodes/integrations/snowflake-pat-config) — token-based alternative
* [Snowflake Reader node](/nodes/integrations/snowflake_reader) and [Snowflake MCP integration](/nodes/mcp/snowflake)
* [Snowflake Key-Pair Authentication docs](https://docs.snowflake.com/en/user-guide/key-pair-auth)
* [How to find your account identifier](https://docs.snowflake.com/en/user-guide/admin-account-identifier)
* [Gumloop Credentials Guide](/core-concepts/credentials)

***

## Need Help?

If you encounter issues not covered here:

1. Review the [Snowflake key-pair authentication documentation](https://docs.snowflake.com/en/user-guide/key-pair-auth)
2. Contact your Snowflake administrator for account-specific issues
3. Reach out to [Gumloop Support](mailto:support@gumloop.com) for integration assistance
