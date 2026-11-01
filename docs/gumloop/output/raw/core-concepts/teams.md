> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Organization and Teams

<Info>
  Organizations and Teams are available only on the **Pro plan** and above.
</Info>

## At a Glance

**Think of Gumloop like Google Workspace:**

<CardGroup cols={3}>
  <Card title="Organization" icon="building">
    Your company's account that manages billing, users, and security
  </Card>

  <Card title="Personal Space" icon="user">
    Your private space for building and running agents and flows. Only you can access it.
  </Card>

  <Card title="Team" icon="users">
    A shared space where multiple people can view, edit, and run flows & agents together
  </Card>
</CardGroup>

<Tip>
  Everyone in your organization shares the same credit pool. Start with your personal space for most work, and create teams when you need true collaboration.
</Tip>

***

## Personal vs Team

Most flows & agents should live in your personal space. Create teams only when collaboration is essential.

|                                  | **Personal** (Recommended)                                                                                                   | **Team**                                                                                                               |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Who can see agents & flows?**  | Only you by default. You can [share with specific users](/core-concepts/share_permissions) as Editors, Viewers, or Use Only. | All team members can view by default (Viewer access), with options to share more broadly                               |
| **Who can edit agents & flows?** | Only you by default. You can share with others as Editors.                                                                   | Team members need Editor access to edit — grant it via the Share dialog or General Access (not given by default)       |
| **Who can run agents & flows?**  | You directly; others you've shared with (based on their role) or via shared interfaces                                       | All team members can chat with team agents; running workflows requires Editor access. Others via sharing or interfaces |
| **Best for**                     | Most agents and flows, development, testing, production                                                                      | True collaboration where multiple people maintain the same agents and flows                                            |
| **Available on**                 | All plans                                                                                                                    | Pro plan and above                                                                                                     |

<Tabs>
  <Tab title="Personal Space" icon="star">
    Every user gets a personal space automatically when they sign up. **This is where most of your work should happen.**

    * **Fast and flexible**: Build without worrying about breaking others' work
    * **Secure by default**: Your agents, flows, and data stay private
    * **Production-ready**: Run production agents and flows that you maintain
    * **Safe experimentation**: Test ideas without affecting your team

    <Info>
      Your personal space is private by default, but you can share any agent or flow with specific users by email. Choose their role: **Editor** (full access), **Viewer** (read-only), or for agents, **Use Only** (chat only). You can also set General Access to share with your entire organization. Learn more about [share permissions](/core-concepts/share_permissions).
    </Info>
  </Tab>

  <Tab title="Team" icon="users">
    Teams let multiple members work on the same agents and flows together. **Use these when true collaboration is essential.**

    * **Multiple editors**: Several people can be granted Editor access to maintain the same agent or flow
    * **Shared visibility**: Team members get Viewer access to team agents and flows by default
    * **Team apps**: Everyone uses the same connected integrations
    * **Department resources**: Agents and flows used across an entire team

    <Warning>
      All team members get Viewer (read-only) access to team agents and flows by default. To let members edit, grant them Editor access through the Share dialog or broaden access via General Access settings. You can also share with users outside the team. Use teams intentionally, not by default.
    </Warning>
  </Tab>
</Tabs>

***

## Understanding Organizations

An **Organization** is your company's top-level account. It's the umbrella that holds everything together.

