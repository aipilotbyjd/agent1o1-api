> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# SSO, SAML & SCIM

Enterprise organizations can configure single sign-on (SSO) authentication and automated user provisioning through SAML and SCIM integrations. This enables centralized identity management, enhanced security, and streamlined user lifecycle management.

## Overview

<CardGroup cols={3}>
  <Card title="Dedicated Login Pages" icon="door-open">
    Custom `gumloop.com/org` login portals for your organization
  </Card>

  <Card title="SAML Authentication" icon="shield-check">
    Enterprise SSO via Okta, Entra ID, Google AD, and more
  </Card>

  <Card title="SCIM Provisioning" icon="users-gear">
    Automated user provisioning plus custom-role and team sync from IdP groups
  </Card>
</CardGroup>

***

## Dedicated SSO Login Pages

Enterprise customers can request a dedicated login page at `gumloop.com/{your-org}`. This provides a branded entry point for your organization's users with configurable authentication options.

<Info>
  To request a custom login page, contact [support@gumloop.com](mailto:support@gumloop.com). Delivery is typically within a few hours after SAML connection setup.
</Info>

### Available Authentication Methods

Organizations can choose which authentication providers to enable or restrict:

| Provider           | Description                                          | Recommendation                             |
| ------------------ | ---------------------------------------------------- | ------------------------------------------ |
| **SAML SSO**       | Enterprise identity providers (Okta, Entra ID, etc.) | Recommended for enterprise                 |
| **Google SSO**     | Sign in with Google Workspace                        | Suitable for Google-based organizations    |
| **Microsoft SSO**  | Sign in with Microsoft 365                           | Suitable for Microsoft-based organizations |
| **Email/Password** | Traditional username and password                    | Not recommended for enterprise             |

<Warning>
  Email/password authentication is not recommended for enterprise deployments. SAML or OAuth-based SSO provides stronger security controls and centralized identity management.
</Warning>

***

## SAML Configuration

SAML (Security Assertion Markup Language) enables enterprise single sign-on through your organization's identity provider.

### Supported Identity Providers

<CardGroup cols={3}>
  <Card title="Okta" icon="key" />

  <Card title="Microsoft Entra ID" icon="microsoft" />

  <Card title="Google Workspace" icon="google" />

  <Card title="JumpCloud" icon="cloud" />

  <Card title="Ping Identity" icon="fingerprint" />

  <Card title="Active Directory" icon="server" />
</CardGroup>

### Setting Up SAML

