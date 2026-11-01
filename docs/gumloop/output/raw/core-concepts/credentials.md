> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Connectors

## Overview

Gumloop connects to external services like Gmail, Slack, Salesforce, and more. **Connectors** are how you authenticate these connections securely. You can connect multiple accounts for each service and choose which one to use in each agent or flow.

<CardGroup cols={2}>
  <Card title="Personal Connectors" icon="user">
    Private to you. Perfect for individual work, testing, and personal accounts.
  </Card>

  <Card title="Team Connectors" icon="users">
    Shared with your team. Ideal for collaborative agents and flows where everyone uses the same account.
  </Card>
</CardGroup>

<Tip>
  Start with personal connectors for most work. Use team connectors only when your team needs to run agents and flows with shared accounts.
</Tip>

### Personal vs Team Connectors

|                         | **Personal Connectors**                     | **Team Connectors**                 |
| ----------------------- | ------------------------------------------- | ----------------------------------- |
| **Who can use?**        | Only you                                    | All team members                    |
| **Where do they work?** | Any space (personal or team)                | Specific team only                  |
| **Default in nodes?**   | Yes, automatic default                      | No, must be selected manually       |
| **Setup**               | One-time, works everywhere                  | Per team                            |
| **Best for**            | Individual work, testing, personal accounts | Team collaboration, shared accounts |
| **Privacy**             | Fully private, even in teams                | Shared with all team members        |

***

## Connect a Connector

Connect an account once, then reuse it across every agent and flow. You can connect a connector just for yourself (**personal**) or for everyone on a team (**team**).

### Connect a Personal Connector

