> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Share Permissions

Gumloop gives you fine-grained control over who can access your agents, workflows, and other resources. You can share with specific users by email, share with your entire team or organization, or make something public for anyone with the link.

## How Sharing Works

Every resource in Gumloop (agents, workflows, custom nodes, interfaces) has a **Share dialog** that you access by clicking the **Share** button. The dialog has two main sections:

1. **Users**: Add specific people by email and assign them a role
2. **General Access**: Control broader access for your team, organization, or the public

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/flow_general_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=ccac8fcb5ee16ca1cd7b4edd3083b59b" alt="Workflow share dialog showing user sharing and General Access settings" width="600" data-path="images/flow_general_access.png" />
</div>

## Roles

Roles determine what someone can do with a shared resource. Gumloop uses a four-tier role hierarchy:

```text theme={"dark"}
Owner > Editor > Viewer > Use Only
```

### Role Comparison

| Permission                    | Owner | Editor | Viewer    | Use Only                            |
| ----------------------------- | ----- | ------ | --------- | ----------------------------------- |
| **View the resource**         | Yes   | Yes    | Yes       | No (agents and skills: invoke only) |
| **Edit the resource**         | Yes   | Yes    | No        | No                                  |
| **Delete the resource**       | Yes   | Yes    | No        | No                                  |
| **Manage sharing**            | Yes   | Yes    | View only | No                                  |
| **Make a copy**               | Yes   | Yes    | Yes       | No                                  |
| **Leave (remove own access)** | No    | Yes    | Yes       | Yes                                 |

### Owner

The person who created the resource. Owners have full control and **cannot be removed** through the sharing UI. Ownership is assigned at creation time and can only be transferred explicitly.

### Editor

Full access to view, edit, delete, and manage sharing for the resource. Editors can add and remove other Editors and Viewers.

### Viewer

Read-only access. Viewers can see the resource and its configuration, view who has access, and make a copy to their own space. They cannot edit anything. This is the role team members get by default on team resources.

### Use Only

The most restricted role, currently available for **agents** and **skills**. Use Only users can interact with the resource (chat with an agent, or use a skill in their agents) but cannot see its configuration, instructions, tools, or any internal details. This is perfect for sharing a resource with end users who just need to use it without seeing how it works.

## Sharing with Specific Users

To share a resource with specific people:

1. Open the resource and click the **Share** button
2. Enter their email address in the "Add people" field
3. Click the dropdown arrow on the Share button to choose a role
4. Click **Share**

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_user_share_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=61cdd8eca6d95e6f5258ddce934cac3a" alt="Agent share dialog showing role selection when sharing with a user" width="600" data-path="images/agent_user_share_access.png" />
</div>

The available roles depend on the resource type:

| Resource          | Available Sharing Roles  |
| ----------------- | ------------------------ |
| **Agents**        | Editor, Viewer, Use Only |
| **Skills**        | Editor, Viewer, Use Only |
| **Workflows**     | Editor, Viewer           |
| **Custom Nodes**  | Editor, Viewer           |
| **Interfaces**    | Viewer                   |
| **Chat Sessions** | Viewer                   |

<Info>
  You can share with **any Gumloop user** by email. They don't need to be on your team or in your organization.
</Info>

### Changing a User's Role

After sharing, you can change a user's role from the Share dialog. Click the role label next to their name to see available options. You cannot change the Owner's role through the sharing UI.

### Removing a User

To remove someone's access, click the role label next to their name and select **Remove**. Owners cannot be removed.

### Leaving a Shared Resource

If you have access to a resource you no longer need, you can remove yourself by clicking your own role and selecting **Leave**. Owners cannot leave their own resources.

## General Access

General Access controls who can access a resource **without** being explicitly added by email. Think of it as concentric rings, from most restrictive to most open.

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_general_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=7e40da68b122bef47cd861b34eefd69a" alt="Agent share dialog showing General Access options with role selection" width="600" data-path="images/agent_general_access.png" />
</div>

### Access Levels

| Level                | Who Gets Access                                      | When to Use                                           |
| -------------------- | ---------------------------------------------------- | ----------------------------------------------------- |
| **Restricted**       | Only explicitly added users and the owner            | Private resources you control access to individually  |
| **Team**             | All members of the team the resource belongs to      | Resources that your whole team should use             |
| **Organization**     | All members of your organization                     | Company-wide resources everyone should access         |
| **Anyone with link** | Everyone, including people without a Gumloop account | Public resources, demos, templates for external users |

