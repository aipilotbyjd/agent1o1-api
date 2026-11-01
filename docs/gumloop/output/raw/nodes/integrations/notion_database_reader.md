> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Notion Database Reader

This document outlines the functionality and characteristics of the Notion Database Reader node, which enables automated data retrieval from Notion databases.

## Node Inputs

### Required Field

* **Select Database**: Choose the Notion database to read from

### Optional Field

* **Number of Records**: Limit the number of records to retrieve (default: 10)

## Node Output

Each database property becomes an output containing the corresponding values as a list.

## Node Functionality

The Notion Database Reader node retrieves data from Notion databases.

**Key features include**:

* Dynamic property mapping
* Customizable record limits
* Direct database access
* Secure authentication with Gumloop

### Trigger Functionality

This node can also function as a trigger to start your workflow when your Notion database is updated. Learn more about triggers in our [Workflow Triggers documentation](https://docs.gumloop.com/core-concepts/workflow_triggers).

## When To Use

The Notion Database Reader node is particularly useful when you need to:

* **Data Export**: Extract database content for processing or analysis
* **Status Monitoring**: Track changes in project or task status
* **Information Retrieval**: Pull specific records based on criteria
* **Automated Updates**: Trigger workflows when database entries change

**Some specific examples**:

* Reading task statuses for progress reports
* Extracting project data for analytics
* Accessing inventory levels for monitoring
* Retrieving contact information for communications

## Example

To read from a task database:

* Select Database: "Project Tasks"
* Number of Records: 10

Outputs will include lists for each database property (e.g., Task Name, Status, Due Date)

## Important Considerations:

1. Requires Authentication with Notion - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Database must be shared with Gumloop during the authentication
3. Output format matches database property types
4. Can trigger automations on database changes
5. Limited to 10 records by default
6. If database structure changes, node may need refresh

In summary, the Notion Database Reader node provides reliable access to Notion database content with optional trigger functionality to fully automate your workflows.
