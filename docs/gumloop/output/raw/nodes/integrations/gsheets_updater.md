> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Sheets Updater

This document outlines the functionality and characteristics of the Google Sheets Updater node, which enables updating existing records in Google Sheets.

## Node Inputs

### Sheet Selection (Choose one method)

* **Select Sheet**: Choose a Google Sheet directly from your Drive
* **Use Link**: Option to use a direct Google Sheets URL

### Configuration Options

* **Sheet Name**: Specific worksheet within the Google Sheets file
* **Search Column**: Column to use for identifying the row(s) to update
* **Search Value**: Value to match in the search column

### Updater Mode

Defines how the node updates data in your Google Sheet:

1. **Update A Single Row**
   * Updates one specific row at a time
   * Search Value: Single text (e.g., "Gumloop")
   * Column inputs: Single values (e.g., "Active", "2024-01-13")
   * Example: Update status for company "Gumloop" to "Active"

2. **Update Multiple Rows**
   * Updates multiple rows in one operation
   * Search Value: Must be a list (e.g., \["Gumloop", "Acme", "TechCorp"])
   * Column inputs: Must be lists of the same length
   * Example: Update status for three companies to \["Active", "Pending", "Inactive"]

### Data Input

Connect your node outputs directly to the column headers you want to update. The node will automatically map the data to the appropriate columns based on these connections.

## Upsert Option

Under **Show More Options**, you'll find a **Upsert** toggle that enhances update operations.

<div align="center">
  <img src="https://mintcdn.com/agenthub/2SeNKuMcWLM9C5iF/images/upsert_option.png?fit=max&auto=format&n=2SeNKuMcWLM9C5iF&q=85&s=edc58c5888eb84f6118c411e4d758b2c" alt="Upsert option toggle" width="400" data-path="images/upsert_option.png" />
</div>

### What is Upsert?

Upsert combines "update" and "insert" functionality in one operation:

* If a record matching your Search Value exists, it will be updated
* If no matching record is found, a new row will be created automatically

> New rows are added at the end of the sheet

### When to Use Upsert

* Updating records that may not exist yet
* Simplifying workflows that would otherwise require conditional logic

## Refreshing Column Headers

> **Important**: If you modify column headers in your Google Sheet, you must refresh the node's column data in Gumloop to see these changes.

<div align="center">
  <img src="https://mintcdn.com/agenthub/OIDhR9iY2uRNFi5X/images/gsheet_updater_refresh_icon.png?fit=max&auto=format&n=OIDhR9iY2uRNFi5X&q=85&s=5f81e226d666398ec0c55c77e52b4dee" alt="Google Sheet Updater refresh button" width="400" data-path="images/gsheet_updater_refresh_icon.png" />
</div>

To refresh column headers:

1. Click the refresh icon (🔄) next to the Sheet Name dropdown
2. This will update the available column inputs to match your current Google Sheet structure
3. You'll need to reconnect any node outputs to columns that have been renamed

**When to refresh your column headers:**

* After adding new columns to your spreadsheet
* After renaming existing columns
* After deleting columns that are no longer needed
* When new columns in your sheet don't appear as inputs in the node

Failure to refresh column headers after modifying your spreadsheet structure is a common cause of workflow failures. Always refresh when you make changes to your Google Sheet's first row.

## Understanding Search Column and Search Value

The "Search Column" and "Search Value" fields work together to find the specific row(s) you want to update in your Google Sheet.

### How It Works

Think of these fields as creating a filter for your spreadsheet rows:

1. **Search Column**: The column you'll use to identify rows (like using a product ID to find its inventory record)
2. **Search Value**: The specific value to look for in that column (like "PROD-123")

### Example: Product Inventory Update

Let's say you have a Google Sheet with product information:

| Product ID | Product Name      | Category    | Price   | Stock |
| ---------- | ----------------- | ----------- | ------- | ----- |
| PROD-001   | Wireless Mouse    | Electronics | \$29.99 | 45    |
| PROD-002   | USB-C Cable       | Accessories | \$12.99 | 78    |
| PROD-003   | Bluetooth Speaker | Electronics | \$49.99 | 15    |

To update the stock level for the Bluetooth Speaker:

1. **Search Column**: Choose "Product ID" (since product IDs are unique)
2. **Search Value**: Enter "PROD-003"
3. **Update Fields**: Connect "Stock" to a node that outputs "25"

When the workflow runs, the node will:

* Search the "Product ID" column for "PROD-003"
* Find the Bluetooth Speaker's row
* Update only its "Stock" field to "25"
* Leave all other fields and rows unchanged

### Multiple Row Updates

For updating several products at once:

1. **Updater Mode**: Set to "Update Multiple Rows"
2. **Search Column**: "Product ID"
3. **Search Value**: Connect to a list like: \["PROD-001", "PROD-002"]
4. **Update Fields**: Connect "Price" to a list like: \["$24.99", "$9.99"]

This will update the prices for both the Wireless Mouse and USB-C Cable in a single operation.

### Important Tips

* **Choose Unique Identifiers**: When possible, use columns with unique values (IDs, emails)
* **Exact Matching**: Search values must match exactly (including case)
* **No Records Found**: If no matching records are found, the node will error out
* **Multiple Matches**: If multiple records match your search value, the first instance is updated

## Node Output

* **Sheet Link**: URL to access the updated Google Sheet

## Node Functionality

The Google Sheets Updater node modifies existing records within Google Sheets.

**Key features include**:

* Exact value matching for precise row identification
* Dynamic column mapping through node connections
* Loop Mode support for batch updates
* Secure authentication with Gumloop

## When To Use

The Google Sheets Updater node is particularly valuable in scenarios requiring modification of existing spreadsheet data. Common use cases include:

* **Data Maintenance**: Update records when information changes
* **Status Updates**: Modify status fields based on events
* **Inventory Management**: Update stock levels
* **Record Tracking**: Maintain current information across records

**Some specific examples**:

* Updating task status
* Modifying contact information
* Refreshing inventory counts
* Adjusting project timelines

## Example

Let's say you have a customer order tracking sheet:

Your Google Sheet has columns: `Order ID`, `Customer Name`, `Status`, `Last Updated`

To update an order status:

* Search Column: "Order ID"
* Search Value: "ORD-123"
* Connect your data to the column inputs, eg:
  * Status: "Completed"
  * Last Updated: current timestamp

The node will find the row with order ID "ORD-123" and update the corresponding columns, leaving other data unchanged.

## Important Considerations:

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Sheet must have headers in the first row
3. Can only update existing rows (use Google Sheets Writer for new rows)
4. After modifying column headers in your Google Sheet, click the refresh button (🔄) next to the Sheet Name to update the available column inputs in Gumloop
5. Search value must exactly match the data in the specified search column
6. Ensure search column contains unique values for accurate updates

In summary, the Google Sheets Updater node provides a reliable way to modify existing records in your Google Sheets. For adding new records, use the Google Sheets Writer node instead.
