> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Monday.com Board Updater

This document provides a guide for using the `Monday.com Board Updater` node, which is designed to update existing items in a specified Monday.com board. This node is particularly useful for workflows where you need to modify or correct data on Monday.com in real-time.

## Node Inputs

The `Monday.com Board Updater` node requires several key inputs:

* **Workspace**: Select the specific [Monday.com workspace](https://www.monday.com) where the data to be updated resides.
* **Board**: Choose the specific board within the selected workspace where items will be updated.
* **Search Column**: Specify the column to search within, to identify the item(s) that need updating. For example, you might search by "Task Name" or "ID".
* **Updater Mode**: Choose between two update modes:
  * **Update a Single Item**: Updates only one item that matches the search value.
  * **Update Multiple Items**: Updates all items that match the search value.
* **Search Value**: Enter the specific value to search for within the specified search column. This value should match the exact text in the Monday.com board to locate the correct item(s).

## Node Output

Status of the write operation.

## Node Functionality

The `Monday.com Board Updater` node allows users to modify existing data in Monday.com boards without manually editing items. It is commonly used to:

Note: Use 'Configure Inputs' option to make certain fields dynamic inputs for Loop Mode operations.

* Correct data in real-time.
* Automate updates to items based on triggers in other systems.
* Batch update multiple items that share a specific attribute.

Note:

### Example Workflow

Suppose you have a Monday.com board that tracks project tasks. One column, "Status," holds values like "In Progress" and "Completed."

**Scenario**: Automatically mark tasks as "Completed" if they reach a certain stage in your workflow.

**Steps**:

1. Set **Workspace** to your Monday.com workspace (e.g., "Main workspace").
2. Select **Board** as your task-tracking board (e.g., "Project Tasks").
3. Set **Search Column** to "Task Name" to locate items by their task names.
4. Choose **Updater Mode** as "Update a Single Item" if updating only one task at a time.
5. Enter **Search Value** as the specific task name, e.g., "Design Phase".
6. Set the **Status** column value to "Completed".

This setup allows the node to locate the "Design Phase" task in the "Task Name" column and update its "Status" to "Completed" automatically.

## Important Considerations

1. Requires Authentication with Monday.com – Set up in the [Connectors page](https://www.gumloop.com/personal/connectors).
2. **Pre-requisite**: Ensure the Gumloop app is installed and authorized in your Monday.com workspace.
3. **Search Value Precision**: The search value must exactly match the value in your specified search column to locate the correct item(s).

In summary, the `Monday.com Board Updater` node simplifies data management by allowing seamless updates to items within Monday.com. With its flexible configuration and ability to batch update, it is a valuable tool for maintaining accurate and up-to-date information in your project boards.
