> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# User Roles

> Composable organization and team roles that determine what each member can do in Gumloop.

<Note>
  Organization roles are available on the **Pro plan and above**. Some feature roles and capabilities require the **Enterprise plan**.
</Note>

Gumloop's roles are **additive**. A member can hold **multiple roles per scope** (organization or team), and their effective permissions are the **union** of every role they hold. Every member implicitly holds the baseline **Member** role; layer additional roles on top to grant access to specific areas.

<Info>
  For granular, feature-by-feature restrictions (app allowlists, node denylists, concurrency limits), see [Custom Roles](/enterprise-features/user_groups). That is a separate, complementary system that restricts what organization roles grant. A user can hold multiple custom roles at the same time.
</Info>

## How it works

<CardGroup cols={2}>
  <Card title="Union of permissions" icon="plus" color="#5e35b1">
    A user with `{Member, Analytics}` can do everything Member and Analytics allow. Revoking one role does not remove permissions granted by another.
  </Card>

  <Card title="Member is implicit" icon="user" color="#5e35b1">
    Every org member automatically holds **Member**. It is not shown in the Manage Roles picker and cannot be removed without removing the user from the org.
  </Card>

  <Card title="Scopes are independent" icon="sitemap" color="#5e35b1">
    Organization roles apply across the org. Team roles apply inside a single team. A user can be a Team Admin on one team and a Team Member on another.
  </Card>

  <Card title="Admin vs Feature roles" icon="layer-group" color="#5e35b1">
    **Admin roles** (Admin, Manager) grant broad authority. **Feature roles** (Security, Developer, Analytics) grant scoped access to one area.
  </Card>
</CardGroup>

## Organization Roles

<CardGroup cols={2}>
  <Card title="Admin" icon="crown" color="#ec407a" href="#admin">
    Full control: billing, SSO, members, and every feature area.
  </Card>

  <Card title="Manager" icon="user-gear" color="#2196f3" href="#manager">
    Member operations, credentials, and analytics. No billing or security.
  </Card>

  <Card title="Member" icon="user" color="#4caf50" href="#member">
    Implicit baseline. Use agents, skills, flowbooks, and personal credentials.
  </Card>

  <Card title="Security" icon="shield-halved" color="#f57c00" href="#security">
    Custom roles, app policies, AI model access, and [App Activity](/enterprise-features/app_activity).
  </Card>

  <Card title="Developer" icon="code" color="#7b1fa2" href="#developer">
    [Hosted MCPs](/enterprise-features/hosted_mcps) and [Proxied MCPs](/enterprise-features/proxied_mcps). Requires Enterprise.
  </Card>

  <Card title="Analytics" icon="chart-line" color="#00796b" href="#analytics">
    Organization analytics, usage, and data export.
  </Card>
</CardGroup>

***

### Admin

<Info>
  **Role ID:** `admin`  ·  **Group:** Admin  ·  **Scope:** Organization
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * Billing, subscription, and credit limits
    * SSO, SAML, and SCIM
    * All member operations
    * Organization credentials
    * Team access and team settings
    * Audit logs
    * AI model access, app policies, custom roles
    * Analytics, usage, and data export
    * [App Activity](/enterprise-features/app_activity), [Hosted MCPs](/enterprise-features/hosted_mcps), and [Proxied MCPs](/enterprise-features/proxied_mcps) (when enabled)
  </Card>

  <Card title="Can assign" icon="user-plus">
    Admin, Manager, Security, Developer, Analytics.
  </Card>
</CardGroup>

**Cannot do:** Nothing. Admin has full authority, so treat it as a break-glass role.

<Tip>
  **When to assign:** Organization owners, finance leads, and IT admins. Keep the count small since Admin includes billing and SSO.
</Tip>

***

### Manager

<Info>
  **Role ID:** `manager`  ·  **Group:** Admin  ·  **Scope:** Organization
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * Invite, remove, and manage members
    * Organization credentials
    * Analytics, usage, and data export
  </Card>

  <Card title="Can assign" icon="user-plus">
    Analytics, Member.
  </Card>
</CardGroup>

