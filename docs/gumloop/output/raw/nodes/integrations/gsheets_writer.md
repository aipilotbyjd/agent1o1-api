> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Sheets Writer

This document outlines the functionality and characteristics of the Google Sheets Writer node, which enables automated data writing to Google Sheets.

## Node Overview

The Google Sheets Writer node allows you to write data to Google Sheets from your Gumloop workflows. It can append new rows, add a single row, or write to specific columns in your spreadsheet.

## Basic Node Operation

The Google Sheets Writer node works by mapping your workflow's outputs to spreadsheet columns:

1. **Sheet Connection**: Connect to an existing Google Sheet
2. **Column Detection**: The node reads the headers in the first row of your selected sheet
3. **Dynamic Inputs**: These headers become available as inputs to the node
4. **Data Mapping**: Connect outputs from previous nodes to these column inputs
5. **Execution**: When the workflow runs, data is written to the corresponding columns

For example, if your sheet has headers "Name", "Email", and "Date", these will appear as input options on the node. You can then connect data from other nodes directly to these inputs.

## Node Inputs

### Sheet Selection (Choose one method)

* **Select Sheet**: Choose to write to an existing Google Sheet from your Drive
* **Use Link**: Option to use a direct Google Sheets URL

### Writer Mode

Choose how to write data to your sheet:

1. **Add New Rows**:

* Appends new rows at the bottom of your sheet
* Preserves existing data
* Best for logging or adding new records over time

2. **Add A Single New Row**:

* Appends one row at the end with data from connected nodes

3. **Write to Column**:

* Adds data to a specified column based on connected input
* Writes data vertically down a single specified column

### Column Inputs

Once connected to a sheet, the node dynamically displays inputs matching the column headers found in the first row of your sheet. Connect your node outputs directly to these column headers to map your data to the appropriate columns in the spreadsheet.

## Refreshing Column Headers

> **Important**: If you modify column headers in your Google Sheet, you must refresh the node's column data in Gumloop to see these changes.

<div align="center">
  <img src="https://mintcdn.com/agenthub/OIDhR9iY2uRNFi5X/images/gsheet_writer_refresh_icon.png?fit=max&auto=format&n=OIDhR9iY2uRNFi5X&q=85&s=cc1490086bc3383097a17ddc1a7932a7" alt="Google Sheet Writer refresh button" width="400" data-path="images/gsheet_writer_refresh_icon.png" />
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

## Node Output

* **Sheet Link**: URL to access the Google Sheet where data was written

## Additional Features

### Create New Sheet Option

Under "Show More Options", you can enable the "Create New Sheet" feature, which creates a copy of the selected sheet's schema in a new workbook. When enabled, you can configure:

* **New Sheet Name**: Specify a name for your new sheet (optional)
* **New Sheet Permissions**: Set access levels for your new sheet:
  * **Keep Sharing Settings**: Maintain your default Google Drive sharing settings
  * **Anyone Can Edit**: Allow anyone with the link to edit the sheet
  * **Anyone Can View**: Allow anyone with the link to view (but not edit) the sheet
  * **Private**: Restricts access to only you

### Configure Inputs

The node allows you to configure any of its parameters as dynamic inputs. You can enable these in the "Configure Inputs" section, including:

* **New Sheet Name**: Name for your newly created Google Sheet
* **Sheet URL**: The URL of the Google Sheet
* **Sheet Name**: The name of the worksheet within the Google Sheet
* Writer Mode parameters
* And any other node parameters

This allows you to dynamically set values from previous nodes rather than using static configurations.

## Example Use Cases

### 1. Customer Feedback Collection

```text theme={"dark"}
Website Scraper → Extract Data → Google Sheets Writer
Setup:
- Writer Mode: Add New Rows
- Columns: Company, Rating, Feedback
Result: Automated collection of online reviews
```

### 2. Daily Metrics Logging

```text theme={"dark"}
Current Datetime → Web Scraper → Extract Data → Google Sheets Writer
Setup:
- Writer Mode: Add A Single New Row
- Columns: Date, Visits, Conversions, Revenue
Result: Daily performance tracking in spreadsheet
```

### 3. Creating a New Report Sheet

```text theme={"dark"}
CSV Reader → Google Sheets Writer
Setup:
- Select Sheet: [Template Sheet with proper columns]
- Create New Sheet: Yes (under Show More Options)
- New Sheet Name: "Q2 Sales Report"
- New Sheet Permissions: Anyone Can View
- Writer Mode: Add New Rows
Result: New shareable report with same schema as template but in a new workbook
```

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Sheet must have headers in the first row for column mapping to work
3. You must have write access to the Google Sheet
4. After modifying column headers in your Google Sheet, click the refresh button (🔄) next to the Sheet Name to update the available column inputs in Gumloop
5. This node only adds new rows - to update existing rows, use the Google Sheets Updater node
6. If you're facing a type mismatch error, toggle the Writer Mode to "Add New Rows" if you're writing multiple rows
7. If "Create New Sheet" option is enabled (found under "Show More Options"):
   * An existing sheet must first be selected (to copy the schema)
   * Google Drive credentials are required
   * You must have permission to write to that Google Drive

In summary, the Google Sheets Writer node streamlines data writing to Google Sheets by adding new rows or columns, with additional options to create new sheets with specific permissions. For updating existing data, consider using the Google Sheets Updater node instead.
