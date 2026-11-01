> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# CSV Reader

The `CSV Reader` node is designed to read data from a CSV file and convert specified columns into separate output lists. This node simplifies working with structured data by allowing users to manually select which columns they want to extract from their CSV files.

## Node Inputs

* **CSV File**
  * **Type**: File
  * **Description**: The CSV file to be processed by the CSV Reader node. This file should have column headers in the first row.

* **Column Headers**
  * **Description**: A list of column headers that you want to extract from the CSV file. Each header must match exactly with the corresponding header in your CSV file. These headers will determine which columns are extracted and made available as outputs.

## Node Outputs

The `CSV Reader` creates an output for each column header specified in the Column Headers input. Each output will contain the data from the corresponding column as a list.

For example, if you specify the headers \["First Name", "Email", "Phone"], the node will create three list outputs:

* First Name
* Email
* Phone

## Node Functionality

The `CSV Reader` node reads content from a CSV file and extracts data from the columns specified in the Column Headers input. Each specified column's data is output as a structured list that can be used in downstream steps within your workflow.

## When To Use

Use the `CSV Reader` node whenever you need to extract specific columns of data from a CSV file. This node is particularly useful when you know exactly which columns you need from your CSV files and want to ensure precise control over the data extraction process.

### Common Use Cases

* **Selective Data Importing**: Extracting specific contact fields from a CSV for import into a CRM system
* **Targeted Sales Analysis**: Reading specific metrics from sales data exports
* **Custom Data Migration**: Importing selected product details from a CSV into a database

## Key Features

* **Manual Column Selection**: Users specify exactly which columns they want to extract, providing precise control over data extraction
* **Exact Header Matching**: Ensures data integrity by requiring exact matches between specified headers and CSV file headers
* **Column-Based Output**: Each specified column of data is output as a separate list
* **Batch Processing Support**: Supports Loop Mode, enabling batch processing of multiple CSV files in a single workflow
* **Dynamic File Input**: Users can define the column headers and pass the CSV file dynamically as an input. Great for workflows with interface or where the CSV is extracted from external sources like Gmail, Slack, Drive, etc.

## Important Notes

1. **Header Matching**: Column headers must match exactly with the headers in your CSV file, including case sensitivity and spacing
2. **Missing Headers**: If a specified header is not found in the CSV file, the node will generate an error

The `CSV Reader` node is a powerful tool for data extraction, offering precise control over which columns are processed from your CSV files and support for dynamic file input and loop mode.