<AccordionGroup>
  <Accordion title="Billing & Credits" icon="credit-card">
    All users share one credit pool. When anyone runs an agent or flow, whether in their personal space or a team, it deducts from the organization's credits.

    There's no separate billing per team or per user. One unified account.
  </Accordion>

  <Accordion title="User Management" icon="user-group">
    * Anyone with your company email domain automatically joins your organization (if [domain whitelisting](https://docs.gumloop.com/core-concepts/organization_user_roles) is enabled)
    * **Invite users by email** — invited users appear with a "Pending" status until they accept
    * Organization admins can **resend** or **revoke** pending invitations
    * Add, remove, and manage user permissions across all teams
    * Set which team new members join by default

    <Frame>
      <img src="https://mintcdn.com/agenthub/5j7_6MWAeY7rv44A/images/pending_invite_actions.png?fit=max&auto=format&n=5j7_6MWAeY7rv44A&q=85&s=d6b1fd30faba5436f14b1b5fd80324c6" alt="Organization members list showing a pending invite with Resend Invite and Revoke Invite actions" width="2370" height="478" data-path="images/pending_invite_actions.png" />
    </Frame>

    <Tip>
      Gumloop's roles are **additive**, so a user can hold multiple roles at once (for example, `{Member, Analytics, Templates}`). Use the **Manage Roles** sheet on the members page to add or remove individual roles instead of promoting or demoting users.
    </Tip>

    [Learn more about user roles and permissions →](/core-concepts/organization_user_roles)
  </Accordion>

  <Accordion title="Settings & Compliance" icon="gear">
    * Control security settings across all teams
    * Manage data retention policies
    * Set organization-wide defaults
    * Monitor usage across all teams
  </Accordion>
</AccordionGroup>

[View Organization Settings →](https://www.gumloop.com/settings/organization/general)

<div align="center">
  <img src="https://mintcdn.com/agenthub/66M6srYbC0Oj5XyU/images/organization-overview.png?fit=max&auto=format&n=66M6srYbC0Oj5XyU&q=85&s=3e311ec530fc2fce6c4de90b01a19fcb" alt="Organization overview" width="900" data-path="images/organization-overview.png" />
</div>

***

## Creating a Team

There are two ways to create a team.

### Option 1: From the Sidebar

On the [Home page](https://www.gumloop.com/hub), find the **Teams** section in the sidebar and click the **+** icon.

<Frame>
  <img src="https://mintcdn.com/agenthub/1Km17aHtxdoqe1vs/images/create_team.png?fit=max&auto=format&n=1Km17aHtxdoqe1vs&q=85&s=4e94f77bcc10c73960ffaa81f921a823" alt="Click the plus icon in the Teams sidebar section" width="502" height="260" data-path="images/create_team.png" />
</Frame>

Give your team a name, choose a logo, and pick a brand color. Then click **Create**.

<Frame>
  <img src="https://mintcdn.com/agenthub/1Km17aHtxdoqe1vs/images/create_team_dialog.png?fit=max&auto=format&n=1Km17aHtxdoqe1vs&q=85&s=0c6ac30cde27ad4035dd11ddda0f1491" alt="New Team dialog with name, logo, and brand color options" width="1260" height="880" data-path="images/create_team_dialog.png" />
</Frame>

### Option 2: From Organization Settings

Go to [Settings → Teams](https://www.gumloop.com/settings/organization/teams) and click the **Create** button.

<Frame>
  <img src="https://mintcdn.com/agenthub/1Km17aHtxdoqe1vs/images/create_team_settings.png?fit=max&auto=format&n=1Km17aHtxdoqe1vs&q=85&s=57795829a230dcb743dc0ca6db9c68e9" alt="Create a team from organization settings" width="2388" height="454" data-path="images/create_team_settings.png" />
</Frame>

***

## Adding Team Members

Once you've created a team, there are three ways to add members.

### Option 1: Invite to Team from the Sidebar (Quickest)

On the [Home page](https://www.gumloop.com/hub), **right-click** on your team in the sidebar and select **Invite to Team**. This opens the invite dialog directly, letting you add members without navigating to settings.

<Frame>
  <img src="https://mintcdn.com/agenthub/eaG8VVzW0XPOKVzE/images/team_invite_right_click.png?fit=max&auto=format&n=eaG8VVzW0XPOKVzE&q=85&s=afdfbef1ad60ff3cac20c843eb3e5d18" alt="Right-click a team to see Settings, Invite to Team, and Leave team options" width="350" data-path="images/team_invite_right_click.png" />
</Frame>

Enter the email address of the person you want to invite. They'll receive an invite and will be added automatically once they accept.

### Option 2: From Team Settings

On the [Home page](https://www.gumloop.com/hub), **right-click** on your team in the sidebar and select **Settings**.

<Frame>
  <img src="https://mintcdn.com/agenthub/eaG8VVzW0XPOKVzE/images/team_invite_right_click.png?fit=max&auto=format&n=eaG8VVzW0XPOKVzE&q=85&s=afdfbef1ad60ff3cac20c843eb3e5d18" alt="Right-click a team to access Settings" width="350" data-path="images/team_invite_right_click.png" />
</Frame>

Click the **Add Member** button and enter their email address. They'll receive an invite and will be added automatically once they accept.

<Frame>
  <img src="https://mintcdn.com/agenthub/EokesKd56_c0JgOx/images/team_add_members.png?fit=max&auto=format&n=EokesKd56_c0JgOx&q=85&s=78b8f94685ed9323a0cace2eb9744096" alt="Team settings page showing members and Add Member button" width="3024" height="1722" data-path="images/team_add_members.png" />
</Frame>

### Option 3: From Organization Settings

1. Go to [Settings → Teams](https://www.gumloop.com/settings/organization/teams)
2. Click on the team you want to manage
3. Click **Add Member** in the Team Members section

***

## Connecting Team Apps

Team apps are shared integrations and API keys that all team members can use when running agents and flows. There are two ways to connect apps for a team.

### Option 1: Right-Click from the Sidebar

On the [Home page](https://www.gumloop.com/hub), **right-click** on your team in the sidebar and select **Settings**.

<Frame>
  <img src="https://mintcdn.com/agenthub/eaG8VVzW0XPOKVzE/images/team_invite_right_click.png?fit=max&auto=format&n=eaG8VVzW0XPOKVzE&q=85&s=afdfbef1ad60ff3cac20c843eb3e5d18" alt="Right-click a team to access Settings" width="350" data-path="images/team_invite_right_click.png" />
</Frame>

Then navigate to the **Apps** tab.

Click **Connect New App** to add a new integration.

<Frame>
  <img src="https://mintcdn.com/agenthub/EokesKd56_c0JgOx/images/team_settings_credentials.png?fit=max&auto=format&n=EokesKd56_c0JgOx&q=85&s=a85f68a434b75a598c32df1cae7defdb" alt="Team Connectors page with Connect New App button" width="3024" height="1722" data-path="images/team_settings_credentials.png" />
</Frame>

### Option 2: From Organization Settings

1. Go to [Settings → Teams](https://www.gumloop.com/settings/organization/teams)
2. Click on the team, then select **Connectors** in the sidebar
3. Click **Connect New App**

<Tip>
  Team apps are available to all team members. For personal integrations that only you use, connect them from your personal [Connectors page](https://www.gumloop.com/personal/connectors) instead.
</Tip>

***

## Moving Agents & Flows Between Teams

You can move agents and flows between your personal space and teams:

1. Go to the [Home page](https://www.gumloop.com/hub)
2. Click the three dots (⋮) next to the agent or flow
3. Select **Move to Team**
4. Choose the destination team

You can always move them back if needed. Once in a team, all members can edit them until you move them elsewhere.

***

## Setting a Default Team

Organization admins can choose which team new members automatically join from the [Teams settings page](https://www.gumloop.com/settings/organization/teams).

<div align="center">
  <img src="https://mintcdn.com/agenthub/KMa3S5LrPuj3opZR/images/default-workspace.png?fit=max&auto=format&n=KMa3S5LrPuj3opZR&q=85&s=e403e0e34836c15384a7286e0864a96e" alt="Default team settings" width="600" data-path="images/default-workspace.png" />
</div>

When someone with your company email signs up:

1. They automatically join your organization
2. They get their personal space
3. They get access to the default team
4. They can request access to other teams

***

## Common Questions

<AccordionGroup>
  <Accordion title="I shared a link. What can others do with it?" icon="share-nodes">
    What others can do depends on their **access level**, which you control through the Share dialog:

    * **No access / not shared**: They can see a preview but cannot interact. They can request access from the owner.
    * **Use Only** (agents only): They can chat with the agent but cannot view its configuration.
    * **Viewer**: They can view the agent or flow but cannot edit it. They can make a copy to their own space.
    * **Editor**: They can view and edit the agent or flow.

    You can also set **General Access** to give your entire team, organization, or anyone with the link a specific role. For public links (Anyone), unauthenticated users are capped at Viewer access.

    <Tip>
      Want someone to just use your automation without seeing how it works? Share the agent with **Use Only** access or share it as an interface.
    </Tip>
  </Accordion>

  <Accordion title="Where should I build my agents and flows?" icon="hammer">
    **Always start in your personal space.** Move to a team only if multiple people need to actively edit the same agent or flow.

    **Keep in personal space if:**

    * You're the primary maintainer
    * It's still in development or testing
    * Others just need to run it (share via interface)

    **Move to a team if:**

    * Multiple people actively edit the same agent or flow
    * The team collectively maintains it
    * You need shared team apps
    * Team members need to cover for each other
  </Accordion>

  <Accordion title="Who pays for agents and flows in different teams?" icon="credit-card">
    **Your organization pays for everything.** Whether an agent or flow runs from your personal space or a team, it uses your organization's credit pool. Teams only affect who can access and edit agents and flows, not billing.
  </Accordion>
</AccordionGroup>

***

## Related Documentation

<CardGroup cols={3}>
  <Card title="Apps & Credentials" icon="puzzle-piece" href="https://docs.gumloop.com/core-concepts/credentials">
    Set up personal and team apps
  </Card>

  <Card title="Credit Usage & Billing" icon="credit-card" href="https://docs.gumloop.com/core-concepts/credits">
    How credits work across teams
  </Card>

  <Card title="User Roles" icon="shield" href="https://docs.gumloop.com/core-concepts/organization_user_roles">
    Organization and team permissions
  </Card>
</CardGroup>
