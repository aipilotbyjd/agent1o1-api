> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# NetSuite OAuth Configuration

This guide walks you through setting up NetSuite OAuth authentication for Gumloop. By following these steps, you'll configure a secure OAuth integration that allows Gumloop to connect to your NetSuite account on behalf of your users.

<Note>
  **Intended Audience:** NetSuite administrators with administrator access or users with Integration Application permission. This setup is performed once and enables OAuth authentication for your organization's NetSuite connection.
</Note>

<Warning>
  This guide provides basic setup instructions for integrating NetSuite with Gumloop. For production environments and security best practices, please refer to the [official NetSuite OAuth 2.0 documentation](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_157771733782.html) to ensure your configuration meets your organization's security requirements.
</Warning>

## What This Guide Covers

This documentation will help you:

1. **Create a NetSuite Integration Record** - Register Gumloop as an OAuth 2.0 client in NetSuite
2. **Configure Role Permissions** - Set up OAuth permissions for user roles
3. **Retrieve OAuth Credentials** - Get the Client ID and Client Secret needed for Gumloop
4. **Configure Gumloop (Administrator)** - Add the NetSuite OAuth Config to your organization
5. **User Authentication** - Connect individual user accounts

Once complete, your team will be able to authenticate NetSuite connections through OAuth in Gumloop.

## Overview

NetSuite OAuth integration enables secure authentication between Gumloop and your NetSuite ERP account. Instead of sharing static credentials, OAuth allows users to authorize Gumloop to access NetSuite on their behalf with automatic token refresh and better security controls.

### Why Use NetSuite OAuth with Gumloop?

<CardGroup cols={2}>
  <Card title="Enhanced Security" icon="shield-halved">
    OAuth tokens are temporary and can be revoked, reducing the risk of credential exposure
  </Card>

  <Card title="Automatic Token Refresh" icon="arrows-rotate">
    Refresh tokens keep your connection active without manual re-authentication
  </Card>

  <Card title="Centralized Control" icon="sliders">
    Manage access and permissions directly in NetSuite
  </Card>

  <Card title="Audit Trail" icon="list-check">
    Track OAuth authentication events in NetSuite's audit logs
  </Card>
</CardGroup>

***

## Prerequisites

Before you begin, ensure you have:

* **NetSuite Account Access** - You need administrator access or Integration Application permission
* **NetSuite Account ID** - Your NetSuite account identifier (e.g., `1234567` or `1234567_SB1` for sandbox)

***

## Step 1: Create the NetSuite Integration Record

You'll create an OAuth 2.0 integration record in NetSuite to register Gumloop as an authorized application.

### 1.1 Navigate to Integration Management