<Steps>
  <Step title="Go to your Connectors page">
    Visit your [Connectors page](https://www.gumloop.com/personal/connectors) or navigate via **Settings → Connectors**
  </Step>

  <Step title="Click Add Connector">
    Select the service you want to connect (Gmail, Slack, OpenAI, etc.)
  </Step>

  <Step title="Authenticate">
    **OAuth (most services):** Click "Connect" and follow the authorization flow. No manual token management needed. Examples: Gmail, Slack, Microsoft services.

    **API Keys (some services):** Paste your API key directly. Examples: OpenAI, AWS, Anthropic.
  </Step>

  <Step title="Set as default (optional)">
    If you connect multiple accounts for the same service (e.g., three different Gmail accounts), you can choose which one is your default. If you only have one account connected for a service, it's automatically your default.
  </Step>
</Steps>

<div align="center">
  <img src="https://mintcdn.com/agenthub/0MIzwL1cHHBNpu7Y/images/credentials.png?fit=max&auto=format&n=0MIzwL1cHHBNpu7Y&q=85&s=d206a5744659ff9edd9af0e0fa42a74e" alt="Personal Connectors page" width="800" data-path="images/credentials.png" />
</div>

<Info>
  **Privacy guaranteed:** Even in teams, other members cannot see or use your personal connectors.
</Info>

### Connect a Team Connector

Team connectors are shared integrations that everyone on a specific team can use. They are scoped to that team only, and a team's default credential applies to all of its members. Set one up from the team's **Connectors** page (right-click the team in the sidebar, or go to **Settings → Teams → your team → Connectors**), then click **Add Connector**. For a step-by-step walkthrough, see [How do I add shared team credentials (team apps)?](https://support.gumloop.com/articles/7840097063-how-do-i-add-shared-team-credentials-team-apps) in the Knowledge Base.

***

## Choose Which Account to Use

A connector can have several connected accounts for the same service (for example, three Gmail logins). You pick which account is used, and the options depend on whether you are configuring an **agent** or a **flow**.

<Tip>
  **Rule of thumb:** by default, everyone uses their own account (**Use Personal Default**). Switch to a team account only when everyone should act as the *same* shared login, such as a support inbox or a team Google Drive.
</Tip>

### In Agents

Each connector connected to your agent has a credential menu (the three-dot icon). Agents can be **shared** with your organization or with specific people, and the account is resolved for whoever **runs** the agent, which is not always you.

**How to choose:**

<CardGroup cols={2}>
  <Card title="Everyone as themselves" icon="user">
    Pick **Use Personal Default**. Each person who runs the agent uses their own account, so data stays private to each user. This is the default.
  </Card>

  <Card title="Everyone as one shared login" icon="users">
    In a team agent, pick **Use Team Default** (or pin a team account). Every team member who runs the agent uses that same shared account.
  </Card>
</CardGroup>

<Info>
  **Which account does the agent run with?** It depends on the option you pick and who runs the agent:

  * **Use Personal Default** (the default for both personal and team agents): the agent uses the **running user's own** default account. Share the agent and each person uses their own account, so data stays private to each user.
  * **Use Specific Account on a personal agent** pins one of *your* personal accounts. It only applies when **you** run the agent. Anyone you share it with cannot use your personal account, so it falls back to *their* personal default.
  * **Use Team Default** or **Use Specific Account on a team agent** uses a shared **team** account, so every team member who runs the agent uses that same account. Anyone outside the team (for example, through an org-wide share) falls back to their own personal default.
  * **Agent-owned credentials** are the exception. When you explicitly pin an account as *agent-owned*, everyone who can access the agent uses that account when tools are called, including people you share it with. See [Agent-Owned Credentials](#agent-owned-credentials) below.
</Info>

#### Personal Agents

Personal agents offer two choices. A personal agent can still be shared, so the table shows which account is used when you run it versus when someone you shared it with runs it.

<Frame>
  <img src="https://mintcdn.com/agenthub/K_k-tD3TARMHQhGo/images/agent-credentials/personal-agent-credential-menu.png?fit=max&auto=format&n=K_k-tD3TARMHQhGo&q=85&s=22e9e488e899e0737675620272102b27" alt="Personal agent credential menu showing Use Personal Default and Use Specific Account options" width="878" height="572" data-path="images/agent-credentials/personal-agent-credential-menu.png" />
</Frame>

| Option                   | What it does                                                                         | Account used at runtime                                                        |
| ------------------------ | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------ |
| **Use Personal Default** | Uses the default account of whoever runs the agent. This is the default setting.     | Each person uses their own account                                             |
| **Use Specific Account** | Pins one of your personal accounts to this agent (e.g., one of two Outlook inboxes). | You: your pinned account. Anyone you share it with: their own personal default |

#### Team Agents

Team agents have a third option: **Use Team Default**. This lets all team members share one account without each person needing their own credentials for that service.

<Frame>
  <img src="https://mintcdn.com/agenthub/K_k-tD3TARMHQhGo/images/agent-credentials/team-agent-credential-menu.png?fit=max&auto=format&n=K_k-tD3TARMHQhGo&q=85&s=f2ec29c58411c78bb18da87c3429f41c" alt="Team agent credential menu showing Use Personal Default, Use Team Default, and Use Specific Account options" width="974" height="636" data-path="images/agent-credentials/team-agent-credential-menu.png" />
</Frame>

| Option                   | What it does                                                              | Account used at runtime                                                                 |
| ------------------------ | ------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| **Use Personal Default** | Uses the personal account of whoever runs the agent. The default setting. | Each person uses their own account                                                      |
| **Use Team Default**     | Uses the team's shared default account for this service.                  | All team members use the shared team account                                            |
| **Use Specific Account** | Pins one team-connected account so the agent always uses it.              | All team members use that team account (anyone outside the team uses their own default) |

<Warning>
  Changing a team agent's credential affects everyone on the team, so a confirmation dialog appears first. You can **cancel**, **make a personal copy** of the agent instead of changing the shared one, or **select a team account** to proceed.
</Warning>

<Frame>
  <img src="https://mintcdn.com/agenthub/K_k-tD3TARMHQhGo/images/agent-credentials/team-agent-change-confirmation.png?fit=max&auto=format&n=K_k-tD3TARMHQhGo&q=85&s=5896e9428e78038259e8e5ca8bc34f35" alt="Confirmation dialog asking if you want to change the account this team agent uses" width="1286" height="430" data-path="images/agent-credentials/team-agent-change-confirmation.png" />
</Frame>

Learn more about using agents with credentials in [Using Agents in Slack → Credentials & Authentication](https://docs.gumloop.com/core-concepts/agents_slack#credentials--authentication). For a step-by-step walkthrough, see [How do I assign a specific account to a specific agent?](https://support.gumloop.com/articles/5170418809-how-do-i-assign-a-specific-gmail-or-other-integration-account-to-a-specific-agent) in the Knowledge Base.

### Agent-Owned Credentials

By default, connectors are **user-owned**: each person who runs the agent uses the account available to them, and a specific account you pin only applies when *you* run the agent. **Agent-owned credentials** change this. When a connector is agent-owned, everyone who can access the agent uses the one account or connection you pinned, without getting direct access to the underlying credential.

<Info>
  **Selection and ownership are separate.** Choosing a specific account decides *which* account the agent uses. Ownership decides *whose* identity runs the tool. Selecting a specific account does not make it agent-owned on its own. You turn that on explicitly.
</Info>

#### Which ownership mode to use

<CardGroup cols={2}>
  <Card title="User-owned / User/Team-owned" icon="user">
    Each person who runs the agent acts as **themselves**, using the account available to them. Pick this when a shared agent should work on each user's own data and that data should stay private to each user. This is the default.
  </Card>

  <Card title="Agent-owned" icon="lock">
    The agent is **bound to one specific account**, and everyone who can access it acts as that account no matter who runs the agent. Pick this when you want to hardcode a credential to the agent, such as a shared service account or a support inbox, so the agent always behaves the same way for every user.
  </Card>
</CardGroup>

#### Turn on agent-owned for a connector

<Steps>
  <Step title="Select a specific account">
    In your agent's **Apps**, open the connector's detail view and select the specific account you want to pin.
  </Step>

  <Step title="Set Credential ownership">
    A **Credential ownership** control appears once a specific account is selected. Choose one of:

    * **User-owned** (labeled **User/Team-owned** on team agents): each user uses the accounts available to them when tools are called. This is the default.
    * **Agent-owned**: everyone using this agent uses the pinned credential when tools are called.

    <Frame>
      <img src="https://mintcdn.com/agenthub/NVlTbwlH5r2dqO3k/images/agent-credentials/credential-ownership-toggle.webp?fit=max&auto=format&n=NVlTbwlH5r2dqO3k&q=85&s=e748a8ad700246da85392578e1aac5c5" alt="Credential ownership section in a Gmail connector detail view showing the User-owned and Agent-owned options" width="1628" height="882" data-path="images/agent-credentials/credential-ownership-toggle.webp" />
    </Frame>

    Connectors set to agent-owned show an **Agent Owned** label in the agent's tool list.

    <Frame>
      <img src="https://mintcdn.com/agenthub/NVlTbwlH5r2dqO3k/images/agent-credentials/connector-agent-owned-label.webp?fit=max&auto=format&n=NVlTbwlH5r2dqO3k&q=85&s=acdd704d07bd3116e246e7537ba16374" alt="Agent connector list showing a connector labeled Agent Owned" width="802" height="220" data-path="images/agent-credentials/connector-agent-owned-label.webp" />
    </Frame>
  </Step>
</Steps>

<Warning>
  **Agent-owned credentials and `Anyone` public sharing cannot be used together.** A pinned credential would otherwise be exposed to the public. If the agent is already shared with `Anyone`, choosing **Agent-owned** asks you to change general access to a non-public level (organization, team, or restricted) first. Once an agent has an agent-owned credential, the `Anyone` option is disabled in its share dialog.
</Warning>

Agent-owned pins are tied to the workspace they live in. Cloning an agent, using it as a template, or moving it to another workspace removes the agent-owned pin, and the connector reverts to user-owned in the new location.

#### Enterprise availability

For organizations, agent-owned credentials are **restricted by default**. An admin turns them on per [custom role](/enterprise-features/user_groups) with the **Agent-owned credentials** feature toggle. Members whose roles do not grant it see the **Agent-owned** option disabled. You can only pin an account you have access to, and Gumloop re-checks that when the agent is saved.

<Info>
  Agent-owned credentials are **required** for agents that let Slack members without Gumloop accounts run them. Those people have no accounts of their own to authenticate with, so any user-owned connector fails for them. See [Organization Service Accounts for Slack Agents](/enterprise-features/slack_agent_access).
</Info>

***

### In Flows

Flows use the same connectors and accounts as agents. Credential selection works a little differently because it happens per node.

<Accordion title="Using connectors in flows" icon="diagram-project">
  In flows, every node that requires authentication has a **"Credentials to use"** dropdown. If you have multiple accounts connected for the same service (e.g., three Gmail accounts), you can pick exactly which one to use on each node.

  <div align="center">
    <img src="https://mintcdn.com/agenthub/Ikk0mS7ZAjXA5-_q/images/node_credentials.png?fit=max&auto=format&n=Ikk0mS7ZAjXA5-_q&q=85&s=bc36395e6736650072637ddff370523d" alt="Credential selection dropdown in a node" width="600" data-path="images/node_credentials.png" />
  </div>

  <Warning>
    All nodes default to your personal credential, even in teams. To use a team credential, you must manually select it from the dropdown on each node.
  </Warning>

  The dropdown offers three options:

  | Option                  | What it does                                                                                                                                                                                                                                                                                                          |
  | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
  | **Personal Default**    | Uses your default personal account for this service. Selected by default on all new nodes. Use it when working in your personal space, testing, or to use your own account inside a team.                                                                                                                             |
  | **Team Default**        | Uses the team's default account for this service. Must be manually selected. Use when everyone on the team should use the same shared account (e.g., [marketing@company.com](mailto:marketing@company.com)). If no team connector is set up for this service, the node will fail, so configure team connectors first. |
  | **Specific Credential** | Choose any specific account you've connected. Useful when you need different accounts for different parts of the same flow.                                                                                                                                                                                           |
</Accordion>

***

## Manage Your Connectors

<AccordionGroup>
  <Accordion title="Viewing your connectors" icon="list">
    Visit your [Connectors page](https://www.gumloop.com/personal/connectors) to see all your personal connectors, last refresh time, connected services, and defaults.
  </Accordion>

  <Accordion title="Refreshing a connector" icon="rotate">
    OAuth connectors automatically refresh when needed. If you see authentication errors:

    1. Go to your Connectors page
    2. Click **Reauthenticate** on the affected service
    3. Complete the authorization flow again
  </Accordion>

  <Accordion title="Removing a connector" icon="trash">
    1. Go to your Connectors page
    2. Find the connector to remove and click **Revoke**
    3. Confirm removal

    All auth tokens are removed immediately. Agents and flows using this connector will fail until you reconnect.
  </Accordion>

  <Accordion title="Setting defaults" icon="star">
    **Personal default:** Your go-to connector for a service. Used when "Personal Default" is selected in a node. Only affects your account.

    **Team default:** The team's primary connector for a service. Used when "Team Default" is selected. Applies to all team members.
  </Accordion>
</AccordionGroup>

***

## Admin Setup

Some services require an administrator to authorize Gumloop before anyone on the team can connect their own account.

### Microsoft Office Setup (Admin Only)

For organizations using Microsoft services (Teams, Outlook, Excel, Word, OneLake), **administrators must configure consent** in Microsoft Entra ID before users can authenticate.

<Tabs>
  <Tab title="Tenant-Wide Consent" icon="building">
    ### Option 1: Grant Admin Consent (Recommended)

    <Steps>
      <Step title="Access Microsoft Entra admin center">
        Go to [Microsoft Entra admin center](https://entra.microsoft.com)
      </Step>

      <Step title="Navigate to Enterprise apps">
        Navigate to **Entra ID > Enterprise apps > All applications**

        <div align="center">
          <img src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/entra-admin-consent-navigation.png?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=eb7f8dea4510bffacc4f6b0aa25fa790" alt="Navigate to Enterprise apps" width="600" data-path="images/entra-admin-consent-navigation.png" />
        </div>
      </Step>

      <Step title="Search for Gumloop">
        Search for Gumloop using client ID: `d3c2a9a5-7f60-40d1-a8ba-62bab546a0f3`

        <div align="center">
          <img src="https://mintcdn.com/agenthub/OIDhR9iY2uRNFi5X/images/gumloop-enterprise-app-search.png?fit=max&auto=format&n=OIDhR9iY2uRNFi5X&q=85&s=3646a3384111cd28c57b97f24930f9f2" alt="Search for Gumloop" width="600" data-path="images/gumloop-enterprise-app-search.png" />
        </div>
      </Step>

      <Step title="Grant consent">
        Under **Security > Permissions**, click **Grant admin consent**
      </Step>
    </Steps>
  </Tab>

  <Tab title="Admin Consent Workflow" icon="clipboard-check">
    ### Option 2: Enable User Requests

    <Steps>
      <Step title="Open admin consent settings">
        Go to **Entra ID > Enterprise apps > Consent and permissions > Admin consent settings**
      </Step>

      <Step title="Enable user requests">
        Set **Users can request admin consent** to **Yes**

        <div align="center">
          <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/admin-consent-toggle-yes.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=bbb507d163735758878e3a16cd0223c5" alt="Enable admin consent requests" width="600" data-path="images/admin-consent-toggle-yes.png" />
        </div>
      </Step>

      <Step title="Configure reviewers">
        Set up reviewers and approval workflow for user requests
      </Step>
    </Steps>
  </Tab>

  <Tab title="App Provisioning" icon="plus">
    ### If Gumloop Isn't in Your Tenant Yet

    Use this URL to provision the app (replace `{tenant}` with your tenant ID):

    ```text theme={"dark"}
    https://login.microsoftonline.com/{tenant}/adminconsent?client_id=d3c2a9a5-7f60-40d1-a8ba-62bab546a0f3
    ```
  </Tab>
</Tabs>

#### Required Permissions by Service

| Integration    | Key Permissions                                                   |
| -------------- | ----------------------------------------------------------------- |
| **Teams**      | `Team.ReadBasic.All`, `ChannelMessage.Read.All`, `Chat.ReadWrite` |
| **Outlook**    | `Mail.ReadWrite`, `Mail.Send`                                     |
| **Excel/Word** | `Files.ReadWrite`, `Sites.ReadWrite.All`                          |
| **OneLake**    | `https://storage.azure.com/user_impersonation`                    |

<Warning>
  **Admin required:** You must be a Global Administrator, Cloud Application Administrator, or Application Administrator to grant consent.
</Warning>

### Salesforce Setup (Admin Only)

As of September 2025, Salesforce introduced new security restrictions that require administrators to pre-install the Gumloop connected app before users can authenticate with their Salesforce instances. For more details, see the [official Salesforce documentation](https://help.salesforce.com/s/articleView?id=005132365\&type=1).

<Info>
  Gumloop is a **Salesforce Connected App**, not an AppExchange app. You will **not** find it in the Salesforce AppExchange marketplace. A Salesforce administrator needs to authorize the connection directly.
</Info>

<Tabs>
  <Tab title="Admin Connects Directly (Recommended)" icon="bolt">
    The easiest way is for a **Salesforce administrator** to authenticate directly from Gumloop:

    <Steps>
      <Step title="Go to your Connectors page">
        Visit the [Salesforce connectors page](https://www.gumloop.com/personal/connectors?provider=salesforce) in Gumloop
      </Step>

      <Step title="Click Connect and authorize">
        Click **Connect** next to Salesforce and sign in with your Salesforce admin account.
      </Step>

      <Step title="Gumloop is automatically added">
        The Gumloop connected app is automatically installed in your Salesforce organization. No additional setup needed.
      </Step>
    </Steps>

    <Tip>
      After the admin connects, all users in the Salesforce organization can authenticate their own accounts with Gumloop.
    </Tip>
  </Tab>

  <Tab title="Non-Admin User Flow" icon="user">
    If a **non-admin user** tries to connect before an admin has authorized:

    <Steps>
      <Step title="User attempts to connect">
        The user visits the [Salesforce connectors page](https://www.gumloop.com/personal/connectors?provider=salesforce) and clicks **Connect**
      </Step>

      <Step title="Admin approval required">
        Salesforce blocks the connection. The Salesforce admin will see a request to approve the Gumloop app in their admin console.
      </Step>

      <Step title="Admin approves the app">
        The admin navigates to **Setup > Apps > Connected Apps > Manage Connected Apps** in Salesforce and approves the pending request.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/HuhMdNdzXDcfecYF/images/salesforce-connected-app-setup.png?fit=max&auto=format&n=HuhMdNdzXDcfecYF&q=85&s=f794cbc252c0c10541d52c51e9d34179" alt="Salesforce connected app setup" width="600" data-path="images/salesforce-connected-app-setup.png" />
        </div>
      </Step>

      <Step title="User retries">
        After admin approval, the user can return and successfully complete the OAuth flow.
      </Step>
    </Steps>
  </Tab>
</Tabs>

#### Managing the Connected App in Salesforce

1. Go to **Setup > Apps > Connected Apps > Manage Connected Apps**
2. Find the Gumloop app to view or modify settings
3. Configure user access policies, IP restrictions, and session policies as needed

<Info>
  For more details, refer to the [Salesforce Connected App documentation](https://help.salesforce.com/s/articleView?id=sf.connected_app_overview.htm\&type=5).
</Info>

***

## Security & Compliance

<CardGroup cols={3}>
  <Card title="SOC 2 Type II" icon="shield-check">
    Certified secure infrastructure and processes
  </Card>

  <Card title="GDPR Compliant" icon="scale-balanced">
    Full compliance with data protection regulations
  </Card>

  <Card title="Trust Center" icon="file-shield" href="https://trust.gumloop.com/">
    View our complete security documentation
  </Card>
</CardGroup>

***

## Related Documentation

<CardGroup cols={2}>
  <Card title="Organization and Teams" icon="building" href="https://docs.gumloop.com/core-concepts/teams">
    Understand how personal spaces and teams work
  </Card>

  <Card title="User Roles & Permissions" icon="shield" href="https://docs.gumloop.com/core-concepts/organization_user_roles">
    Organization and team permission levels
  </Card>
</CardGroup>
