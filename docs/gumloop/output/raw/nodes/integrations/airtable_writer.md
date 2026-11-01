> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Airtable Writer

This document explains the Airtable Writer node, which lets you create new records in your Airtable bases automatically.

## Node Inputs

### Required Fields

* **Base**: Choose your Airtable base
* **Table**: Select the table where you want to write data
* **Writer Mode**: Choose how to write data
  * Add New Rows: Creates multiple rows from a list input
  * Add A Single New Row: Creates one row from a text input
* **Column Inputs**: Headers in your table automatically appear as column inputs
  * Each input must match the column's data type
  * Example: Text for text fields, numbers for numeric fields

## Refreshing Field Options

> **Important**: If you modify your fields in your Airtable base, you must refresh the node's field data in Gumloop to see these changes.

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/airtable_writer_refresh_icon.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=9a9146db41d10c08d9471356ace5e914" alt="Airtable Writer refresh button" width="400" data-path="images/airtable_writer_refresh_icon.png" />
</div>

To refresh field options:

1. Click the refresh icon (🔄) next to the Table dropdown
2. This will update the available field inputs to match your current Airtable table structure
3. You'll need to reconnect any node outputs to fields that have been renamed

**When to refresh your field options:**

* After adding new fields to your Airtable table
* After renaming existing fields in your table
* After deleting fields that are no longer needed
* When new fields in your table don't appear as inputs in the node

Failure to refresh field options after modifying your Airtable table structure is a common cause of workflow failures. Always refresh when you make changes to your table fields.

## Node Output

* Link to your Airtable base after successful write
* Any error messages if the operation fails

## Node Functionality

The Airtable Writer node creates new records in your Airtable bases:

* Matches your data to table columns
* Supports single or multiple row creation
* Maintains secure authentication through Gumloop
* Linked Record Support

## Linked Record Support

The node supports creating linked records between tables in Airtable. This allows you to establish relationships between records in different tables.

### Key Components

* **Primary Table**: Contains the original records to link from
* **Linked Table**: The table you want to link to
* **Link Field**: Column in Airtable with type 'Link to another record'

### How It Works

1. The Link Field appears as a regular input in your node configuration
2. It accepts record values from the Primary Table to create links
3. The record value should ideally be the primary field from your Primary Table
4. If using a non-primary field value, a new record will be created in the Primary Table first
5. Works seamlessly with Airtable Reader node outputs

### Example of Linking Records: Project Tasks and Assignees

```text theme={"dark"}
Primary Table: Tasks
Linked Table: Team Members
Link Field: Assignee

Setup:
- Create a 'Link to another record' field named 'Assignee' in Tasks table
- In Airtable Writer node:
  - Table: Tasks
  - Column Inputs:
    - Task Name → Text
    - Due Date → Date
    - Assignee → Team Member Email (Primary field from Team Members table)
```

### Behavior

* If Team Member Email exists:
  * Creates link to existing team member
  * No new record created
* If Email doesn't exist:
  * Creates new record in Team Members table
  * Then creates the link

## Example Workflows

### 1. Process Form Submissions

```text theme={"dark"}
Get Typeform Responses → Airtable Writer
Setup:
- Writer Mode: Add New Rows
- Table: Customer Feedback
- Column Mapping:
  - Name → Name
  - Email → Email
  - Rating → Score
  - Feedback → Comments
Next Steps: Use Airtable Reader to analyze feedback trends
```

### 2. Lead Generation Pipeline

```text theme={"dark"}
LinkedIn Profile Scraper → Extract Data → Airtable Writer
Setup:
- Writer Mode: Add New Rows
- Table: Sales Leads
- Column Mapping:
  - Full Name → Contact Name
  - Company → Organization
  - Title → Position
  - Location → Region
Next Steps: Connect with Salesforce Updater for CRM sync
```

### 3. Content Calendar Management

```text theme={"dark"}
RSS Feed Reader → AI List Sorter → Airtable Writer
Setup:
- Writer Mode: Add New Rows
- Table: Content Ideas
- Column Mapping:
  - Title → Post Title
  - Link → Source URL
  - Published Date → Date
  - Summary → Description
Next Steps: Use Ask AI to generate content briefs
```

### 4. Support Ticket Logging

```text theme={"dark"}
Gmail Reader → Categorizer → Airtable Writer
Setup:
- Writer Mode: Add A Single New Row
- Table: Support Tickets
- Column Mapping:
  - Subject → Issue
  - Sender → Customer Email
  - Category → Ticket Type
  - Body → Description
Next Steps: Use Slack Message Sender for team notifications
```

## Best Practices

### Writer Mode Selection

* Use "Add New Rows" when:
  * Processing batches of data
  * Working with List type input/outputs
  * Handling multiple records at once

* Use "Add A Single New Row" when:
  * Processing individual items
  * Working with single text input/output
  * Creating one record at a time

## Important Notes

### Authentication

1. Set up Airtable credentials in [Connectors page](https://www.gumloop.com/personal/connectors)
2. Ensure proper base and table permissions
3. For updating existing records, use the [Airtable Updater](https://docs.gumloop.com/nodes/integrations/airtable_updater) node instead
4. After modifying fields in your Airtable table, click the refresh button (🔄) next to the Table dropdown to update the available field inputs in Gumloop

In summary, the Airtable Writer node provides a straightforward way to create new records in your Airtable bases. Remember to refresh the node whenever you make changes to your Airtable table structure to ensure smooth operation of your automation workflows.
