> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Notion Database Updater

This document outlines the functionality and characteristics of the Notion Database Updater node, which enables updating existing records in Notion databases.

## Node Inputs

### Required Fields

* **Select Database**: Choose the Notion database to update
* **Search Column**: Column to use for identifying the record to update
* **Search Value**: Value to match in the search column

### Optional Field

* **Update Body Text**: Toggle to update the page's content

## Node Output

* **Page Link**: URL to access the updated Notion page

## Node Functionality

The Notion Database Updater node modifies existing records in Notion databases.

**Key features include**:

* Exact value matching for record identification
* Body text modification support
* Loop Mode for batch updates
* Secure authentication with Gumloop

## When To Use

The Notion Database Updater node is essential when you need to modify existing database records. Common use cases include:

* **Status Updates**: Update task or project statuses when specific events occur
* **Record Maintenance**: Modify existing entries with new information
* **Progress Tracking**: Update completion percentages or milestones
* **Content Revision**: Modify page content based on external changes

**Some specific examples**:

* Updating task status when moving through workflow stages
* Modifying priority levels based on new criteria
* Updating deadline dates when schedules change
* Refreshing content details when source information changes

## Example: Using Search Column and Search Value

The Search Column and Search Value fields work together to identify which record(s) to update in your Notion database. Think of them as the database equivalent of a "find" operation.

### How It Works

| Field             | Purpose                                     | Example      |
| ----------------- | ------------------------------------------- | ------------ |
| **Search Column** | Specifies which column to look in           | "Project ID" |
| **Search Value**  | Specifies what value to find in that column | "PRJ-2025"   |

When you set these values, the node will:

1. Look in the specified column ("Project ID")
2. Find the row where that column contains your search value ("PRJ-2025")
3. Update the matching row

### Example Database

Here's how this would work with a sample Project Tracker database in Notion:

| Project ID | Project Name     | Status      | Assigned To | Due Date       |
| ---------- | ---------------- | ----------- | ----------- | -------------- |
| PRJ-2025   | Website Redesign | In Progress | Sarah       | April 15, 2025 |
| PRJ-2026   | Mobile App       | Not Started | Michael     | May 20, 2025   |
| PRJ-2027   | SEO Campaign     | In Progress | Alex        | April 10, 2025 |

If you configure:

* Search Column: "Project ID"
* Search Value: "PRJ-2025"
* Connect "Completed" to the Status input

Then **only** the "Website Redesign" project will be updated to have Status = "Completed". The other projects remain unchanged.

### Multiple Updates with Loop Mode

When using Loop Mode, you can update multiple records at once:

| Loop Input                              | Result                                                      |
| --------------------------------------- | ----------------------------------------------------------- |
| Search Value: \["PRJ-2025", "PRJ-2027"] | Updates both the Website Redesign and SEO Campaign projects |
| Status: \["Completed", "Completed"]     | Sets both projects to "Completed" status                    |

## Important Considerations:

1. Requires Authentication with Notion - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Database must be shared with Gumloop during authentication
3. Search value must exactly match the value in the specified column
4. Only updates existing records (to create new records, you can use the 'Notion Database Writer' node)
5. Node must be refreshed if database structure changes

In summary, the Notion Database Updater node provides a reliable way to modify existing Notion database records. For adding new records, use the Notion Database Writer node instead.
