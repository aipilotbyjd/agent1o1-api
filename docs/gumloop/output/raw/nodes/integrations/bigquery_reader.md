> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# BigQuery Reader

This document outlines the functionality and characteristics of the BigQuery Reader node, which enables automated data retrieval from Google BigQuery.

## Node Inputs

### Required Fields

* **Project**: Your Google Cloud project ID
  * Example: `my-gcp-project`
* **Dataset**: The dataset containing your target table
  * Example: `my_dataset`
* **Table**: The specific table to query
  * Example: `my_table`
* **Query**: Your SQL SELECT query
  * Example: `SELECT * FROM my_dataset.my_table`

### Optional Field

* **Maximum Bytes Billed**: Limit to control query costs

## Node Output

Each selected column in your query becomes an output containing a list of values from that column.

## Node Functionality

The BigQuery Reader node executes SQL queries against Google BigQuery tables.

**Key features include**:

* Support for complex SQL queries
* Cost control through byte billing limits
* Batch processing capabilities
* Dynamic output generation
* Secure authentication with Gumloop

## When To Use

The BigQuery Reader node is particularly valuable in scenarios requiring data extraction from BigQuery. Common use cases include:

* **Data Analysis**: Extract datasets for processing
* **Reporting**: Generate regular business reports
* **Data Migration**: Move data between systems
* **Monitoring**: Track changes in data over time

**Some specific examples**:

* Daily sales performance analysis
* Customer behavior tracking
* Inventory level monitoring
* Transaction pattern analysis

## Example

To query recent sales data:

```sql theme={"dark"}
SELECT 
  date,
  product_name,
  quantity,
  revenue
FROM my_dataset.sales
WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
```

## Important Considerations:

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Use column specifications instead of SELECT \*
3. Include WHERE clauses to limit data when possible
4. May require reauthentication based on Google Admin policies

### Resolving Authentication Issues

If you experience frequent reauthentication requests:

1. Set Gumloop as a Trusted App in Google Admin console
2. Adjust reauthentication policies for Trusted Apps
3. For detailed guidance, visit [Google Admin Reauthentication Policy](https://support.google.com/a/answer/9368756)

In summary, the BigQuery Reader node provides powerful data extraction capabilities from Google BigQuery, with features for cost control and efficient data retrieval. For authentication support, [reach out to us](https://portal.usepylon.com/gumloop/forms/help).
