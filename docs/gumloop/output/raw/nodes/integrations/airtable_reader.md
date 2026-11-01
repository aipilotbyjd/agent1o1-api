> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Airtable Reader

This document outlines the functionality and characteristics of the Airtable Reader node.

## Node Inputs

### Required Fields

* **Base**: The specific Airtable base from which data will be read
* **Table**: The particular table within the selected base to retrieve data from
* **View**: Select which view to read from within the table

### Optional Fields

* **Columns**: Specify which columns to fetch (if not specified, fetches all columns)
* **Number of Records**: How many rows to fetch from the table
  * When set to 1: Returns a single record as text
  * When greater than 1: Returns an array of records as a list
* **Row Range**: Specify exact rows to fetch (e.g., "2-5, 8, 11-13")

## Refreshing Field Options

> **Important**: If you modify your fields in your Airtable base, you must refresh the node's field data in Gumloop to see these changes.

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/airtable_reader_refresh_icon.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=2a404c554b7b3cce5722f1eb5cad0239" alt="Airtable Reader refresh button" width="400" data-path="images/airtable_reader_refresh_icon.png" />
</div>

To refresh field options:

1. Click the refresh icon (🔄) next to the Table dropdown
2. This will update the available fields to match your current Airtable table structure
3. You'll need to reconnect any outputs that were using fields that have been renamed

**When to refresh your field options:**

* After adding new fields to your Airtable table
* After renaming existing fields in your table
* After deleting fields that are no longer needed
* When new fields in your table don't appear as outputs in the node

Failure to refresh field options after modifying your Airtable table structure is a common cause of workflow failures. Always refresh when you make changes to your table fields.

### Search Parameters

#### How to Filter Records (Search Column & Value)

Think of Search Column and Search Value like a filter for your data:

* First, pick which column you want to filter by (Search Column)
* Then, specify what you're looking for in that column (Search Value)

**Simple Example:**

```text theme={"dark"}
If your table has a "Status" column and you want to find all "Active" projects:
- Search Column: Status
- Search Value: Active
→ This will only return records where Status = "Active"
```

**Important Notes:**

* Must match exactly (including letter case)
* "Active" will not match "active" or "ACTIVE"
* "In Progress" will not match "In progress"
* No partial matches ("Alex" won't find "Alexander")

**More Examples:**

1. Finding High Priority Tasks

```text theme={"dark"}
Search Column: Priority
Search Value: High
Result: Only returns tasks marked as exactly "High"
```

2. Finding Orders by Status

```text theme={"dark"}
Search Column: Order Status
Search Value: Shipped
Result: Only returns orders with status exactly "Shipped"
```

3. No Filtering

```text theme={"dark"}
Search Column: No Search Column
Result: Returns all records (no filtering)
```

#### Row Range Explained

Row Range lets you pick specific rows to read:

* First row (row 1) is always headers, so start with row 2
* Use numbers and dashes to specify which rows you want

**Examples:**

```text theme={"dark"}
"2-5"      → Gets rows 2,3,4,5
"2,4,6"    → Gets just rows 2,4,6
"2-5,8"    → Gets rows 2,3,4,5 and 8
```

## Node Output

The output format depends on your configuration:

### Single Record Mode (Number of Records = 1)

* Outputs as text strings for each column
* Example outputs for a contact table:
  * Name: "John Doe"
  * Email: "[john@example.com](mailto:john@example.com)"
  * Phone: "555-0123"

### Multiple Records Mode (Number of Records > 1)

* Outputs as lists of values for each column
* Example outputs for a contact table:
  * Names: \["John Doe", "Jane Smith"]
  * Emails: \["[john@example.com](mailto:john@example.com)", "[jane@example.com](mailto:jane@example.com)"]
  * Phones: \["555-0123", "555-0124"]

## Node Functionality

### Basic Operation

The Airtable Reader node fetches data from your Airtable bases with flexible filtering and output options.

### Trigger Functionality

This node can also function as a trigger to start your workflow when your Airtable table updates:

* Monitors for new/modified records
* Checks every 60 seconds
* Requires "Last Modified Timestamp" field type column
* Learn more about triggers at: [https://docs.gumloop.com/core-concepts/workflow\_triggers](https://docs.gumloop.com/core-concepts/workflow_triggers)

## Example Workflows

### 1. Basic Contact List Processing

```text theme={"dark"}
Airtable Reader → Ask AI → Gmail Sender
Setup:
- Base: Contacts
- Table: Leads
- Number of Records: All
- Columns: Name, Email, Status
Purpose: Send automated emails to new leads
```

### 2. Filtered Status Updates

NOTE: This example shows how to use Search Column and Search Value to filter records

```text theme={"dark"}
Airtable Reader → Combine Text → Slack Message Sender
Setup:
- Base: Projects
- Table: Tasks
- Search Column: Status
- Search Value: "Urgent"
- Number of Records: 1
Purpose: Send urgent task notifications
```

### 3. Data Synchronization

```text theme={"dark"}
Airtable Reader → CSV Writer → Google Sheets Writer
Setup:
- Base: Inventory
- Table: Products
- Row Range: "1-100"
- Columns: Product, Stock, Price
Purpose: Keep inventory spreadsheets in sync
```

## Important Considerations

1. Requires Authentication with Airtable - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Output type changes from list to text when Number of Records = 1
3. Search parameters are case-sensitive and require exact matches
4. View selection can impact available records and columns
5. Row Range cannot start with row 1 (headers)
6. Search Column and Search Value must match exactly (no partial matches)
7. After modifying fields in your Airtable table, click the refresh button (🔄) next to the Table dropdown to update the available field inputs in Gumloop

In summary, the Airtable Reader node provides flexible ways to fetch and filter data from Airtable, with output formatting that adapts to your needs. Whether used as a standard node or trigger, it forms the foundation for many automation workflows involving Airtable data.
