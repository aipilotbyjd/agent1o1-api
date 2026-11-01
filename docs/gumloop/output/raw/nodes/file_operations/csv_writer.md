> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# CSV Writer

# CSV Writer

The `CSV Writer` node enables you to create new CSV files or modify existing ones with structured data.

## Required Inputs

* **Column Header Names**: List of headers that define your CSV structure
  * Each header automatically creates a corresponding list input on the node
  * Example: Headers \["Name", "Age", "Email"] create three list inputs
  * Required for both new and existing files

* **CSV Filename**: Name for your new file (e.g., "output.csv")
  * Required only when creating a new file

## Optional Configuration

* **Write to Existing CSV?**: Toggle between creating new or modifying existing file
  * When enabled, additional inputs appear:
    * **CSV File**: Select the CSV file to modify
    * **Writer Mode**: How data is written to existing file
      * **Add New Row**: Appends a new row at the end of the CSV
      * **Write to Column**: Adds data to columns starting at the last filled entry

## Node Output

* **Generated CSV File**: The new or modified CSV file

## Key Features

* **Dynamic Inputs**: Headers automatically create corresponding list inputs
* **Flexible Operations**: Create new files or modify existing ones
* **Batch Processing**: Supports Loop Mode for multiple file operations

# Node Functionality

The CSV Writer node is designed for adding or updating data in a structured way within a CSV file. It can either append a new row to keep the data sequential or populate data directly into specified columns. This flexibility makes it suitable for systematic data updates without altering the integrity of the original data file.

## When To Use

Use the CSV Writer node for scenarios where you need to continuously add or organize data within a CSV, such as:

* **Updating Reports**: Append weekly or monthly metrics, like sales or inventory numbers, to an ongoing report.
* **Data Compilation**: Collect pieces of information over time and compile them into a structured format, either as new rows or updated columns.

## Important Notes

1. **Dynamic List Inputs**: All the CSV header inputs expect data in `List` type. Refer to the 'List Operation' nodes if you're dealing with data in a different format.
2. **Data Structure Integrity**: If you're using an existing file the node appends data without overwriting existing entries, maintaining data integrity.

The CSV Writer node is an essential tool for workflows that require frequent data additions or updates to CSV files, streamlining data management and improving organization.