**Cannot do:** Change billing, SSO, AI model access, app policies, custom roles, or audit logs. Cannot grant Admin, Manager, Security, or Developer.

<Tip>
  **When to assign:** Team leads and ops managers who handle day-to-day onboarding and need usage visibility without billing or security authority.
</Tip>

***

### Member

<Info>
  **Role ID:** `member`  ·  **Group:** Feature  ·  **Scope:** Organization and Team  ·  **Baseline:** Yes
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * Create and use agents, skills, flowbooks, and custom operators
    * Read organization metadata
    * Create teams
    * Manage personal credentials
    * Leave the organization or a team
  </Card>

  <Card title="Can assign" icon="user-plus">
    Nothing.
  </Card>
</CardGroup>

**Cannot do:** Any management action: billing, members, credentials, analytics, or security controls.

<Tip>
  **When to assign:** Automatic. Every organization member holds Member implicitly. It cannot be removed without removing the user from the organization.
</Tip>

***

### Security

<Info>
  **Role ID:** `security`  ·  **Group:** Feature  ·  **Scope:** Organization  ·  **Plan:** Enterprise
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * [Custom Roles](/enterprise-features/user_groups)
    * [App Policies](/enterprise-features/app-policies/overview)
    * [AI Model Access Controls](/enterprise-features/ai_model_control)
    * [App Activity](/enterprise-features/app_activity), [Hosted MCPs](/enterprise-features/hosted_mcps), and [Proxied MCPs](/enterprise-features/proxied_mcps) (on enabled orgs)
    * [Slack workspaces and the organization service account](/enterprise-features/slack_agent_access)
  </Card>

  <Card title="Can assign" icon="user-plus">
    Developer.
  </Card>
</CardGroup>

**Cannot do:** Billing, SSO, member management, or audit logs. Cannot grant Admin, Manager, or Security.

<Tip>
  **When to assign:** Security engineers, platform leads, and compliance owners who configure guardrails without taking on billing or SSO.
</Tip>

***

### Developer

<Info>
  **Role ID:** `developer`  ·  **Group:** Feature  ·  **Scope:** Organization  ·  **Requires:** Enterprise
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * [Hosted MCPs](/enterprise-features/hosted_mcps) and [Proxied MCPs](/enterprise-features/proxied_mcps)
    * [App Activity](/enterprise-features/app_activity) for their own servers and tools
    * Standard content (agents, skills, flowbooks, custom operators) via Member
  </Card>

  <Card title="Can assign" icon="user-plus">
    Nothing.
  </Card>
</CardGroup>

**Cannot do:** Any organization management action. Cannot view audit logs or organization-wide [App Activity](/enterprise-features/app_activity).

<Tip>
  **When to assign:** Builders and integration engineers who need to develop and test [Hosted MCPs](/enterprise-features/hosted_mcps) and [Proxied MCPs](/enterprise-features/proxied_mcps). Granted by Admin or Security.
</Tip>

<Warning>
  Developer is hidden in the Manage Roles UI if Hosted MCPs and Proxied MCPs are not enabled on the organization.
</Warning>

***

### Analytics

<Info>
  **Role ID:** `analytics`  ·  **Group:** Feature  ·  **Scope:** Organization  ·  **Plan:** Enterprise
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * Organization analytics dashboard
    * Usage limits and credit consumption
    * Data export
  </Card>

  <Card title="Can assign" icon="user-plus">
    Nothing.
  </Card>
</CardGroup>

**Cannot do:** Member management, credentials, security controls, or billing.

<Tip>
  **When to assign:** Finance, FP\&A, and data analysts who need usage visibility without member management authority. Granted by Admin or Manager.
</Tip>

## Team Roles

Teams use a simpler two-role system.

### Team Admin

<Info>
  **Role ID:** `admin` (team scope)  ·  **Scope:** Team
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    * All team content (agents, flowbooks, skills, custom operators)
    * Team credentials
    * Team analytics
    * Team membership
  </Card>

  <Card title="Can assign" icon="user-plus">
    Team Admin, Team Member.
  </Card>
</CardGroup>

**Cannot do:** Anything outside the team. Team roles do not grant organization-level authority.

<Tip>
  **When to assign:** People who own a team's content end-to-end, including onboarding teammates and managing credentials.
