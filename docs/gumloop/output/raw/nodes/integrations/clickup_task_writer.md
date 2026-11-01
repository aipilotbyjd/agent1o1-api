> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# ClickUp Task Writer

This document outlines the functionality and characteristics of the ClickUp Task Writer node, which enables automated task creation in ClickUp workspaces.

## Node Inputs

### Required Fields

* **Task Name**: Title of the task
* **Team**: Your ClickUp workspace
* **Space**: Team space
* **Folder**: Contains lists
* **List**: Where task will be created

### Optional Fields

* **Task Description**: Detailed task information
* **Assignees**: Team members to assign
* **Status**: Task status (e.g., Complete, In Progress)
* **Priority**: Task urgency level

## Node Output

* **Task URL**: Link to the created task

## Node Functionality

The ClickUp Task Writer node creates new tasks in specified ClickUp lists.

**Key features include**:

* Hierarchical task placement
* Multiple task fields
* Team assignment
* Status management
* Priority settings
* Loop mode to write multiple tasks
* Secure authentication with Gumloop

## When To Use

The ClickUp Task Writer node is valuable for task creation automation. Common use cases include:

* **Project Management**: Create tasks for new projects
* **Ticket Creation**: Convert external requests to tasks
* **Process Automation**: Generate tasks from triggers
* **Task Assignment**: Create and assign tasks automatically

**Some specific examples**:

* Creating tasks from form submissions
* Converting email requests into assigned tasks
* Generating scheduled project tasks
* Creating templated task sequences

## Important Considerations:

1. Requires Authentication with ClickUp - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Task name is required
3. Assignees must be team members
4. Use the configure inputs option to dynamically expose Team, Space, Folder, List & Status fields as inputs

In summary, the ClickUp Task Writer node streamlines task creation in ClickUp, supporting detailed task configuration and automatic assignment for efficient project management.
