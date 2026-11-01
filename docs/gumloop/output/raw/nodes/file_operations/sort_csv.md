> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sort CSV

The `Sort CSV` node is used to organize data within a CSV file by sorting rows based on a specified column, making it easier to analyze or visualize data.

## Node Inputs

* **CSV File Name**
  * **Type**: File
  * **Description**: The CSV file to be sorted.

* **Column Index**
  * **Type**: Integer
  * **Description**: Specifies the column to sort by, with zero-based indexing (e.g., 0 for the first column, 1 for the second).

* **Sorted CSV File Name**
  * **Type**: Text
  * **Description**: Name for the generated sorted CSV file.

* **Has Headers**
  * **Type**: Boolean
  * **Description**: Indicates if the CSV file includes a header row. If true, the first row will be treated as the header and preserved at the top of the file.
  * **Optional**: Default is `false`.

* **Reverse Sort**
  * **Type**: Boolean
  * **Description**: If true, sorts the selected column in descending order. Otherwise, the default is ascending.
  * **Optional**: Default is `false`.

## Node Output

* **Sorted CSV File**
  * **Description**: Returns the newly created sorted CSV file.

## Node Functionality

The `Sort CSV` node sorts rows within a CSV file based on a specified column index. Users can choose ascending or descending order, and the node automatically maintains the header row (if specified). This node makes it simple to reorganize data by any numeric or text-based column.

The resulting sorted file is saved with the user-defined name, allowing for easy retrieval and further use in workflows or for download.

## When To Use

Use the `Sort CSV` node whenever you need to arrange CSV data for better readability or analysis. Some typical scenarios include:

* **Prioritizing Data**: Sorting by metrics such as revenue, score, or date to quickly identify top performers or trends.
* **Data Preparation**: Organizing data before generating reports or feeding it into analysis tools, ensuring ordered presentation.
* **Data Cleaning**: Restructuring data in a specific order for consistency across datasets.

## Usage Example

Imagine you have a CSV with customer data and want to sort by the "Age" column (index 2). Configure the node as follows:

* **Column Index**: `2`
* **Has Headers**: `true` (if the CSV has headers)
* **Reverse Sort**: `false` (for ascending order)

The `Sort CSV` node will then generate a sorted CSV file based on the specified settings.

## Additional Notes

* **Batch Processing**: The node supports loop mode, enabling you to sort multiple CSV files in a single workflow.
* **File Compatibility**: Ideal for structured data in CSV format; however, ensure the target column is correctly indexed and contains compatible data types for consistent sorting.

This node simplifies CSV data sorting, allowing users to organize large datasets efficiently without manual intervention or additional software.