</Tip>

### Team Member

<Info>
  **Role ID:** `member` (team scope)  ·  **Scope:** Team  ·  **Baseline:** Yes
</Info>

<CardGroup cols={2}>
  <Card title="Can access" icon="circle-check">
    Read access to team content.
  </Card>

  <Card title="Can assign" icon="user-plus">
    Nothing.
  </Card>
</CardGroup>

**Cannot do:** Manage team membership, credentials, or team roles.

<Tip>
  **When to assign:** Automatic. Every team member holds Team Member implicitly.
</Tip>

<Info>
  **Organization ceiling:** org **Admins** hold `organization:manage_team_access`, which lets them manage team memberships and team roles on every team in the organization, regardless of their team-level role. This is how org admins unblock access issues.
</Info>

## Managing Roles

Roles are assigned and revoked individually from the **Manage Roles** sheet. You pick the exact combination of roles the user should hold. This is not a promote or demote action.

<Frame>
  <img src="https://mintcdn.com/agenthub/w3Ko1Zf8Ohe9VqZL/images/manage-roles-sheet.png?fit=max&auto=format&n=w3Ko1Zf8Ohe9VqZL&q=85&s=b67c14a7d98d1d7e6ffc65c94fa6b3c9" alt="Manage Roles sheet showing Admin and Feature role groups with checkboxes, each role paired with a View details link." width="1297" height="1952" data-path="images/manage-roles-sheet.png" />
</Frame>

