> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sharing Roles

Roles on a shared resource are separate from org roles (Admin / Manager / Member). You set them in the **Share** dialog.

## What each role can do

| Capability                          | Owner               | Editor                                        | Viewer                                        | Use Only |
| ----------------------------------- | ------------------- | --------------------------------------------- | --------------------------------------------- | -------- |
| Chat with the agent                 | Yes                 | Yes                                           | Yes                                           | Yes      |
| See instructions, tools, and skills | Yes                 | Yes                                           | Yes                                           | No       |
| Edit the agent                      | Yes                 | Yes                                           | No                                            | No       |
| Manage sharing                      | Yes                 | Yes                                           | View only                                     | No       |
| Manage triggers                     | Yes                 | Yes                                           | No                                            | No       |
| Make a copy                         | Yes                 | Yes                                           | Yes                                           | No       |
| See other people's chats            | On a **team** agent | On a **team** agent, if they are on that team | On a **team** agent, if they are on that team | No       |

Full matrix: [Share Permissions](/core-concepts/share_permissions).

## Which roles exist where

* **Agents and skills:** Editor, Viewer, Use Only
* **Workflows:** Editor and Viewer (no Use Only)

## How to assign a role

1. Open the agent and click **Share**.
2. Add a person by email, or set **General Access** for the team or organization.
3. Pick **Editor**, **Viewer**, or **Use Only**.

<Tip>
  For a company-wide agent that people should only talk to, set General Access to **Organization** + **Use Only**.
</Tip>

## FAQ

<AccordionGroup>
  <Accordion title="What's the difference between Viewer and Use Only?">
    Viewer can see the setup and chat. Use Only can only chat. They cannot see instructions, tools, or files.
  </Accordion>

  <Accordion title="Does team membership make someone an Editor?">
    No. Team members get Viewer-level access to team agents by default. Raise the role in Share if they should edit.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Share Permissions" icon="share-nodes" href="/core-concepts/share_permissions">
    Full role reference
  </Card>

  <Card title="Who can see chats" icon="eye" href="/help/sharing/who-can-see-chats">
    Chat visibility by role
  </Card>
</CardGroup>
