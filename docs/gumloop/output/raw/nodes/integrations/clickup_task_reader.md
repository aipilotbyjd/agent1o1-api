> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# ClickUp Task Reader

This document outlines the functionality and characteristics of the ClickUp Task Reader node, which enables automated task data retrieval from ClickUp workspaces.

## Node Inputs

### Required Hierarchy Selection

* **Team**: Your ClickUp workspace
* **Space**: Team space
* **Folder**: Folder to read tasks from
* **List**: Contains tasks

### Optional Fields

* **Task Information To Read**: Select specific data fields to retrieve
* **Filters**: Filter tasks by status, priority, tags, or assignee
* **Number of Tasks**: Limit results (default: 10)

## Node Output

Selected task information provided as lists (string\[]).

## Node Functionality

The ClickUp Task Reader node retrieves task data based on specified criteria.

**Key features include**:

* Hierarchical navigation
* Flexible data selection
* Multiple filter options
* Customizable task limits
* List format outputs
* Secure authentication with Gumloop

## When To Use

The ClickUp Task Reader node is valuable for task management automation. Common use cases include:

* **Project Monitoring**: Track task statuses and progress
* **Workload Analysis**: Review task assignments and priorities
* **Reporting**: Generate task-based reports
* **Process Automation**: Trigger workflows based on task data

**Some specific examples**:

* Collecting overdue high-priority tasks
* Monitoring unassigned tasks in specific lists
* Generating daily task status reports
* Tracking project milestone completion

## Important Considerations:

1. Requires Authentication with ClickUp - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Output format depends on selected information

In summary, the ClickUp Task Reader node streamlines task data retrieval from ClickUp, supporting filtered access and detailed information extraction for project management automation.
