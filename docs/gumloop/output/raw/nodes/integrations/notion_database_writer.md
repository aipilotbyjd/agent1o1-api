> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Notion Database Writer

This document outlines the functionality and characteristics of the Notion Database Writer node, which enables automated data writing to Notion databases.

## Node Inputs

### Required Field

* **Select Database**: Choose the Notion database to write to

### Optional Field

* **Add Body Text**: Toggle to include content in the page body
* **Dynamic Input Fields**: Based on your database columns

## Node Output

* **Page Link**: URL to access the newly created Notion page

## Node Functionality

The Notion Database Writer node creates new pages/rows in Notion databases.

**Key features include**:

* Automatic input fields based on column headers
* Body text support
* Loop Mode for batch writing on different databases
* Secure authentication with Gumloop

## When To Use

You can use the Notion Database Writer node when you need to programmatically add new information to a Notion database as part of an automated process. This is particularly useful for scenarios such as:

* Automatically logging data from another system to Notion for organization or analysis purposes.
* Capturing form responses and recording them directly into a Notion database.
* Synchronizing data between Notion and other platforms or services by adding new entries automatically whenever updates are detected elsewhere.

## Important Considerations:

1. Requires Authentication with Notion - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Database must be shared with Gumloop integration

In summary, the Notion Database Writer node streamlines data entry into Notion databases, supporting various field types and enabling automated workflow integration with Notion's organizational capabilities.
