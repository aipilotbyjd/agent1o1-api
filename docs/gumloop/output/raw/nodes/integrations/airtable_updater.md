> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Airtable Updater

This document explains the Airtable Updater node, which lets you update existing records in your Airtable bases.

## Node Inputs

### Required Fields

* **Base**: Choose your Airtable base
* **Table**: Select the table containing records to update
* **Search Column**: Choose which column to use for finding records (ideally a unique identifier column)
* **Updater Mode**: Choose how to update records
  * Update a Single Row: Updates one record using text input
  * Update Multiple Rows: Updates multiple records using list input
* **Search Value**: A unique identifier to find the relevant record to update
  * For single row: Enter text value
  * For multiple rows: Provide list of values
* **Update Fields**: Select which columns to update with new values

## Upsert Option

Under **Show More Options**, you'll find a **Upsert** toggle that enhances update operations.

<div align="center">
  <img src="https://mintcdn.com/agenthub/2SeNKuMcWLM9C5iF/images/upsert_option.png?fit=max&auto=format&n=2SeNKuMcWLM9C5iF&q=85&s=edc58c5888eb84f6118c411e4d758b2c" alt="Upsert option toggle" width="400" data-path="images/upsert_option.png" />
</div>

### What is Upsert?

Upsert combines "update" and "insert" functionality in one operation:

* If a record matching your Search Value exists, it will be updated
* If no matching record is found, a new row will be created automatically

> New rows are added at the end of the table

### When to Use Upsert

* Updating records that may not exist yet
* Simplifying workflows that would otherwise require conditional logic

## Refreshing Field Options

> **Important**: If you modify field names in your Airtable base, you must refresh the node's field data in Gumloop to see these changes.

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/airtable_updater_refresh_icon.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=bdad25b4a49eb6e735a1b64a78554201" alt="Airtable Updater refresh button" width="400" data-path="images/airtable_updater_refresh_icon.png" />
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

* Link to your Airtable base after successful update
* Any error messages if the operation fails

## Understanding Search Column and Search Value

The "Search Column" and "Search Value" fields work together to find the specific record(s) you want to update in your Airtable table.

### How It Works

Think of these fields as creating a filter for your Airtable records:

1. **Search Column**: The column you'll use to identify records (like using a person's email to find their record)
2. **Search Value**: The specific value to look for in that column (like "[john@example.com](mailto:john@example.com)")

### Example: Customer Database Update

Let's say you have an Airtable with customer information:

| Customer ID | Name         | Email                                       | Status   | Last Contact |
| ----------- | ------------ | ------------------------------------------- | -------- | ------------ |
| CUST-001    | John Doe     | [john@example.com](mailto:john@example.com) | Active   | 2023-12-15   |
| CUST-002    | Jane Smith   | [jane@example.com](mailto:jane@example.com) | Inactive | 2023-11-30   |
| CUST-003    | Alex Johnson | [alex@example.com](mailto:alex@example.com) | Pending  | 2024-01-05   |

To update Jane's status from "Inactive" to "Active":

1. **Search Column**: Choose "Email" (since email addresses are unique)
2. **Search Value**: Enter "[jane@example.com](mailto:jane@example.com)"
3. **Update Fields**: Connect "Status" to a node that outputs "Active"

When the workflow runs, the node will:

* Search the "Email" column for "[jane@example.com](mailto:jane@example.com)"
* Find Jane's record
* Update only her "Status" field to "Active"
* Leave all other fields and records unchanged

### Multiple Row Updates

For updating several records at once:

1. **Updater Mode**: Set to "Update Multiple Rows"
2. **Search Column**: "Customer ID"
3. **Search Value**: Connect to a list like: \["CUST-001", "CUST-003"]
4. **Update Fields**: Connect "Status" to a list like: \["Premium", "Premium"]

This will update only the customers with IDs CUST-001 and CUST-003 to have Premium status.

### Important Tips

* **Choose Unique Identifiers**: When possible, use columns with unique values (IDs, emails)
* **Exact Matching**: Search values must match exactly (including case)
* **No Records Found**: If no matching records are found, the node will error out
* **Multiple Matches**: If multiple records match your search value, the first instance is updated

## Node Functionality

The Airtable Updater node modifies existing records in your Airtable bases:

* Finds records using exact value matching
* Supports single or multiple record updates
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

### 1. Update Lead Status

```text theme={"dark"}
Salesforce Reader → Extract Data → Airtable Updater
Setup:
- Updater Mode: Update Multiple Rows
- Search Column: Email
- Column Updates:
  - Status → Lead Status
  - Last Contact → Contact Date
  - Notes → Communication Log
Next Steps: Use Slack Message Sender for team updates
```

### 2. Enrich Company Data

```text theme={"dark"}
Enrich Company Information → Airtable Updater
Setup:
- Updater Mode: Update a Single Row
- Search Column: Company Name
- Column Updates:
  - Industry → Industry
  - Employee Count → Size
  - Revenue → Annual Revenue
  - Website → Website URL
Next Steps: Trigger notifications for sales team
```

### 3. Process Support Tickets

```text theme={"dark"}
Gmail Reader → Categorizer → Airtable Updater
Setup:
- Updater Mode: Update a Single Row
- Search Column: Ticket ID
- Column Updates:
  - Status → Ticket Status
  - Priority → Issue Priority
  - Response → Latest Reply
Next Steps: Use Slack Message Sender for support team alerts
```

### 4. Content Status Updates

```text theme={"dark"}
AI List Sorter → Airtable Updater
Setup:
- Updater Mode: Update Multiple Rows
- Search Column: Content ID
- Column Updates:
  - Priority → Content Priority
  - Status → Publication Status
  - Notes → AI Recommendations
Next Steps: Generate content briefs with Ask AI
```

## Best Practices

### Updater Mode Selection

* Use "Update a Single Row" when:
  * Processing one record at a time
  * Working with single text input/output
  * Needing precise control over updates

* Use "Update Multiple Rows" when:
  * Processing batches of records
  * Working with List type input/outputs
  * Performing bulk updates

## Important Notes

### Authentication

1. Set up Airtable credentials in [Connectors page](https://www.gumloop.com/personal/connectors)
2. Ensure proper base and table permissions
3. For creating new records, use the [Airtable Writer](https://docs.gumloop.com/nodes/integrations/airtable_writer) node instead
4. After modifying field names in your Airtable table, click the refresh button (🔄) next to the Table dropdown to update the available field inputs in Gumloop

In summary, the Airtable Updater node provides a straightforward way to modify existing records in your Airtable bases. Remember to refresh the node whenever you make changes to your Airtable table structure to ensure smooth operation of your automation workflows.