<Warning>
  **Anonymous users are capped at Viewer access.** Even if you set General Access to "Anyone with link" with an Editor role, unauthenticated users will only get Viewer-level access. They must sign in to get any elevated role.
</Warning>

<Info>
  On Enterprise, agents set to **Organization** access can additionally allow **Slack members without Gumloop accounts** to run them, using a shared organization service account. That toggle appears in the agent's share dialog directly under General Access. See [Organization Service Accounts for Slack Agents](/enterprise-features/slack_agent_access).
</Info>

### General Access Roles

When you set General Access to Team, Organization, or Anyone, you also choose **what role** that audience gets. For example, you might give your entire organization Viewer access to an agent, but give your team Editor access.

### Rules and Constraints

Not every access level is available in every context:

| Resource Location  | Available General Access Levels    |
| ------------------ | ---------------------------------- |
| **Personal space** | Restricted, Organization\*, Anyone |
| **Team space**     | Team, Organization\*, Anyone       |

\*Organization is only available if you belong to an organization.

Key constraints:

* **Team resources cannot be set to Restricted.** If a resource lives in a team, the minimum access level is Team. All team members will have access.
* **Personal resources cannot be set to Team.** There is no team to share with.

## How Access Is Resolved

When you try to access a resource, Gumloop checks your permissions in a specific order. The first match wins:

