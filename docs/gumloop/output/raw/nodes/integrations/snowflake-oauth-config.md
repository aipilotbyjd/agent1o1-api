> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Snowflake OAuth Configuration

This guide walks you through setting up Snowflake OAuth authentication for Gumloop. By following these steps, you'll configure a secure OAuth integration that allows Gumloop to connect to your Snowflake account on behalf of your users.

<Note>
  **Intended Audience:** Snowflake administrators with ACCOUNTADMIN role or users with CREATE INTEGRATION privilege. This setup is performed once and enables OAuth authentication for your organization's Snowflake connection.
</Note>

<Tip>
  **Alternative Authentication:** If setting up OAuth is not feasible for your organization, you can use [Snowflake Key-Pair (RSA JWT)](/nodes/integrations/snowflake-keypair-config) — best for service users and MFA-enforced accounts — or [Snowflake PAT (Programmatic Access Token)](/nodes/integrations/snowflake-pat-config). OAuth remains the recommended approach for interactive users thanks to enhanced security and automatic token refresh.
</Tip>

<Warning>
  This guide provides basic setup instructions for integrating Snowflake with Gumloop. For production environments and security best practices, please refer to the [official Snowflake OAuth documentation](https://docs.snowflake.com/en/user-guide/oauth-custom) to ensure your configuration meets your organization's security requirements.
</Warning>

## What This Guide Covers

This documentation will help you:

1. **Create a Snowflake OAuth Integration** - Register Gumloop as a custom OAuth client in Snowflake
2. **Retrieve OAuth Credentials** - Get the Client ID and Client Secret needed for Gumloop
3. **Configure Gumloop (Administrator)** - Add the Snowflake OAuth Config to your organization
4. **User Authentication** - Connect individual user accounts with proper scopes

Once complete, your team will be able to authenticate Snowflake connections through OAuth in Gumloop.

## Overview

Snowflake OAuth integration enables secure authentication between Gumloop and your Snowflake account. Instead of sharing static credentials, OAuth allows users to authorize Gumloop to access Snowflake on their behalf with automatic token refresh and better security controls.

### Why Use Snowflake OAuth with Gumloop?

<CardGroup cols={2}>
  <Card title="Enhanced Security" icon="shield-halved">
    OAuth tokens are temporary and can be revoked, reducing the risk of credential exposure
  </Card>

  <Card title="Automatic Token Refresh" icon="arrows-rotate">
    Refresh tokens keep your connection active without manual re-authentication
  </Card>

  <Card title="Centralized Control" icon="sliders">
    Manage access and permissions directly in Snowflake
  </Card>

  <Card title="Audit Trail" icon="list-check">
    Track OAuth authentication events in Snowflake's audit logs
  </Card>
</CardGroup>

***

## Prerequisites

Before you begin, ensure you have:

* **Snowflake Account Access** - You need the ACCOUNTADMIN role or a role with CREATE INTEGRATION privilege
* **Snowflake Account URL** - Your Snowflake account URL (e.g., `https://myorg-account123.snowflakecomputing.com`)

***

## Step 1: Create the Snowflake OAuth Integration

You'll run SQL commands in Snowflake to create a custom OAuth integration for Gumloop.

### 1.1 Connect to Snowflake

1. Log in to your [Snowflake account](https://app.snowflake.com)
2. Open a new SQL worksheet
3. Ensure you're using a role with sufficient privileges:

```sql theme={"dark"}
USE ROLE ACCOUNTADMIN;
```

<Info>
  If you don't have the ACCOUNTADMIN role, ask your Snowflake administrator to either grant you this role temporarily or execute these commands on your behalf.
</Info>

### 1.2 Create the OAuth Integration

Copy and execute the following SQL command to create the OAuth integration:

```sql theme={"dark"}
CREATE OR REPLACE SECURITY INTEGRATION GUMLOOP
  TYPE = OAUTH
  ENABLED = TRUE
  OAUTH_CLIENT = CUSTOM
  OAUTH_CLIENT_TYPE = 'CONFIDENTIAL'
  OAUTH_REDIRECT_URI = 'https://api.gumloop.com/auth/callback'
  OAUTH_ISSUE_REFRESH_TOKENS = TRUE
  OAUTH_REFRESH_TOKEN_VALIDITY = 7776000;
```

<Accordion title="Understanding the Configuration Parameters">
  * **TYPE = OAUTH** - Specifies this is an OAuth integration
  * **ENABLED = TRUE** - Activates the integration immediately
  * **OAUTH\_CLIENT = CUSTOM** - Indicates this is a custom OAuth client (not a pre-built partner integration)
  * **OAUTH\_CLIENT\_TYPE = 'CONFIDENTIAL'** - Marks this as a confidential client that can securely store secrets
  * **OAUTH\_REDIRECT\_URI** - The Gumloop callback URL where users are redirected after authentication
  * **OAUTH\_ISSUE\_REFRESH\_TOKENS = TRUE** - Enables automatic token refresh for persistent connections
  * **OAUTH\_REFRESH\_TOKEN\_VALIDITY = 7776000** - Sets refresh token validity to 90 days (7,776,000 seconds)
</Accordion>

<Warning>
  **Important:** Snowflake automatically adds certain administrative roles to the OAuth blocked roles list: **ACCOUNTADMIN**, **ORGADMIN**, **SECURITYADMIN**, and **GLOBALORGADMIN**. If you need to use these roles with OAuth, you must either:

  * Remove them from the blocked roles list (if your organization's security policy allows), or
  * Switch to a different role that is not blocked

  For more information, see the [Blocking Specific Roles](#blocking-specific-roles) section below.
</Warning>

<Info>
  For custom OAuth integrations (`OAUTH_CLIENT = CUSTOM`), scopes are not configured on the security integration itself. Instead, scopes are specified during the OAuth authorization request. Gumloop handles this automatically when users connect their accounts.

  To control which roles can be used with this integration, use `BLOCKED_ROLES_LIST` to deny specific roles or `PRE_AUTHORIZED_ROLES_LIST` to skip the user consent step for specific roles. Refer to the [Snowflake OAuth documentation](https://docs.snowflake.com/en/user-guide/oauth-custom) for details.
</Info>

### 1.3 Verify the Integration

Confirm the integration was created successfully:

```sql theme={"dark"}
SHOW SECURITY INTEGRATIONS LIKE 'GUMLOOP';
```

You should see `GUMLOOP` in the results.

### 1.4 View Integration Details

To see all configuration details:

```sql theme={"dark"}
DESC SECURITY INTEGRATION GUMLOOP;
```

This displays all properties of your OAuth integration, including the OAuth endpoints.

***

## Step 2: Retrieve OAuth Credentials

Now you need to get the Client ID and Client Secret that Gumloop will use to authenticate.

### 2.1 Get Client Credentials

Execute the following command:

```sql theme={"dark"}
SELECT SYSTEM$SHOW_OAUTH_CLIENT_SECRETS('GUMLOOP');
```

This returns a JSON object containing your credentials. The output will look like:

```json theme={"dark"}
{
  "OAUTH_CLIENT_ID": "ABC123XYZ456...",
  "OAUTH_CLIENT_SECRET": "def789ghi012...",
  "OAUTH_CLIENT_SECRET_2": ""
}
```

<Warning>
  **Keep these credentials secure!** Treat the Client ID and Client Secret like passwords. Do not share them publicly or commit them to version control.
</Warning>

### 2.2 Save the Credentials

Copy and save the following values from the JSON response:

* **OAUTH\_CLIENT\_ID** - You'll need this for Gumloop
* **OAUTH\_CLIENT\_SECRET** - You'll need this for Gumloop

<Tip>
  Store these credentials in a secure password manager until you're ready to add them to Gumloop.
</Tip>

***

## Step 3: Configure Gumloop (Administrator Setup)

Now that you have your Snowflake OAuth credentials, you'll add them to Gumloop as an administrator.

### 3.1 Add Snowflake OAuth Config to Gumloop

1. Navigate to [Settings → Organization → OAuth Configuration](https://www.gumloop.com/settings/organization/oauth-configuration)
2. Search for **"Snowflake OAuth Config"** in the credentials list
3. Click **Add Credential**

<div align="center">
  <img src="https://mintcdn.com/agenthub/cO1r7IxjHlHA1Zed/images/snowflake_oauth.png?fit=max&auto=format&n=cO1r7IxjHlHA1Zed&q=85&s=5edb757128108bd167c5f55ba8c37287" width="700" data-path="images/snowflake_oauth.png" />
</div>

4. Enter the following information:
   * **Client ID**: The `OAUTH_CLIENT_ID` from Step 2.1
   * **Client Secret**: The `OAUTH_CLIENT_SECRET` from Step 2.1

<div align="center">
  <img src="https://mintcdn.com/agenthub/u965qqc0PjeNU8LR/images/Snowflake_add_credentials.png?fit=max&auto=format&n=u965qqc0PjeNU8LR&q=85&s=af9c2d8efb111db94370a1697aa9244c" width="700" data-path="images/Snowflake_add_credentials.png" />
</div>

5. Save the configuration

This sets up the OAuth integration at the organization level. Individual users will now be able to connect using this configuration.

***

## Step 4: User Authentication

Once the Gumloop administrator has completed Step 3, individual users can connect their Snowflake accounts.

### 4.1 Connect Your Snowflake Account

1. Navigate to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Click **Add Credential**
3. Select **Snowflake** from the list of integrations
4. Choose the first Snowflake option as the authentication method

<div align="center">
  <img src="https://mintcdn.com/agenthub/cO1r7IxjHlHA1Zed/images/snowflake_personal_credentials.png?fit=max&auto=format&n=cO1r7IxjHlHA1Zed&q=85&s=06fe7d969d342b8a78a76ee63562e87c" width="700" data-path="images/snowflake_personal_credentials.png" />
</div>

5. Select **Snowflake OAuth Config** (the configuration added by your administrator or Okta if that is setup)
6. Enter the following information:
   * **Workspace ID**: Your Snowflake account identifier (e.g., `myorg-account123`)
   * **Scopes**: Space-separated list of OAuth scopes (see warning below)

### PrivateLink and Private Service Connect

<Warning>
  **PrivateLink accounts are not supported with Gumloop's standard (cloud-hosted) deployment** unless you whitelist Gumloop's [static egress IPs](/enterprise-features/static_egress_ips) in your Snowflake network policy. The OAuth handshake requires Gumloop to reach your Snowflake account over the public internet. If your Snowflake account is behind AWS PrivateLink, Azure Private Link, or Google Cloud Private Service Connect and you are unable to whitelist Gumloop's IPs, connections will fail because Gumloop's servers cannot reach the private endpoint.

  **The solution is a VPC deployment**, where Gumloop runs inside your network perimeter so the OAuth handshake stays private. Contact [support@gumloop.com](mailto:support@gumloop.com) to explore VPC deployment options for your organization.
</Warning>

If your Snowflake account uses PrivateLink **and** you have a Gumloop VPC deployment, include `.privatelink` in the Workspace ID. For example, if your Snowflake URL is `https://myorg-account123.privatelink.snowflakecomputing.com`, enter `myorg-account123.privatelink`.

Snowflake admins can find the private account URL by running `SYSTEM$GET_PRIVATELINK_CONFIG()` and checking the `regionless-privatelink-account-url` field (or `privatelink-account-url` for the region-specific locator format).

<Warning>
  **Critical: Scopes Configuration**

  If you leave scopes empty, most Snowflake operations will fail. You must specify the role(s) you want to use with this connection.

  **Required format:**

  ```text theme={"dark"}
  session:role:YOUR_ROLE_NAME
  ```

  Replace `YOUR_ROLE_NAME` with your actual Snowflake role (e.g., `PUBLIC`, `ANALYST`, etc.). The role name is case-sensitive and must be in uppercase unless the role was created with quotes.

  <Info>
    **Note:** Gumloop automatically handles the `refresh_token` scope internally. You only need to specify the role scope(s).
  </Info>

  **Examples:**

  * Basic access: `session:role:PUBLIC`
  * Analyst role: `session:role:ANALYST`
  * Custom role: `session:role:DATA_ENGINEER`
  * Multiple roles: `session:role:ANALYST,session:role:DATA_ENGINEER`

  For detailed scope configuration, refer to the [Snowflake OAuth scope documentation](https://docs.snowflake.com/en/user-guide/oauth-custom#label-oauth-scope).
</Warning>

### 4.2 Authorize the Connection

After entering your information:

1. Click **Connect** or **Authorize**
2. You'll be redirected to Snowflake's authorization page
3. Log in with your Snowflake credentials
4. Review the requested permissions and role
5. Click **Authorize** to grant Gumloop access
6. You'll be redirected back to Gumloop with a successful connection

### 4.3 Verify Your Connection

To confirm your OAuth connection is working correctly:

1. Go to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Search for **Snowflake**
3. If the connection is successful, you should see your **Snowflake username** displayed instead of "Snowflake Account"

<Frame>
  <img src="https://mintcdn.com/agenthub/fX-hmQiNRO_To9ca/images/snowflake_auth_check.png?fit=max&auto=format&n=fX-hmQiNRO_To9ca&q=85&s=55ef1c1300c8bd9cfaa512d6e8cc4e06" alt="Snowflake OAuth verification showing username instead of account name" width="2878" height="352" data-path="images/snowflake_auth_check.png" />
</Frame>

<Tip>
  If you see your username listed (as shown in the image above), your OAuth connection is properly configured and ready to use!
</Tip>

***

## Blocking Specific Roles

Snowflake automatically blocks certain administrative roles from being used with OAuth for security reasons. These blocked roles include:

* **ACCOUNTADMIN**
* **ORGADMIN**
* **SECURITYADMIN**
* **GLOBALORGADMIN**

These roles are blocked by default and cannot be removed from the block list without contacting Snowflake Support and obtaining approval from your security team.

### Adding Additional Blocked Roles

To block additional custom roles from being used with OAuth:

```sql theme={"dark"}
ALTER SECURITY INTEGRATION GUMLOOP 
  SET BLOCKED_ROLES_LIST = ('SYSADMIN', 'CUSTOM_ADMIN_ROLE');
```

<Info>
  If users need to access Snowflake with OAuth using a role that's currently blocked, they have two options:

  1. Request removal from the blocked roles list (requires Snowflake Support approval)
  2. Switch to a different, non-blocked role that has the necessary permissions
</Info>

***

## Troubleshooting

### "Invalid Client" Error

**Problem:** Getting an "invalid\_client" error when connecting

**Solution:**

* Verify the Client ID and Client Secret are correct in the Snowflake OAuth Config
* Check that the integration is enabled: `DESC SECURITY INTEGRATION GUMLOOP;`
* Ensure the redirect URI matches exactly: `https://api.gumloop.com/auth/callback`

### Most Operations Are Failing

**Problem:** Connected successfully but Snowflake operations return permission errors

**Solution:**
This usually means scopes are not configured correctly. Ensure you specified a valid role scope when connecting your Snowflake account.

Update your credential with a proper role scope, for example: `session:role:PUBLIC`

Gumloop automatically handles the `refresh_token` scope, so you only need to specify the role.

### Role Access Issues

**Problem:** Users can't access certain Snowflake resources or specific role

**Solution:**

* Verify the role name in your scope is spelled correctly and in uppercase
* Check if the desired role is blocked: `DESC SECURITY INTEGRATION GUMLOOP;`
* Ensure the user has been granted the role in Snowflake: `SHOW GRANTS TO USER your_username;`
* If using an administrative role (ACCOUNTADMIN, SECURITYADMIN, etc.), these are blocked by default

### Username Not Showing in Gumloop

**Problem:** Still seeing "Snowflake Account" instead of username in credentials page

**Solution:**

* The OAuth authorization may not have completed successfully
* Try removing the credential and re-connecting
* Verify scopes are configured correctly with a valid role (e.g., `session:role:PUBLIC`)
* Check Snowflake audit logs to confirm the OAuth authorization was successful

### Tokens Expiring Too Quickly

**Problem:** Users need to re-authenticate frequently

**Solution:**
Increase the refresh token validity in Snowflake:

```sql theme={"dark"}
ALTER SECURITY INTEGRATION GUMLOOP 
  SET OAUTH_REFRESH_TOKEN_VALIDITY = 15552000;  -- 180 days
```

***

## Security Best Practices

<CardGroup cols={2}>
  <Card title="Regular Credential Rotation" icon="key">
    Periodically rotate your OAuth client secrets to maintain security
  </Card>

  <Card title="Principle of Least Privilege" icon="user-shield">
    Grant users only the minimum Snowflake roles needed for their work
  </Card>

  <Card title="Monitor OAuth Activity" icon="chart-line">
    Regularly review OAuth token usage in Snowflake audit logs
  </Card>

  <Card title="Network Policies" icon="network-wired">
    Configure Snowflake network policies to restrict OAuth access by IP
  </Card>
</CardGroup>

<Info>
  For comprehensive security guidance and advanced configuration options, refer to the [official Snowflake OAuth documentation](https://docs.snowflake.com/en/user-guide/oauth-custom).
</Info>

***

## Additional Resources

* [Snowflake OAuth Custom Clients Documentation](https://docs.snowflake.com/en/user-guide/oauth-custom)
* [Snowflake OAuth Error Codes](https://docs.snowflake.com/en/user-guide/oauth-error-codes)
* [Snowflake Network Policies](https://docs.snowflake.com/en/user-guide/network-policies)
* [Gumloop Credentials Guide](/core-concepts/credentials)

***

## Need Help?

If you encounter issues not covered in this guide:

1. Check the [Snowflake OAuth documentation](https://docs.snowflake.com/en/user-guide/oauth-custom) for detailed technical information
2. Contact your Snowflake administrator for account-specific issues
3. [Reach out to us](https://portal.usepylon.com/gumloop/forms/help) for integration assistance