<Steps>
  <Step title="Open the members page">
    Go to [Organization Members](https://www.gumloop.com/settings/organization/members) or a team's **Members** tab.
  </Step>

  <Step title="Open Manage Roles">
    Click the three-dot menu next to the member and choose **Manage Roles**. The sheet opens with every role the member currently holds pre-selected.
  </Step>

  <Step title="Toggle and save">
    Check or uncheck any available role and click **Save**. Roles you are not authorized to assign are hidden. Effective permissions update immediately.
  </Step>
</Steps>

### Adding a new member with roles

Pre-assign organization roles when you invite someone so they land with the right permissions as soon as they accept.

<Frame>
  <img src="https://mintcdn.com/agenthub/w3Ko1Zf8Ohe9VqZL/images/add-member-to-organization.png?fit=max&auto=format&n=w3Ko1Zf8Ohe9VqZL&q=85&s=4e5bb80e6745b79b22c8bd566c167b82" alt="Add Member to Organization modal with fields for email, a multi-select Roles picker showing Member, Manager, Security selected, a Custom Roles selector, and a Teams selector." width="1438" height="1332" data-path="images/add-member-to-organization.png" />
</Frame>

* **Roles** is a multi-select. Every invitee gets **Member** implicitly; pick any additional roles your own role lets you assign.
* **Custom Roles** picks one or more [Custom Roles](/enterprise-features/user_groups) that apply subtractive restrictions on top of the organization roles. New invitees automatically join the default custom role; you can layer additional custom roles on top.
* **Teams** adds the invitee to one or more [teams](/core-concepts/teams).

### Best practices

<AccordionGroup>
  <Accordion title="Start minimal, then layer on" icon="shield-halved">
    Every user starts as Member automatically. Add the narrowest additional roles that match their responsibilities. You can always add more later.
  </Accordion>

  <Accordion title="Prefer feature roles over Manager or Admin" icon="layer-group">
    If someone only needs analytics visibility, grant **Analytics**, not Manager. Keep the high-authority list short.
  </Accordion>

  <Accordion title="Use Security instead of Admin for guardrails" icon="user-shield">
    Custom roles, app policies, and AI model access no longer require Admin. Grant **Security** so platform and security teams can own guardrails without billing or SSO.
  </Accordion>

  <Accordion title="Review role assignments quarterly" icon="calendar-check">
    Additive roles make it easy to accumulate extras. Run a quarterly review and remove roles that are no longer needed.
  </Accordion>
</AccordionGroup>

## Removing a member

Remove a member from the three-dot menu on [Organization Members](https://www.gumloop.com/settings/organization/members), or from a team's **Members** tab to remove them from that team only.

<Warning>**Their triggers stop with them.** A [trigger](/core-concepts/workflow_triggers) runs as the person who set it up, using their connectors. When you remove a member, Gumloop turns off the active triggers they own in the spaces they lost — both triggers created in those team spaces and personal triggers on flows those spaces own — so no automation keeps running under a departed account.</Warning>

Scope follows the removal:

| Removal                   | Triggers turned off                                                                   |
| ------------------------- | ------------------------------------------------------------------------------------- |
| **From the organization** | Their triggers across every team in the organization                                  |
| **From one team**         | Only their triggers in that team; triggers in teams they still belong to keep running |

Nothing else is touched: teammates' triggers on the same flows keep running, triggers on flows in the member's own personal space are left alone, and no flow is deleted. To keep an automation running after someone leaves, have a remaining member re-create the trigger with their own connectors.

## How permissions resolve

When someone takes an action, Gumloop checks three things. The action goes through only if all three agree.

<CardGroup cols={3}>
  <Card title="1. Roles" icon="layer-group" color="#5e35b1">
    The union of everything your organization and team roles grant at the relevant scope. This is the ceiling on what you can do.
  </Card>

  <Card title="2. Item sharing" icon="share-nodes" color="#2196f3">
    For a specific agent, flowbook, or skill, the owner can grant you **Editor**, **Viewer**, or **Use only**. Sharing overrides the default per item.
  </Card>

  <Card title="3. Custom roles" icon="filter" color="#f57c00">
    Your [Custom Roles](/enterprise-features/user_groups) can subtract from what roles and sharing allow. For example, they can block certain apps or nodes. A user can hold multiple custom roles, and the effective restriction is composed across all of them.
  </Card>
</CardGroup>

In short: organization and team roles set the ceiling, sharing adjusts access per item, and custom roles can subtract on top.

## Role Comparison

Users with multiple roles get the **union** of the "Yes" columns.

| Capability                                         | Admin | Manager           | Security  | Developer | Analytics | Member |
| -------------------------------------------------- | ----- | ----------------- | --------- | --------- | --------- | ------ |
| Billing and subscription                           | Yes   | No                | No        | No        | No        | No     |
| SSO / SAML / SCIM                                  | Yes   | No                | No        | No        | No        | No     |
| Add and remove members                             | Yes   | Yes               | No        | No        | No        | No     |
| Assign roles                                       | All   | Analytics, Member | Developer | No        | No        | No     |
| Audit logs                                         | Yes   | No                | No        | No        | No        | No     |
| AI model access controls                           | Yes   | No                | Yes       | No        | No        | No     |
| App policies                                       | Yes   | No                | Yes       | No        | No        | No     |
| Custom roles                                       | Yes   | No                | Yes       | No        | No        | No     |
| Organization credentials                           | Yes   | Yes               | No        | No        | No        | No     |
| Org analytics, usage, data export                  | Yes   | Yes               | No        | No        | Yes       | No     |
| Create agents, skills, flowbooks, custom operators | Yes   | Yes               | Yes       | Yes       | Yes       | Yes    |
| Create teams                                       | Yes   | Yes               | Yes       | Yes       | Yes       | Yes    |
| App Activity & MCP management (Enterprise)         | Yes   | No                | Yes       | Yes       | No        | No     |

Team Admin can assign Team Admin and Team Member inside the team. Team Member is implicit and has read access to team content.

## Related Resources

<CardGroup cols={2}>
  <Card title="Custom Roles" icon="users-gear" href="/enterprise-features/user_groups">
    Additive restriction roles that subtract from what organization roles grant.
  </Card>

  <Card title="App Policies" icon="shield-check" href="/enterprise-features/app-policies/overview">
    Allow or block specific apps for users.
  </Card>

  <Card title="AI Model Access Controls" icon="robot" href="/enterprise-features/ai_model_control">
    Restrict which AI models users can call.
  </Card>

  <Card title="Audit Logging" icon="clipboard-list" href="/enterprise-features/audit_logging">
    Track every administrative action.
  </Card>

  <Card title="Teams" icon="people-group" href="/core-concepts/teams">
    Group users and content for shared access.
  </Card>
</CardGroup>