1. Log in to your [NetSuite account](https://system.netsuite.com)
2. Navigate to **Setup > Integration > Manage Integrations > New**

<Info>
  If you don't have access to this menu, contact your NetSuite administrator to either grant you the necessary permissions or create the integration on your behalf.
</Info>

### 1.2 Configure Basic Information

Enter the following details in the integration record:

* **Name:** `Gumloop` (or your preferred name, e.g., "Gumloop NetSuite Integration")
* **Description:** Optional description for documentation purposes
* **State:** Set to **Enabled**

### 1.3 Configure Authentication Settings

On the **Authentication** subtab, configure the following settings:

<Steps>
  <Step title="Enable Token-Based Authentication">
    Check **Token-Based Authentication** to enable this authentication method.
  </Step>

  <Step title="Configure OAuth 2.0 Settings">
    Under **OAuth 2.0**, configure the following:

    * **Authorization Code Grant:** Check this option
    * **Public Client:** Check this option (required for the integration)
    * **Redirect URI:** Enter `https://api.gumloop.com/auth/callback`

    <Warning>
      **Important:** The redirect URI must be exactly `https://api.gumloop.com/auth/callback`. The `http://` scheme is not supported for security reasons.
    </Warning>
  </Step>

  <Step title="Enable Required Scopes">
    Enable the following scopes (you can remove other enabled scopes):

    * **RESTlets** - Required for RESTlet access
    * **REST Web Services** - Required for REST API access

    <Info>
      These are the minimum required scopes for Gumloop to interact with NetSuite records and run SuiteQL queries.
    </Info>
  </Step>

  <Step title="Enable User Credentials">
    Under **User Credentials**, check **User Credential** to enable user-based authentication.
  </Step>
</Steps>

### 1.4 Optional Settings

You may also configure these optional settings based on your organization's needs:

* **Refresh Token Validity:** Default is 48 hours (range: 1-720 hours)
* **Maximum Time For Token Rotation:** Default is 168 hours (range: 1-720 hours)
* **OAuth 2.0 Consent Policy:** Choose "Always Ask", "Never Ask", or "Ask First Time"

### 1.5 Save and Record Credentials

1. Click **Save**
2. After saving, copy and securely store your:
   * **Client ID**
   * **Client Secret**
   * **Account ID**

<Warning>
  **Important:** The Client ID and Client Secret are only displayed once after saving. If lost, you'll need to reset them to obtain new values. Store these credentials securely.
</Warning>

***

## Step 2: Configure Role Permissions

OAuth is not automatically enabled for all NetSuite roles. You must configure the appropriate permissions for users who will authenticate via OAuth.

### 2.1 Edit User Roles

1. Navigate to **Setup > Users/Roles > Manage Roles**
2. Edit the role you want to use for OAuth authentication

### 2.2 Add OAuth Permissions

Under **Permissions > Setup**, add the following OAuth permissions:

| Permission                                       | Description                   |
| ------------------------------------------------ | ----------------------------- |
| **OAuth 2.0 Authorized Applications Management** | For admins managing auth apps |
| **Log in using OAuth 2.0 Access Tokens**         | Required for OAuth 2.0 login  |

### 2.3 Add Functional Permissions

Under **Permissions > Setup** or **Permissions > Web Services**, add the functional permissions required for your integration:

| Permission             | Description                  |
| ---------------------- | ---------------------------- |
| **REST Web Services**  | Access to REST API endpoints |
| **RESTlets**           | Access to RESTlet scripts    |
| **User Access Tokens** | Ability to use access tokens |

<Tip>
  The specific permissions needed depend on your use case. At minimum, ensure REST Web Services and RESTlets are enabled for the role.
</Tip>

### 2.4 Save the Role

Click **Save** to apply the permission changes.

<Info>
  For detailed information on role permissions, refer to the [NetSuite OAuth 2.0 documentation](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_157771733782.html#procedure_157838925981).
</Info>

***

## Step 3: Configure Gumloop Credentials

Now that you have your NetSuite OAuth credentials, you'll add them to Gumloop. The setup process differs based on your Gumloop plan.

<Note>
  **Understanding the Two Credential Types:**

  * **NetSuite OAuth Config** - Contains the Client ID and Client Secret from your NetSuite integration record
  * **NetSuite** - Your personal NetSuite authentication that uses the OAuth Config to connect

  Both credentials are required for NetSuite OAuth to work. The difference is where the OAuth Config is stored based on your plan.
</Note>

<Tabs>
  <Tab title="Pro & Enterprise Plans" icon="building">
    ### Organization-Level Setup (Recommended)

    For users on **Pro** or **Enterprise** plans, organization administrators can configure the NetSuite OAuth Config once at the organization level. After this setup, all organization members only need to add their personal NetSuite credentials.

    #### Administrator Setup

    1. Navigate to [Settings → Organization → OAuth Configuration](https://www.gumloop.com/settings/organization/oauth-configuration)
    2. Search for **"NetSuite OAuth Config"** in the credentials list
    3. Click **Add Credential**
    4. Enter the following information:
       * **Client ID**: The Client ID from Step 1.5
       * **Client Secret**: The Client Secret from Step 1.5
    5. Save the configuration

    <Tip>
      Once an organization admin completes this setup, all organization members can authenticate with NetSuite without needing to configure the OAuth Config themselves.
    </Tip>

    #### User Setup (After Admin Configuration)

    After your organization admin has configured the OAuth Config:

    1. Navigate to [Connectors page](https://www.gumloop.com/personal/connectors)
    2. Click **Add Credential**
    3. Select **NetSuite** from the list of integrations
    4. Enter your **workspace name** (the first part of your NetSuite URL, e.g., `gumloop` for `gumloop.app.netsuite.com`)
    5. Click **Add credential** - you'll be redirected to NetSuite to log in and authorize
    6. After logging in, you'll be redirected back to Gumloop with a successful connection
  </Tab>

  <Tab title="Individual Users" icon="user">
    ### Personal Credentials Setup

    For users **without an organization**, you need to add **both** the NetSuite OAuth Config and your NetSuite credentials under your personal credentials.

    #### Step 1: Add NetSuite OAuth Config

    1. Navigate to [Connectors page](https://www.gumloop.com/personal/connectors)
    2. Click **Add Credential**
    3. Search for **"NetSuite OAuth Config"**
    4. Enter the following information:
       * **Client ID**: The Client ID from Step 1.5
       * **Client Secret**: The Client Secret from Step 1.5
    5. Save the configuration

    #### Step 2: Add NetSuite Credentials

    1. Click **Add Credential** again
    2. Select **NetSuite** from the list of integrations
    3. Enter your **workspace name** (the first part of your NetSuite URL, e.g., `gumloop` for `gumloop.app.netsuite.com`)
    4. Click **Add credential** - you'll be redirected to NetSuite to log in and authorize
    5. After logging in, you'll be redirected back to Gumloop with a successful connection

    <Warning>
      Individual users must configure both credentials. If you only add the NetSuite OAuth Config without adding the NetSuite credential, you won't be able to authenticate.
    </Warning>
  </Tab>
</Tabs>

***

## Step 4: User Authentication

Once the NetSuite OAuth Config is set up (either at the organization level or personally), users can connect their NetSuite accounts with a simple OAuth flow.

### 4.1 Connect Your NetSuite Account

1. Navigate to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Click **Add Credential**
3. Select **NetSuite** from the list of integrations
4. Enter your **workspace name** - this is the first part of your NetSuite URL (e.g., `gumloop` for `gumloop.app.netsuite.com`)
5. Click **Add credential**

<Info>
  **Finding Your Workspace Name**

  Your workspace name is the subdomain of your NetSuite account URL:

  * If your NetSuite URL is `https://gumloop.app.netsuite.com`, your workspace name is `gumloop`
  * If your NetSuite URL is `https://mycompany.app.netsuite.com`, your workspace name is `mycompany`
</Info>

### 4.2 Authorize via NetSuite

After clicking **Add credential**:

1. You'll be automatically redirected to NetSuite's login page
2. Log in with your NetSuite credentials
3. Review and approve the requested permissions
4. You'll be redirected back to Gumloop with a successful connection

<Tip>
  The entire process takes just a few seconds - enter your workspace name, click Add credential, log in to NetSuite, and you're done!
</Tip>

### 4.3 Verify Your Connection

To confirm your OAuth connection is working correctly:

1. Go to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Search for **NetSuite**
3. If the connection is successful, you should see your NetSuite account connected

***

## Troubleshooting

### "Invalid Client" Error

**Problem:** Getting an "invalid\_client" error when connecting

**Solution:**

* Verify the Client ID and Client Secret are correct in the NetSuite OAuth Config
* Check that the integration is enabled in NetSuite
* Ensure the redirect URI matches exactly: `https://api.gumloop.com/auth/callback`

### "Access Denied" or Permission Errors

**Problem:** Connected successfully but NetSuite operations return permission errors

**Solution:**

* Verify the user's role has the required OAuth permissions (Step 2)
* Ensure REST Web Services and RESTlets scopes are enabled on the integration
* Check that the user has been assigned a role with the necessary permissions

### OAuth Not Working for Specific Users

**Problem:** Some users can't authenticate via OAuth

**Solution:**

* OAuth is not automatically enabled for all roles
* Verify the user's role has "Log in using OAuth 2.0 Access Tokens" permission
* Check that the role has REST Web Services and RESTlets permissions

### Token Expiring Too Quickly

**Problem:** Users need to re-authenticate frequently

**Solution:**
Increase the refresh token validity in your NetSuite integration record:

1. Go to **Setup > Integration > Manage Integrations**
2. Edit your Gumloop integration
3. Increase **Refresh Token Validity** (up to 720 hours / 30 days)

***

## Security Best Practices

<CardGroup cols={2}>
  <Card title="Regular Credential Rotation" icon="key">
    Periodically rotate your OAuth client secrets to maintain security
  </Card>

  <Card title="Principle of Least Privilege" icon="user-shield">
    Grant users only the minimum NetSuite roles needed for their work
  </Card>

  <Card title="Monitor OAuth Activity" icon="chart-line">
    Regularly review OAuth token usage in NetSuite audit logs
  </Card>

  <Card title="Role-Based Access" icon="users-gear">
    Use NetSuite roles to control what data users can access through Gumloop
  </Card>
</CardGroup>

<Info>
  For comprehensive security guidance and advanced configuration options, refer to the [official NetSuite OAuth 2.0 documentation](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_157771733782.html).
</Info>

***

## Additional Resources

* [NetSuite OAuth 2.0 Documentation](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_157771733782.html)
* [NetSuite REST Web Services](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1558708800.html)
* [NetSuite SuiteQL](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_156257770590.html)
* [Gumloop Credentials Guide](/core-concepts/credentials)
* [Okta Integration Guide](/core-concepts/okta-integration)

***

## Need Help?

If you encounter issues not covered in this guide:

1. Check the [NetSuite OAuth 2.0 documentation](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_157771733782.html) for detailed technical information
2. Contact your NetSuite administrator for account-specific issues
3. [Reach out to us](https://portal.usepylon.com/gumloop/forms/help) for integration assistance
