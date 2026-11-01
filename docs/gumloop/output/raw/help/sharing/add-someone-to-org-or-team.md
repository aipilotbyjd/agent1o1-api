> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Add Someone to Your Org or Team

You need **Admin** or **Manager**. Regular Members cannot invite others.

## Add someone to the organization

1. Go to [Organization Members](https://www.gumloop.com/settings/organization/members).
2. Click **Add Member**.
3. Enter their email.
4. Optionally pick extra [organization roles](/core-concepts/organization_user_roles) and [teams](/core-concepts/teams). Every invitee already gets **Member**.
5. Send the invite. They show as **Pending** until they accept.

<Frame>
  <img src="https://mintcdn.com/agenthub/w3Ko1Zf8Ohe9VqZL/images/add-member-to-organization.png?fit=max&auto=format&n=w3Ko1Zf8Ohe9VqZL&q=85&s=4e5bb80e6745b79b22c8bd566c167b82" alt="Add Member to Organization modal with email, roles, and teams" width="1438" height="1332" data-path="images/add-member-to-organization.png" />
</Frame>

From the members list you can **Resend Invite** or **Revoke Invite** on a pending row.

<Note>
  If your org uses domain restrictions or SSO, people on a blocked domain cannot join. See [SSO, SAML & SCIM](/enterprise-features/sso_saml_scim).
</Note>

## Add someone to a team

You can pick teams in the org invite modal above, or invite from the team itself.

**From the sidebar**

1. On the [Home page](https://www.gumloop.com/hub), right-click the team.
2. Select **Invite to Team**.
3. Enter their email.

<Frame>
  <img src="https://mintcdn.com/agenthub/eaG8VVzW0XPOKVzE/images/team_invite_right_click.png?fit=max&auto=format&n=eaG8VVzW0XPOKVzE&q=85&s=afdfbef1ad60ff3cac20c843eb3e5d18" alt="Right-click a team to invite members" width="638" height="352" data-path="images/team_invite_right_click.png" />
</Frame>

**From team settings**

1. Right-click the team and open **Settings**.
2. Click **Add Member** and enter their email.

<Frame>
  <img src="https://mintcdn.com/agenthub/EokesKd56_c0JgOx/images/team_add_members.png?fit=max&auto=format&n=EokesKd56_c0JgOx&q=85&s=78b8f94685ed9323a0cace2eb9744096" alt="Team settings page with Add Member" width="3024" height="1722" data-path="images/team_add_members.png" />
</Frame>

**From organization settings**

Open the team under [Organization > Teams](https://www.gumloop.com/settings/organization/teams) and add them in **Team Members**.

## Good to know

* Inviting someone to a team does not make them Editor on every agent. Share each agent with the role you want. See [Sharing roles](/help/sharing/sharing-roles).
* Org roles are additive. Change them later from [Organization Members](https://www.gumloop.com/settings/organization/members). That is separate from an agent's Editor / Viewer / Use Only role.

## Related

<CardGroup cols={2}>
  <Card title="User Roles" icon="id-badge" href="/core-concepts/organization_user_roles">
    Admin, Manager, Member
  </Card>

  <Card title="Create a team" icon="users" href="/help/sharing/create-a-team">
    Create the team first
  </Card>
</CardGroup>