<Steps>
  <Step title="Access SSO Settings">
    Navigate to [gumloop.com/settings/organization/sso](https://www.gumloop.com/settings/organization/sso)

    <Note>
      SAML and SCIM settings require the **Admin** [organization role](/core-concepts/organization_user_roles#admin) and an Enterprise subscription. SCIM-provisioned users land with the baseline **Member** RBAC role; their custom roles and team memberships come from IdP-group mappings (or name-based resolution, if enabled) and don't affect RBAC.
    </Note>
  </Step>

  <Step title="Generate Setup Link">
    Click **Generate Setup Link** to create a SAML connection configuration. This generates the SP (Service Provider) details needed for your identity provider.
  </Step>

  <Step title="Configure Your Identity Provider">
    Use the generated details to configure a SAML application in your IdP. For step-by-step instructions, see the guides for your provider:

    * [SAML with Okta](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers/okta/saml-with-okta)
    * [SAML with Microsoft Entra ID](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers/entra)
    * [SAML with JumpCloud](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers/jumpcloud)
  </Step>

  <Step title="Request Custom Login Page">
    After completing SAML setup, contact [support@gumloop.com](mailto:support@gumloop.com) to request your dedicated login page at `gumloop.com/{your-org}`.
  </Step>
</Steps>

### SP-Initiated vs IdP-Initiated Login

Gumloop supports **SP-initiated login only**. This means users must start their login flow from Gumloop (the Service Provider) rather than from your identity provider's app dashboard.

<Tabs>
  <Tab title="How It Works" icon="diagram-project">
    **SP-Initiated Flow:**

    1. User navigates to `gumloop.com/{your-org}`
    2. Clicks the SSO login button
    3. Redirects to your IdP for authentication
    4. Upon successful auth, returns to Gumloop with a valid session

    This approach ensures Gumloop controls the full authentication handshake, including session token generation and storage.
  </Tab>

  <Tab title="IdP Tiles & Bookmarks" icon="grid-2">
    **Best Practice:** Instead of IdP-initiated login, configure your IdP to redirect users to your Gumloop login page:

    * **Okta/Entra Tiles:** Set the tile URL to `https://gumloop.com/{your-org}`
    * **Browser Bookmarks:** Bookmark your organization's login page
    * **Company Portals:** Link directly to the SP-initiated login URL

    This provides the same one-click experience while maintaining security.
  </Tab>
</Tabs>

<Info>
  For more technical details on SP vs IdP-initiated SSO, see [SSOReady's guide](https://ssoready.com/blog/guides/idp-vs-sp-initiated-sso/).
</Info>

### SAML Best Practices

<CardGroup cols={2}>
  <Card title="Use SP-Initiated Login" icon="shield-halved">
    Configure IdP tiles to redirect to your Gumloop login page rather than using IdP-initiated flows
  </Card>

  <Card title="Disable IdP-Initiated" icon="ban">
    Prevent IdP-initiated logins in your IdP settings to avoid session handling issues
  </Card>

  <Card title="Test Before Rollout" icon="flask">
    Verify the SAML connection with test users before enabling for your entire organization
  </Card>

  <Card title="Document for Users" icon="book">
    Provide clear instructions to users on how to access Gumloop via your organization's login page
  </Card>
</CardGroup>

### SAML vs SCIM: User Provisioning

<Tabs>
  <Tab title="SAML (JIT Provisioning)" icon="bolt">
    **Just-In-Time (JIT) Provisioning**

    With SAML alone, users are provisioned when they first log in:

    * User authenticates via SAML for the first time
    * Gumloop automatically creates their account on successful auth
    * No pre-provisioning or advance user management

    **Best for:** Organizations that don't need advance user visibility or automated deprovisioning.
  </Tab>

  <Tab title="SCIM (IdP Provisioning)" icon="arrows-rotate">
    **Direct IdP-Based Provisioning**

    SCIM provides proactive user lifecycle management:

    * Users provisioned before first login (visible in member list)
    * Automatic deprovisioning when removed from IdP
    * Custom-role and team sync from IdP groups (each direction configurable independently)

    **Best for:** Organizations requiring advance user management, automated offboarding, or centralized group-based permissions.
  </Tab>
</Tabs>

***

## SCIM Provisioning

SCIM (System for Cross-domain Identity Management) enables automated user provisioning, deprovisioning, and synchronization of both **custom roles** and **teams** between your identity provider and Gumloop. Each direction is configured independently, and each can resolve IdP groups via a curated mapping table or by name match with auto-create on miss.

<Note>
  SCIM is an add-on feature. Contact [support@gumloop.com](mailto:support@gumloop.com) to request SCIM enablement for your organization. The team will evaluate your use case to determine if SCIM is the right solution for your needs.
</Note>

### What SCIM Provides

<AccordionGroup>
  <Accordion title="Automated User Provisioning" icon="user-plus">
    When users are assigned to the Gumloop application in your IdP, they are automatically provisioned in Gumloop. Users appear in your organization's member list and can be viewed before they first log in (pre-provisioning).
  </Accordion>

  <Accordion title="Automated Deprovisioning" icon="user-minus">
    When users are removed from the Gumloop application in your IdP, they are automatically deprovisioned—removing their access and freeing up seats.
  </Accordion>

  <Accordion title="Custom-Role Sync" icon="shield">
    IdP groups can be mapped to Gumloop [Custom Roles](/enterprise-features/user_groups), enabling centralized access control management. Users in **multiple mapped IdP groups receive the union** of every matched role.

    <Warning>
      This is **group-based synchronization**, not role-based access control (RBAC). Restrictions are managed through Gumloop's [Custom Roles](/enterprise-features/user_groups) system.
    </Warning>
  </Accordion>

  <Accordion title="Team Sync" icon="users-rectangle">
    IdP groups can be mapped to Gumloop **teams (projects)**. The team direction is independent of the custom-role direction — you can configure either, both, or neither. Users in multiple mapped IdP groups join every mapped team.
  </Accordion>

  <Accordion title="Per-direction Mode (Mapping Table vs Name-Based)" icon="toggle-on">
    Each direction (roles, teams) has its own **Use mapping table** toggle:

    * **On (default):** the curated mapping table is the source of truth. IdP groups not in the table are skipped — users keep their current memberships when no mapping matches.
    * **Off (name-based):** Gumloop matches each IdP group's display name directly to a Gumloop role/team (case- and whitespace-insensitive). On miss, a new role or team is **auto-created** using the IdP group's name on the next sync. In this mode the IdP is the source of truth, so users with no matching IdP groups have their roles/teams wiped.

    <Warning>
      Switching a direction to name-based mode is **destructive on next sync** for users whose IdP groups don't match any Gumloop entity — the UI requires explicit confirmation before applying. Use it only when your IdP is the authoritative system of record for that direction.
    </Warning>
  </Accordion>
</AccordionGroup>

### Setting Up SCIM

<Steps>
  <Step title="Request SCIM Enablement">
    Contact [support@gumloop.com](mailto:support@gumloop.com) to have SCIM enabled for your organization. The team will evaluate your use case to ensure SCIM is the right solution.
  </Step>

  <Step title="Generate SCIM Credentials">
    Once enabled, navigate to [gumloop.com/settings/organization/sso](https://www.gumloop.com/settings/organization/sso) and use **Generate Setup Link** to create SCIM directory credentials.
  </Step>

  <Step title="Configure Your Identity Provider">
    Set up SCIM provisioning in your IdP using the base URL and bearer token from Gumloop. See provider-specific guides:

    * [SCIM with Okta](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers/okta/scim-with-okta)
    * [SCIM with Microsoft Entra ID](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers/entra)

    <Info>
      SCIM is currently supported for **Okta** and **Microsoft Entra ID** only.
    </Info>
  </Step>

  <Step title="Create Mappings (Optional)">
    Map IdP groups to Gumloop entities under **Group to Custom Role Mappings** and **Group to Team Mappings** in the SSO settings. Each direction is independent.

    <Info>
      Mappings are optional. With the **Use mapping table** toggle on (default) and the table empty, SCIM sync will not modify users' role or team assignments — you can manage them directly in Gumloop. SCIM only changes a user's memberships when an explicit mapping matches.
    </Info>

    For each direction you can instead toggle **Use mapping table** off to switch to name-based mode, where IdP group names auto-resolve to existing Gumloop entities and unmatched names auto-create new ones on the next sync.
  </Step>

  <Step title="Enable Directory Sync">
    Select your SCIM directory on the `/sso` page and enable synchronization. You can trigger manual syncs or configure automated periodic syncs.
  </Step>
</Steps>

### SCIM and Custom Roles / Teams

<Tabs>
  <Tab title="How It Works" icon="sitemap">
    IdP groups are mapped to Gumloop [Custom Roles](/enterprise-features/user_groups) and/or **teams (projects)**, independently per direction. When users are synced, they are assigned the **union** of every matched mapping.

    **Important considerations:**

    * If a direction's mapping table is empty (and **Use mapping table** is on), SCIM leaves that direction's assignments alone for existing users.
    * Create groups in your IdP first, then map them to Gumloop custom roles or teams.
    * Group names don't need to match exactly when the mapping table is on — you define the mapping by selecting the Gumloop entity per row.
    * If mappings exist but no IdP group matches, the user is placed in the **default custom role** (and the **default team** for new provisions).
  </Tab>

  <Tab title="Union Semantics" icon="users">
    A user in multiple mapped IdP groups receives the **union** of every matched role and the union of every matched team. There is no priority — every mapping that matches the user's IdP groups contributes.

    Example: if `Engineering → Eng-Role` and `On-Call → On-Call-Role` are both configured and the user belongs to both IdP groups, the user is assigned **both** `Eng-Role` and `On-Call-Role`.

    To grant a single specific role, ensure the user belongs to exactly one mapped IdP group. To revoke SCIM-managed access, remove the user from the SCIM directory (which triggers the deprovisioning path) — clearing the mapping table will not strip existing users while the **Use mapping table** toggle remains on.
  </Tab>

  <Tab title="Name-Based Mode" icon="wand-magic-sparkles">
    Toggling **Use mapping table** off for a direction switches that direction to name-based resolution:

    * Each IdP group's display name is matched (case- and whitespace-insensitive) against existing Gumloop custom role / team names.
    * **Hit:** the user is assigned that existing entity.
    * **Miss:** a new custom role or team is **auto-created** using the IdP group's name on the next sync.

    Within-org name uniqueness is enforced at create/rename time, so name-based resolution is deterministic. SCIM treats the IdP as authoritative in this mode — users whose IdP groups don't resolve to any Gumloop entity have their roles/teams **wiped**. The UI requires explicit confirmation before switching to name-based mode.

    For team sync only: name-based mode is authoritative **across organizations** — a user's cross-org team memberships not represented in the IdP target set are also removed. SCIM can still only **add** users to teams in the synced org.
  </Tab>
</Tabs>

### Sync Operations

| Trigger       | Description                                    |
| ------------- | ---------------------------------------------- |
| **Scheduled** | Automatic periodic sync (every 15 minutes)     |
| **Manual**    | On-demand sync triggered by organization admin |

### Pre-Provisioned Users

Users assigned to Gumloop in your IdP are visible in your organization's member list before they log in for the first time. This enables:

* Advance seat planning
* Pre-assigning users to teams
* Visibility into pending onboarding

<Info>
  Pre-provisioned users don't consume active seats until they complete their first login.
</Info>

### SCIM Best Practices

<CardGroup cols={2}>
  <Card title="Map Groups If Needed" icon="layer-group">
    Configure role and team mappings only when you want SCIM to manage those assignments. With **Use mapping table** on and the table empty, users keep their current Gumloop memberships.
  </Card>

  <Card title="Prefer Mapping Table Mode" icon="table">
    Default mapping-table mode is non-destructive — users with no matching IdP group are left alone. Use name-based mode only when your IdP is authoritative and you accept that descoped users will lose roles/teams.
  </Card>

  <Card title="Confirm Before Going Name-Based" icon="triangle-exclamation">
    Switching a direction off the mapping table will wipe roles/teams for users whose IdP groups don't match any Gumloop entity on the next sync. The UI gates this behind a confirmation modal — read it.
  </Card>

  <Card title="Test with Pilot Group" icon="flask">
    Enable SCIM for a small test group before rolling out to the entire organization.
  </Card>

  <Card title="Monitor Audit Logs" icon="clipboard-list">
    Review SCIM-related audit events to verify provisioning, mapping changes, and auto-creates land as expected.
  </Card>

  <Card title="Disable = Fresh Start" icon="rotate-left">
    Disabling SCIM clears all mapping tables, per-direction toggles, and SCIM tracking rows. Re-enabling starts clean — users remain in the org as if added manually.
  </Card>
</CardGroup>

### SCIM Audit Events

SCIM operations are tracked in your organization's [audit logs](/enterprise-features/audit_logging):

| Event                                | Description                                                                              |
| ------------------------------------ | ---------------------------------------------------------------------------------------- |
| `SCIM_SYNC_STARTED`                  | Directory sync operation initiated                                                       |
| `SCIM_SYNC_COMPLETED`                | Sync completed with summary stats                                                        |
| `SCIM_SYNC_FAILED`                   | Sync failed with error details                                                           |
| `SCIM_SYNC_ENABLED`                  | SCIM sync enabled for the organization                                                   |
| `SCIM_SYNC_DISABLED`                 | SCIM sync disabled for the organization                                                  |
| `SCIM_USER_PROVISIONED`              | New user provisioned via SCIM                                                            |
| `SCIM_USER_DEPROVISIONED`            | User removed via SCIM                                                                    |
| `SCIM_USER_PERMISSION_GROUP_CHANGED` | User's custom-role assignments updated (union of mapped roles)                           |
| `SCIM_USER_TEAM_CHANGED`             | User's team memberships updated (union of mapped teams)                                  |
| `SCIM_GROUP_MAPPING_UPDATED`         | Curated role mappings table replaced                                                     |
| `SCIM_TEAM_MAPPING_UPDATED`          | Curated team mappings table replaced                                                     |
| `SCIM_AUTO_CREATED_ENTITY`           | A new role or team was auto-created from an unmatched IdP group name (name-based mode)   |
| `SCIM_USE_MAPPING_TABLE_CHANGED`     | A per-direction toggle (role or team) flipped between mapping-table and name-based modes |

***

## Security & Compliance

Gumloop's SSO implementation follows industry security standards:

<CardGroup cols={2}>
  <Card title="SOC 2 Type II" icon="certificate">
    Certified compliance with SOC 2 Type II controls for security, availability, and confidentiality
  </Card>

  <Card title="SAML 2.0" icon="lock">
    Industry-standard SAML 2.0 protocol for secure assertion exchange
  </Card>

  <Card title="Encrypted Transit" icon="shield-halved">
    All authentication traffic encrypted via TLS 1.3
  </Card>

  <Card title="Session Management" icon="clock">
    Configurable session timeouts and secure token handling
  </Card>
</CardGroup>

<Tip>
  For detailed security information and certifications, visit [trust.gumloop.com](https://trust.gumloop.com/).
</Tip>

***

## Related Resources

<CardGroup cols={2}>
  <Card title="Custom Roles" icon="user-shield" href="/enterprise-features/user_groups">
    Configure granular permissions for synced users
  </Card>

  <Card title="Audit Logging" icon="clipboard-list" href="/enterprise-features/audit_logging">
    Monitor authentication and provisioning events
  </Card>

  <Card title="Okta Integration" icon="key" href="/core-concepts/okta-integration">
    Configure Okta for service authentication (Snowflake, NetSuite)
  </Card>

  <Card title="Organization Roles" icon="building" href="/core-concepts/organization_user_roles">
    Understand organization member roles and permissions
  </Card>
</CardGroup>

***

## Need Help?

* **Setup Assistance:** Contact [support@gumloop.com](mailto:support@gumloop.com)
* **SCIM Enablement:** Request via [support@gumloop.com](mailto:support@gumloop.com)
* **Identity Provider Docs:** [SSOReady Configuration Guides](https://ssoready.com/docs/idp-configuration/guides-for-common-identity-providers)
