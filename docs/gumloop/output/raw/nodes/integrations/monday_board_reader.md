> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Monday.com Board Reader

This document outlines the functionality and characteristics of the `Monday.com Board Reader` node, which enables users to retrieve data from specified Monday.com boards and groups. The node can also be [activated as a trigger](#using-it-as-a-trigger) so a board change starts your workflow or agent.

## Node Inputs

The `Monday.com Board Reader` node requires several inputs to operate effectively. These inputs include:

* **Workspace**: The specific [Monday.com workspace](https://www.monday.com) from which to retrieve data.
* **Board**: The particular board within the selected workspace to read data from.
* **Group**: Specifies the group within the board to read (e.g., "Completed", "To-Do").
* **Number of Items** (optional): Limits the number of items to fetch.
  * If set to `1`, outputs a single `text` item.
  * If left blank or set to more than `1`, outputs a `List of text` items.

## Node Output

The `Monday.com Board Reader` node outputs:

* Retrieved data as either a single text item or a list of text items, based on the `Number of Items` setting.

## Node Functionality

The `Monday.com Board Reader` node is designed to read data from Monday.com boards and integrate it into workflows.

Key features include:

* **Loop Mode**: Allows for batch processing of multiple items, enabling iteration over data within workflows.
* **Dynamic Inputs**: Configurable options for showing inputs dynamically, allowing you to toggle visibility for `Workspace`, `Board`, and `Number of Items`.

## When To Use

The `Monday.com Board Reader` node is valuable in scenarios where data from Monday.com is needed within an automated workflow. Common use cases include:

* **Task Management**: Automatically retrieve tasks from specific boards and groups for tracking progress.
* **Automated Reporting**: Pull data for reporting purposes, such as completed tasks or pending items.
* **Project Monitoring**: Sync project data from multiple boards to centralize tracking within a workflow.

**Some specific examples**:

* Fetching a list of completed tasks for generating a status report.
* Retrieving new tasks from a "To-Do" group to integrate into a task management system.
* Pulling data from a project board for team-wide visibility in another system.

## Using It as a Trigger

Toggle **Activate as workflow trigger** (or pick Monday.com when creating an [agent trigger](/core-concepts/agent_triggers#event-based-triggers)) and the node stops reading the board on demand and instead runs whenever the board changes. Monday.com pushes the change to Gumloop over a webhook, so it fires in real time.

In trigger mode you select a **Trigger Mode** instead of a **Number of Items**:

| Field                               | When it applies                                                                                                        |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Workspace**, **Board**            | Always. The webhook is registered on the board you select.                                                             |
| **Trigger Mode**                    | Always. One of 18 item, subitem, update, or column events.                                                             |
| **Column**                          | Trigger Mode is **Specific Column Changed**.                                                                           |
| **Status Column**, **Status Value** | Trigger Mode is **Status Changed To**. Status Value is optional — leave it empty to fire on any change to that column. |
| **Group**                           | Trigger Mode is **Item Moved To Group**.                                                                               |

In trigger mode the node outputs one value per column on the selected board, named after the column title, plus the board's item name column (`Item`, `Task`, or whatever your board calls its items).

<Info>The full list of trigger modes and their behavior is documented in [Workflow Triggers](/core-concepts/workflow_triggers#monday-com).</Info>

## Important Considerations:

1. Requires Authentication with Monday.com – Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Pre-requisite**: Ensure the Gumloop app is installed in your Monday.com workspace.
3. **Trigger mode** additionally needs permission to manage webhooks on the board. If you connected Monday.com before trigger support shipped, reconnect the account so the webhook permission is granted.

In summary, the `Monday.com Board Reader` node provides a powerful way to integrate data from Monday.com boards into automated workflows. With its flexible configuration options and secure access, it is ideal for retrieving and utilizing Monday.com data in real-time.
