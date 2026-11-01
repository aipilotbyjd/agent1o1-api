> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Custom Roles

Custom Roles let admins control exactly which apps, tools, scopes, nodes, and AI models each group of users can access. They also gate sensitive features and set per-user usage caps.

<Warning>
  Managing Custom Roles requires the **Admin** or **Security** organization role.
</Warning>

## Key Concepts

* **Additive membership** — a user can hold multiple custom roles. Their effective access is the union across all roles.
* **Default role** — every new member is auto-assigned to the default role. It cannot be deleted.
* **Restrictions compose least-restrictively** — a user is only blocked from something if **every** role they hold blocks it.

<Info>
  Custom Roles **restrict** what a user can do. [Organization Roles](/core-concepts/organization_user_roles) **grant** authority (Admin, Manager, etc.). Both must allow an action for it to succeed.
</Info>

***

## Managing Roles

Navigate to [gumloop.com/settings/organization/groups](https://www.gumloop.com/settings/organization/groups).

Each role has the following tabs:

| Tab              | Purpose                                                              |
| ---------------- | -------------------------------------------------------------------- |
| **Apps**         | Per-app control over tools, scopes, and nodes                        |
| **Models**       | Which AI models users in this role can select                        |
| **Features**     | Toggles for sensitive capabilities                                   |
| **Usage Limits** | Per-user concurrency and credit caps                                 |
| **Users**        | Assign members to this role                                          |
| **Settings**     | Rename, restrict newly added integrations, set as default, or delete |

***

## Apps Tab

The **Apps** tab is where you control which apps your users can access and what they can do with each app. It consolidates **tools**, **scopes**, and **nodes** into a single per-app view.

### Overview

The main view shows all available apps as cards. Each card shows the count of granted tools, scopes, and nodes at a glance.

<Frame>
  <img src="https://mintcdn.com/agenthub/3QjSeRblEIreHcyh/images/custom-roles/apps/apps-overview.png?fit=max&auto=format&n=3QjSeRblEIreHcyh&q=85&s=486f12b3347c89f0d76da5c61dfd5901" alt="Apps tab showing all available apps as cards with tool, scope, and node counts" style={{ maxWidth: '680px' }} width="2402" height="1440" data-path="images/custom-roles/apps/apps-overview.png" />
</Frame>

***

### Example: Configuring GitHub Access

Let's walk through configuring GitHub access for a custom role.

**Step 1: Open the app**

Click the GitHub card (or click **Add App** and select GitHub). This opens the App Picker with three sub-tabs: **Tools**, **Scopes**, and **Nodes**.

***

**Step 2: Configure Tools**

The Tools tab shows every agent tool available for this app. Toggle individual tools on or off.

In this example, the role grants 61 of 63 GitHub tools (like Search Issues, Search Pull Requests, Search Repositories, etc.):

<Frame>
  <img src="https://mintcdn.com/agenthub/3QjSeRblEIreHcyh/images/custom-roles/apps/app-picker-tools.png?fit=max&auto=format&n=3QjSeRblEIreHcyh&q=85&s=8310e6c1616796fafe34596eba3c610f" alt="GitHub App Picker Tools tab showing 61 of 63 tools granted with individual toggles for each tool" style={{ maxWidth: '480px' }} width="1266" height="1680" data-path="images/custom-roles/apps/app-picker-tools.png" />
</Frame>

* Use **Select all** / **Deselect all** for bulk changes
* Search for specific tools using the search bar
* Tools that are not selected will be blocked for users in this role

***

**Step 3: Configure Scopes**

The Scopes tab controls which OAuth scopes users in this role can grant when connecting the app.

In this example, all 7 GitHub scopes are granted (`gist`, `project`, `public_repo`, `read:org`, `read:project`, `repo`, `user`):

<Frame>
  <img src="https://mintcdn.com/agenthub/3QjSeRblEIreHcyh/images/custom-roles/apps/app-picker-scopes.png?fit=max&auto=format&n=3QjSeRblEIreHcyh&q=85&s=4b5b3edf7f4b3cdfc444fac15da5524a" alt="GitHub App Picker Scopes tab showing 7 of 7 scopes granted" style={{ maxWidth: '480px' }} width="1256" height="1460" data-path="images/custom-roles/apps/app-picker-scopes.png" />
</Frame>

* Only selected scopes can be authorized by users in this role
* Removing a scope may affect tools or nodes that depend on it

***

**Step 4: Configure Nodes**

The Nodes tab controls which workflow nodes are available for this app. It also has a toggle for **MCP Node Creation** that controls whether users can build new custom nodes for this app.

In this example, 3 GitHub nodes are granted and MCP Node Creation is enabled:

<Frame>
  <img src="https://mintcdn.com/agenthub/3QjSeRblEIreHcyh/images/custom-roles/apps/app-picker-nodes.png?fit=max&auto=format&n=3QjSeRblEIreHcyh&q=85&s=a59abc90806e60309c6d88d2aee44ca8" alt="GitHub App Picker Nodes tab with MCP Node Creation enabled and 3 nodes granted: GitHub PR Commenter, GitHub PR Description Inserter, Read GitHub Pull Request" style={{ maxWidth: '480px' }} width="1324" height="1690" data-path="images/custom-roles/apps/app-picker-nodes.png" />
</Frame>

* **MCP Node Creation** toggle — controls whether users can create new custom MCP nodes for this app
* Individual node toggles — controls which existing nodes are available in the workflow builder

***

**Step 5: Save**

Click **Save** to apply your changes. The app card on the overview will update to reflect the new counts.

***

### Removing App Access

To completely remove a role's access to an app, click the **three-dot menu** (⋯) on the app card and select **Remove access**.

<Frame>
  <img src="https://mintcdn.com/agenthub/3QjSeRblEIreHcyh/images/custom-roles/apps/app-remove-access.png?fit=max&auto=format&n=3QjSeRblEIreHcyh&q=85&s=fcff2005d74d58530dfbed4a83f042e5" alt="App card menu showing Remove access option" style={{ maxWidth: '400px' }} width="1178" height="522" data-path="images/custom-roles/apps/app-remove-access.png" />
</Frame>

This removes all tool, scope, and node grants for that app from this role.

***

### How App Restrictions Compose

When a user is in **multiple roles**, app access composes with the least-restrictive rule:

| Scenario                                                   | Result                                       |
| ---------------------------------------------------------- | -------------------------------------------- |
| Role A grants 3 GitHub tools, Role B has no GitHub card    | User gets **all** GitHub tools (B is silent) |
| Role A grants `repo` scope only, Role B has no GitHub card | User gets **all** scopes (B is silent)       |
| Role A and Role B both only grant `repo` scope             | User gets only `repo` scope                  |
| Role A removes app access entirely, Role B has no card     | User is **unrestricted** (B is silent)       |

<Note>
  A role with no card for an app is "silent" on that app, meaning it does not restrict it. Only when **every** role a user holds explicitly restricts an app does the restriction apply.
</Note>

***

## Models Tab

The **Models** tab controls which AI models users in this role can select — in the agent model picker and in workflow AI nodes. Models a role does not allow are hidden from those pickers, so members can only choose from what they are permitted to use.

<Frame>
  <img src="https://mintcdn.com/agenthub/FuFTEoad3LOMqApS/images/custom-roles/models-tab.png?fit=max&auto=format&n=FuFTEoad3LOMqApS&q=85&s=e6fb54098ef76cd296c3518b37b5f36c" alt="Models tab showing an allow-list of AI models grouped by provider, with per-provider allowed counts, a search bar, and US-provider hosted badges" style={{ maxWidth: '680px' }} width="2534" height="1544" data-path="images/custom-roles/models-tab.png" />
</Frame>

Models are grouped by provider (Anthropic, OpenAI, SpaceXAI, Google, and more) with a running **allowed** and **blocked** count in the header. You can:

* Toggle a whole provider on or off, or expand a provider to toggle individual models
* Search for a specific model or provider
* See a **US-provider hosted** badge on providers served by US-based providers under Zero Data Retention policies

Deprecated models are collapsed behind a **+N more deprecated models** link so the list stays focused on current models.

<Info>
  A role's model list is **bounded by the organization's model access ceiling**. If a model is blocked org-wide on the [AI Model Access Control](/enterprise-features/ai_model_control#ai-model-access-control) page, it cannot be granted to a role here, even if it appears selectable.
</Info>

<Note>
  Clearing every model for a role is an explicit **deny-all** — users in that role (and no other role that allows models) will have no selectable models and fall back to the organization's configured fallback model.
</Note>

### How model access composes

Like apps, model access composes **least-restrictively** across a user's roles: a user can select a model if **any** role they hold allows it. A role that leaves its Models tab untouched is "silent" and does not restrict models.

At runtime, if a flow or agent references a model the user is no longer allowed to use, Gumloop falls back to the organization's [Fallback Model](/enterprise-features/ai_model_control#fallbacks) instead of failing.

***

## Features Tab

Controls sensitive capabilities that are gated by default for non-admin members. A user gets a feature if **any** of their roles grants it.

<Frame>
  <img src="https://mintcdn.com/agenthub/oWu_o23xPYGRQDYy/images/custom-roles/features-tab.png?fit=max&auto=format&n=oWu_o23xPYGRQDYy&q=85&s=71579a9a98c05836b647e2b67c321d61" alt="Features tab showing toggles for sensitive capabilities" width="2378" height="1280" data-path="images/custom-roles/features-tab.png" />
</Frame>

| Feature                               | What it allows                                                                               |
| ------------------------------------- | -------------------------------------------------------------------------------------------- |
| **Team creation**                     | Create new teams within the organization                                                     |
| **Team credential addition**          | Add credentials to teams the user has admin access to                                        |
| **Agent email inbox management**      | Enable, change, or disable email inboxes for agents                                          |
| **Agent incognito mode**              | Run agent chats without saving messages to history                                           |
| **MCP node creation**                 | Create MCP nodes within the organization                                                     |
| **Public flow and interface sharing** | Share flows and interfaces publicly                                                          |
| **External chat sharing**             | Share chats outside the organization or publicly                                             |
| **External artifact sharing**         | Share files outside the organization or publicly                                             |
| **Flow modification**                 | Create, update, or delete flows and workbooks                                                |
| **Workflow incognito mode**           | Run workflows without saving run data to history                                             |
| **Agent modification**                | Create, update, or delete agents                                                             |
| **Skill creation**                    | Create [skills](/core-concepts/skills) within the organization                               |
| **Agent-owned credentials**           | Configure agents to use a pinned account or connection for everyone who can access the agent |
| **Brain access**                      | View and search [Brain](/core-concepts/brain) knowledge sources                              |
| **Brain source management**           | Add, update, sync, share, or delete [Brain](/core-concepts/brain) sources                    |

<Info>
  Features marked with a shield icon are denied by default for enterprise users unless explicitly granted. Others are allowed by default.
</Info>

<Note>
  **Skill creation** covers *new* skills only. With it turned off, an agent that tries to save a new skill skips it and reports that skill creation has been restricted by your organization admin; editing skills that already exist is unaffected and stays governed by each skill's own access.
</Note>

***

## Usage Limits Tab

Per-user caps that override organization-wide defaults.

<Frame>
  <img src="https://mintcdn.com/agenthub/oWu_o23xPYGRQDYy/images/custom-roles/usage-limits-tab.png?fit=max&auto=format&n=oWu_o23xPYGRQDYy&q=85&s=1b0eee1f78fcf8a2165164e5d39d46c0" alt="Usage Limits tab showing concurrent run, agent, and credit caps" width="2536" height="1506" data-path="images/custom-roles/usage-limits-tab.png" />
</Frame>

| Cap                            | Effect                                                                 |
| ------------------------------ | ---------------------------------------------------------------------- |
| **Concurrent Run Limit**       | Max simultaneous workflow runs per user                                |
| **Concurrent Agent Limit**     | Max simultaneous agent interactions per user                           |
| **Monthly Credit Cap**         | Max credits per billing month                                          |
| **Credit Usage Notifications** | Email alerts at thresholds (50%, 80%, 100%)                            |
| **Per-Chat Credit Warnings**   | Pause chat for approval when a single chat's spend crosses a threshold |

Caps compose by taking the **maximum** across a user's roles.

<Info>
  Monthly credit caps can also be read and set programmatically, so an internal credit-management system can act as the control plane. See the [List](/api-reference/organization/list-role-credit-limits), [Get](/api-reference/organization/get-role-credit-limit), and [Set](/api-reference/organization/set-role-credit-limit) Custom Role Credit Limit API endpoints.
</Info>

### Per-Chat Credit Warnings

Per-Chat Credit Warnings pause a user's chat for approval whenever the credit spend **in that single chat** crosses a threshold. This prevents runaway conversations from silently burning through credits.

<Frame>
  <img src="https://mintcdn.com/agenthub/yMOKUyZANSzb-dO1/images/custom-roles/per-chat-credit-warnings.png?fit=max&auto=format&n=yMOKUyZANSzb-dO1&q=85&s=66238b602b0a9b5c7c66bf5d5395f2c6" alt="Per-Chat Credit Warnings section showing no warnings configured and a Configure button" width="2318" height="400" data-path="images/custom-roles/per-chat-credit-warnings.png" />
</Frame>

Click **Configure** to open the threshold picker. Toggle the default thresholds (**5,000**, **10,000**, and **25,000** credits) or add a custom value.

<Frame>
  <img src="https://mintcdn.com/agenthub/yMOKUyZANSzb-dO1/images/custom-roles/per-chat-credit-warnings-configure.png?fit=max&auto=format&n=yMOKUyZANSzb-dO1&q=85&s=ccd75cde897ca9c2234a796f01ab0f59" alt="Per-Chat Credit Warnings configuration modal with default thresholds at 5,000, 10,000, and 25,000 credits and an Add custom threshold button" style={{ maxWidth: '540px' }} width="1346" height="1040" data-path="images/custom-roles/per-chat-credit-warnings-configure.png" />
</Frame>

When a chat hits an enabled threshold, the agent pauses and creates an [Action Request](/core-concepts/human_in_the_loop) that the user (or an admin) must approve before the conversation continues.

<Info>
  The default values are recommended to prevent runaway chats while ensuring normal usage is not interrupted.
</Info>

**Multi-role resolution:** thresholds compose by taking the **maximum** across all of a user's custom roles. For example, if a user belongs to Role A (warning at 5,000 credits) and Role B (warning at 10,000 credits), their chat will pause at **10,000** credits — the higher, least-restrictive value wins.

***

## Users Tab

Assign members to this role. Adding a user here does **not** remove them from any other role.

***

## Settings Tab

<Frame>
  <img src="https://mintcdn.com/agenthub/r5ogdBGQjCtcxwWl/images/custom-roles/settings-tab.png?fit=max&auto=format&n=r5ogdBGQjCtcxwWl&q=85&s=7f289a9500700a765e12506238c4d154" alt="Settings tab with role name, description, Restrict Newly Added Integrations toggle, default role, and delete" width="2022" height="529" data-path="images/custom-roles/settings-tab.png" />
</Frame>

* **Role Name** and **Description** — rename the role and describe what it's for
* **Restrict Newly Added Integrations** toggle — *"Automatically block newly released integrations for this role until an admin approves them."*
* **Default Role** toggle — promotes this role to be the org's default
* **Delete Role** — irreversible; members fall back to the default role

### Restricting newly added integrations

Gumloop ships new integrations, nodes, and MCP servers regularly, and by default they become available to your members as soon as they launch. Turning on **Restrict Newly Added Integrations** flips that default for this role: anything newly released is blocked for the role until an admin approves it.

<Steps>
  <Step title="Turn it on">
    Enable the toggle in the role's **Settings** tab. Existing access is untouched — it only applies to integrations released from that point on.
  </Step>

  <Step title="New integrations arrive blocked">
    As Gumloop releases integrations, they are added to this role's blocked list automatically instead of becoming available.
  </Step>

  <Step title="Approve what you want">
    An admin unblocks a specific integration for the role from the [**Apps** tab](#apps-tab), the same way any other app access is granted.
  </Step>
</Steps>

<Info>
  Newly released integrations are added to the blocked list by a periodic job rather than the instant they ship, so there can be a short window before a brand-new integration appears in the role's list.
</Info>

<Tip>
  There is also an organization-wide version of this setting. Instead of enabling the toggle role by role, it restricts newly released integrations for **every** custom role in the organization, including roles created after it is turned on.
</Tip>

<Warning>
  Access is a union across roles: a user who is also in a role without this restriction still gets the new integration. To hold a new integration back from someone, every role they belong to has to block it.
</Warning>

***

## SCIM and IdP Group Sync

If your organization uses SCIM provisioning, IdP groups can be mapped to custom roles automatically.

<CardGroup cols={2}>
  <Card title="How it works" icon="rotate">
    * IdP groups map to custom roles via a curated **mapping table** (or name-based mode)
    * Users receive the **union** of every matched role — there is no priority
    * If mappings exist but no IdP group matches, the user gets the **default role**
  </Card>

  <Card title="Notes" icon="circle-info">
    * A user in multiple mapped IdP groups is assigned **all** of the matched roles
    * Team (project) sync is configured independently of the role direction
    * With **Use mapping table** on, SCIM keeps a user's existing memberships when no mapped IdP group matches (clearing the table won't strip them)
  </Card>
</CardGroup>

For full SCIM setup instructions, see [SSO, SAML, and SCIM](/enterprise-features/sso_saml_scim#scim-and-custom-roles-teams).

***

## FAQ

<AccordionGroup>
  <Accordion title="What happens if a user is in multiple roles?" icon="layer-group">
    * **Apps**: access is the union. Blocked only if every role blocks it.
    * **Models**: access is the union. A model is selectable if any role allows it.
    * **Features**: granted if any role grants it.
    * **Usage caps**: the highest value wins (including per-chat credit warning thresholds).
  </Accordion>

  <Accordion title="What does a 'silent' role mean?" icon="circle-question">
    If a role has no card for an app, it has no opinion on that app. The user is unrestricted for that app by this role. Restrictions only apply when all roles agree.
  </Accordion>

  <Accordion title="Can I use the default role as a restrictive baseline?" icon="shield-halved">
    Yes. Keep the default role restrictive, then create add-on roles that widen access for specific groups. Or do the opposite: keep default permissive and use stricter roles to narrow access.
  </Accordion>
</AccordionGroup>

***

## See Also

<CardGroup cols={2}>
  <Card title="Organization Roles" icon="building" href="/core-concepts/organization_user_roles">
    The authority side of Gumloop's permission model.
  </Card>

  <Card title="App Policies" icon="shield-halved" href="/enterprise-features/app-policies/overview">
    Block or tag specific tool calls and restrict OAuth domains.
  </Card>
</CardGroup>
