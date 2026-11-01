> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Monday.com Board Writer

This document outlines the functionality and setup of the `Monday.com Board Writer` node, which enables users to write data directly to specified boards and groups on Monday.com, automating the creation of items.

## Node Inputs

The `Monday.com Board Writer` node requires the following inputs:

* **Workspace**: Select the specific [Monday.com workspace](https://www.monday.com) where data will be written.
* **Board**: Choose the specific board within the selected workspace to write data.
* **Group**: Specify the group within the board where the data will be added (e.g., "To-Do", "Completed").

## Node Output

Status of the write operation.

## Node Functionality

The `Monday.com Board Writer` node allows users to automate data entry in Monday.com, integrating it into workflows that involve task creation, project management, and other data-driven processes.

Note: Use 'Configure Inputs' option to make certain fields dynamic inputs for Loop Mode operations.

### Key Features:

* **Loop Mode**: Enables batch processing to create multiple items at once.
* **Dynamic Inputs**: Allows toggling visibility for `Workspace`, `Board`, and `Group` inputs for customized data entry and flexibility in automation.

## When To Use

The `Monday.com Board Writer` node is ideal for workflows that require data to be added or updated on Monday.com automatically. Common use cases include:

* **Task Automation**: Automatically create new tasks based on triggers from other systems.
* **Project Updates**: Send updates directly to a board based on other workflow events.
* **Data Synchronization**: Sync information from other systems into Monday.com in real-time.

**Examples:**

* Creating a new task in the "To-Do" group whenever an email is marked as important.
* Adding completed project data to a "Completed" group for tracking purposes.
* Updating project boards based on data received from external sources.

## Important Considerations:

1. Requires Authentication with Monday.com – Set up in the [Connectors page](https://www.gumloop.com/personal/connectors).
2. **Pre-requisite**: The Gumloop app must be authorized and installed in your Monday.com workspace.
3. **Data Matching**: Ensure the input types match the column types on the Monday.com board to prevent errors during data entry.

In summary, the `Monday.com Board Writer` node is a robust tool for automating data entry in Monday.com, offering flexible configuration options and secure access. It is especially useful for real-time data synchronization and task management within automated workflows.