1. **Direct user grant** (if you were added by email, this always wins)
2. **Team grant** (if the resource has Team access and you're a team member)
3. **Organization grant** (if the resource has Organization access and you're in the org)
4. **Public grant** (if the resource has Anyone access)
5. **No access** (if none of the above matched)

<Info>
  **Direct grants always take priority.** If you're added as a Viewer directly, you'll be a Viewer even if the Organization-level access is set to Editor. This lets resource owners restrict specific users below the general access level when needed.
</Info>

## Making a Copy

Viewers (and above) can make a copy of a shared resource to their own space. This creates an independent copy that they fully own.

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/agent_make_a_copy.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=5f85f844d5773a56c125e001f276123d" alt="Make a Copy button in the agent interface" width="400" data-path="images/agent_make_a_copy.png" />
</div>

For agents, the **Make a Copy** button appears in the top bar. For workflows, you can duplicate from the hub using the three-dot menu.

Copies are completely independent. Changes to the original don't affect the copy, and vice versa.

## Sharing Agents

Agents have the richest sharing model with three roles for sharing: **Editor**, **Viewer**, and **Use Only**.

### Agent Roles in Detail

| Capability                            | Editor | Viewer    | Use Only |
| ------------------------------------- | ------ | --------- | -------- |
| Chat with the agent                   | Yes    | Yes       | Yes      |
| View agent configuration              | Yes    | Yes       | No       |
| Edit instructions, tools, model       | Yes    | No        | No       |
| Manage triggers (webhooks, schedules) | Yes    | No        | No       |
| Create templates from the agent       | Yes    | No        | No       |
| Move agent between workspaces         | Yes    | No        | No       |
| Manage sharing settings               | Yes    | View only | No       |
| Make a copy                           | Yes    | Yes       | No       |

### Agent-Specific Share Actions

The agent Share dialog includes additional actions at the bottom:

* **Copy agent link**: Copies a direct link to the agent
* **Copy current chat link**: Copies a link to the current conversation
* **Copy setup link**: Copies a link that guides users through authenticating with the agent's required integrations

<Tip>
  **Use the setup link** when sharing agents that rely on integrations (Gmail, Slack, etc.). It walks the recipient through connecting their own credentials so they can use the agent immediately.
</Tip>

## Sharing Workflows

Workflows support **Editor** and **Viewer** sharing roles.

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/flow_user_share_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=b7bd89fa7e75c21365e7c67c91890cab" alt="Workflow share dialog showing Editor and Viewer role options" width="600" data-path="images/flow_user_share_access.png" />
</div>

### Workflow Roles in Detail

| Capability              | Editor | Viewer    |
| ----------------------- | ------ | --------- |
| View the workflow       | Yes    | Yes       |
| Edit, add, remove nodes | Yes    | No        |
| Run the workflow        | Yes    | No        |
| Manage triggers         | Yes    | No        |
| Create templates        | Yes    | No        |
| Move between workspaces | Yes    | No        |
| Manage sharing settings | Yes    | View only |
| Make a copy             | Yes    | Yes       |

### Workflow vs Interface Access

Workflow access and interface access are **independent**. Sharing a workflow does not automatically share its interfaces, and vice versa. This lets you:

* Share an interface widely while keeping the workflow private
* Give someone Editor access to the workflow without giving them access to run the interface
* Make an interface public without making the underlying workflow public

See [Interfaces documentation](/core-concepts/interfaces) for details on interface-specific access.

## Sharing Custom Nodes

Custom nodes support **Editor** and **Viewer** roles. By default, only the creator (Owner) can edit a custom node.

See [Custom Node Builder](/nodes/custom_node_details) for details on custom node sharing.

## Sharing Chat Sessions

Individual agent chat sessions can be shared with **Viewer** access only. The chat creator (Owner) controls sharing. Shared viewers can read the conversation but cannot send messages.

## Finding Shared Resources

Every resource listing page in Gumloop (Agents, Skills, Files, Workflows) includes three tabs to help you find what you need:

| Tab                | What It Shows                                                                |
| ------------------ | ---------------------------------------------------------------------------- |
| **Mine**           | Resources you created                                                        |
| **Shared with me** | Resources that others have shared with you directly or via your organization |
| **Organization**   | All resources visible to your entire organization                            |

The **Shared with me** tab is the fastest way to find resources that others have given you access to. It shows agents, skills, files, and workflows where you have been explicitly added as a collaborator, or where the General Access level includes you.

Each listing page also supports search and filters so you can narrow down results by name, creator, and other criteria.

<Info>
  For details on the Shared with me view for each resource type, see: [Agents](/core-concepts/agents#finding-agents), [Skills](/core-concepts/skills#finding-skills), and [Files](/core-concepts/agent_artifacts#files-page).
</Info>

## Action Requests

When you try to access a resource you don't have permission to view, Gumloop lets you **request access** directly. This sends a notification to the resource owner or a workspace admin who can grant or deny your request.

### How It Works

1. **You visit a resource you can't access** (an agent, workflow, file, or team). You'll see a **Request Access** button.
2. **Your request is sent** to the appropriate person, either the resource owner or a workspace admin, via email and Slack (if connected).
3. **The recipient reviews your request** and can approve or deny it with a single click.

### In-App Inbox

Admins and resource owners can review and respond to action requests directly from the **Inbox** in Gumloop. When a request comes in, it appears as a notification with the requester's name, the requested resource or role, and the time it was submitted.

Each request offers four response options:

* **Approve**: Grant the request immediately.
* **Open**: Review the request details before making a decision.
* **Reject**: Irreversibly deny the request.
* **Dismiss**: Ignore the request without taking action.

Resolved requests move to the **Resolved** tab for reference, and you can use **Clear** to clean up your inbox.

<Frame>
  <img src="https://mintcdn.com/agenthub/-zimQqN5kjF5x8qk/images/action_request_inbox.png?fit=max&auto=format&n=-zimQqN5kjF5x8qk&q=85&s=c8ccd23b9e6713a77361e9a5082560fd" alt="Action request inbox showing a role request with Approve, Open, Reject, and Dismiss options" width="930" height="1022" data-path="images/action_request_inbox.png" />
</Frame>

### Slack One-Click Approval

If the approver has Slack connected to Gumloop, they receive the access request as a Slack DM with **Approve** and **Deny** buttons. This lets them grant or deny access with a single click, without leaving Slack.

<Info>
  Slack one-click approval is currently available for **team** and **organization** access requests. Support for other request types (such as individual agent or workflow access) is being rolled out incrementally.
</Info>

**Prerequisites for Slack notifications:**

* **Approver must have Slack connected.** The person who receives the request (resource owner, team admin, or org admin) needs to have authenticated Slack in their [Connectors page](https://www.gumloop.com/personal/connectors?provider=slack).
* **Requester must have Slack connected.** The person requesting access also needs Slack authenticated so Gumloop can identify which Slack workspace they belong to. Without this, the notification is sent via email only.

<Tip>
  If you're not receiving Slack notifications for access requests, make sure both the requester and the approver have connected Slack in their Gumloop account. Visit your [Connectors page](https://www.gumloop.com/personal/connectors?provider=slack) to check your Slack connection.
</Tip>

### What You Can Request Access To

| Resource Type          | Who Receives the Request                                        |
| ---------------------- | --------------------------------------------------------------- |
| **Agents**             | The agent owner                                                 |
| **Skills**             | The skill owner                                                 |
| **Workflows**          | The workflow owner                                              |
| **Files**              | The file owner, or the owner of the agent that created the file |
| **Teams**              | A workspace admin                                               |
| **Organization roles** | An organization admin                                           |

### Request Lifecycle

Each request is tracked with a durable record:

* **Pending**: The request has been sent and is waiting for a decision.
* **Accepted**: The recipient approved the request and access was granted.
* **Rejected**: The recipient denied the request.
* **Expired**: The request was not acted on within the expiry window.

Requests can only be resolved once. If a request is denied, the requester can submit a new request later.

## Cross-Organization Sharing

You can share resources with users in other organizations by adding them by email. When a resource is shared across organizations:

* The **General Access section is hidden** for cross-org users (to protect internal team/org details)
* Cross-org users see a simple badge showing their access level
* Access is governed by the direct user grant only

## Enterprise Controls

Organizations on Enterprise plans have additional controls over sharing:

### Public Sharing Restrictions

Organization admins can restrict users from setting General Access to "Anyone with link" using [User Groups](/enterprise-features/user_groups). When this restriction is enabled, the "Anyone" option is hidden in the Share dialog, and API calls to set public access will be rejected.

### Audit Logging

All sharing operations are logged in the [audit trail](/enterprise-features/audit_logging):

* When General Access level is changed
* When a user is granted access
* When a user's access is revoked

Each event records who made the change, who was affected, the role, and the resource.

## Common Questions

<AccordionGroup>
  <Accordion title="Can I share with someone outside my organization?" icon="globe">
    Yes. You can share with any Gumloop user by entering their email in the Share dialog. They don't need to be on your team or in your organization. The resource will appear in their sidebar under "Shared with me."
  </Accordion>

  <Accordion title="What happens when I reduce General Access?" icon="shield">
    If you lower the General Access level (e.g., from Organization to Restricted), users who were accessing via that level will lose access. Users with direct grants (added by email) are not affected. If you're reducing your own access, the UI will warn you before proceeding.
  </Accordion>

  <Accordion title="Can I give someone Use Only access to a workflow?" icon="eye-slash">
    No. Use Only is currently available for **agents** and **skills** only. For workflows, the most restricted sharing role is Viewer, which gives read-only access. If you want someone to run your workflow without seeing it, share it as an [interface](/core-concepts/interfaces) instead.
  </Accordion>

  <Accordion title="What's the difference between Viewer and Use Only?" icon="user-lock">
    **Viewer** can see the resource's configuration and use it. **Use Only** can only use the resource (chat with an agent, or invoke a skill in agents) and cannot see any configuration details, files, or instructions. Use Only is ideal for end users who just need to interact with the resource without seeing how it works.
  </Accordion>

  <Accordion title="I'm an Editor but I can't change the Owner's role. Why?" icon="crown">
    Owner grants cannot be modified or removed through the sharing UI. Ownership can only be transferred explicitly by the current Owner. This protects the creator's control over their resource.
  </Accordion>

  <Accordion title="Why can't I set my team resource to Restricted?" icon="lock">
    Resources in a team inherently belong to that team, so team access is the minimum. If you need more restrictive access, move the resource to your personal space first, then share with specific users.
  </Accordion>

  <Accordion title="If I make a workflow public, does that make its interface public too?" icon="window">
    No. Workflow access and interface access are configured independently. Making a workflow public does not affect its interface's access settings, and vice versa.
  </Accordion>

  <Accordion title="What can anonymous users do with a public resource?" icon="user-slash">
    Anonymous (unauthenticated) users are capped at **Viewer** access regardless of the public grant's role setting. They can view the resource but cannot edit, run, or manage anything. They must sign in to get elevated access.
  </Accordion>

  <Accordion title="I was shared a resource but can't find it. Where is it?" icon="magnifying-glass">
    Check the **Shared with me** tab on the relevant listing page (Agents, Skills, Files, or Workflows). This tab shows all resources that others have shared with you. If you still can't find it, the sharing may not have completed. Ask the person who shared it to verify your access in the Share dialog.
  </Accordion>
</AccordionGroup>

## Related Documentation

<CardGroup cols={3}>
  <Card title="Organization & Teams" icon="building" href="/core-concepts/teams">
    Understand personal spaces, teams, and organizations
  </Card>

  <Card title="Organization Roles" icon="shield" href="/core-concepts/organization_user_roles">
    Admin, Manager, and Member roles at the org level
  </Card>

  <Card title="User Groups" icon="users" href="/enterprise-features/user_groups">
    Enterprise feature restrictions including public sharing controls
  </Card>
</CardGroup>
