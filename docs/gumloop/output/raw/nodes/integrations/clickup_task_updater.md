> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# ClickUp Task Updater

This document outlines the functionality and characteristics of the ClickUp Task Updater node, which enables automated task updates in ClickUp workspaces.

## Node Inputs

### Required Fields

* **Task ID**: Identifier of task to update
* **Team**: Your ClickUp workspace
* **Space**: Team space
* **Folder**: Contains lists
* **List**: Contains task

### Optional Update Fields

* **New Task Name**: Updated title
* **New Task Description**: Updated details
* **New Assignees**: Change task assignment
* **New Status**: Modified task status
* **New Priority**: Updated priority level

## Node Output

* **Task URL**: Link to the updated task

## Node Functionality

The ClickUp Task Updater node modifies existing tasks in ClickUp.

**Key features include**:

* Selective field updates
* Multiple task properties
* Team re-assignment
* Status management
* Priority adjustment
* Loop mode to update multiple tasks
* Secure authentication with Gumloop

## When To Use

The ClickUp Task Updater node is valuable for task maintenance automation. Common use cases include:

* **Status Updates**: Automatically progress task stages
* **Assignment Changes**: Reassign tasks based on conditions
* **Priority Management**: Adjust task urgency levels
* **Task Maintenance**: Update task details programmatically

**Some specific examples**:

* Updating task status based on external triggers
* Reassigning tasks when team members change
* Adjusting priorities based on deadlines
* Modifying task descriptions with new information

## Example

To update a task's status and assignee:

1. Task identification:
   * Task ID: "abc123"
   * Team/Space/Folder/List path selected

2. Update configuration:
   * New Status: "In Progress"
   * New Assignees: "New Team Member"
   * Leave other fields empty to maintain current values

3. Using dynamic inputs (via Configure Inputs):
   * Connect task location data for flexible updates
   * Update status based on workflow triggers

## Important Considerations:

1. Requires Authentication with ClickUp - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Task ID must be valid
3. Use the Configure Inputs option to dynamically expose Team, Space, Folder, List & Status fields as inputs

In summary, the ClickUp Task Updater node streamlines task modification in ClickUp, supporting selective updates and automated task management workflows.
