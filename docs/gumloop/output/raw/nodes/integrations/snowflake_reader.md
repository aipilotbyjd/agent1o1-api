> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Snowflake Reader

This document outlines the functionality of the Snowflake Reader node, which enables executing SELECT queries on Snowflake databases and using the results in automated workflows.

## Node Configuration

* **Query**: SQL SELECT statement to execute
  * Must be a valid SELECT query
  * Can be dynamically provided from previous nodes
  * [View Snowflake SQL reference](https://docs.snowflake.com/en/sql-reference/sql/select)

* **Column Names**: Specify which columns from your query results to expose as outputs
  * Each column becomes a separate output that can connect to other nodes
  * Output is in `List` format

## Authentication

1. Navigate to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Add Snowflake credentials:
   * Account Identifier URL (e.g., `org-account.region.snowflakecomputing.com`)
     * Required for connecting to your Snowflake instance
     * [How to find your account identifier](https://docs.snowflake.com/en/user-guide/admin-account-identifier)
   * Username
   * Password

## Example Workflows

### 1. Sales Performance Alerts

```text theme={"dark"}
Snowflake Reader (sales metrics) → Combine Text → Slack Message Sender
```

* Snowflake Reader fetches daily sales metrics including revenue, targets, and regional performance
* Combine Text formats the data into a structured alert message:
  * Highlights underperforming regions and revenue gaps
  * Shows month-over-month comparisons
  * Includes relevant sales manager contacts
* Slack messages are automatically routed to:
  * Regional sales channels for local performance
  * Executive channel for company-wide metrics
  * Custom channels based on alert severity

### 2. Inventory Management

```text theme={"dark"}
Snowflake Reader (inventory levels) → Gmail Sender → Airtable Writer
```

* Snowflake Reader monitors:
  * Current stock levels across warehouses
  * Historical consumption rates
  * Supplier details and lead times
* Gmail Sender automates:
  * Purchase order emails to suppliers
  * Order confirmation requests
  * Delivery timeline updates
* Airtable Writer maintains:
  * Complete order history
  * Supplier response times
  * Reorder patterns for analysis

### 3. Customer Engagement Automation

```text theme={"dark"}
Snowflake Reader (customer data) → Ask AI → SendGrid Email Sender
```

* Snowflake Reader collects:
  * Purchase history and frequency
  * Product category preferences
  * Customer interaction data
* Ask AI processes this data to:
  * Generate personalized product recommendations
  * Create customized email content
  * Optimize sending times
* SendGrid Email Sender delivers:
  * Targeted promotional campaigns
  * Personalized follow-up messages
  * Product recommendation emails

### 4. Data Quality Monitoring

```text theme={"dark"}
Snowflake Reader (data quality metrics) → Jira Issue Creator → Slack Message Sender
```

* Snowflake Reader checks for:
  * Missing or null values
  * Data format inconsistencies
  * Anomalous patterns
* Jira Issue Creator automatically:
  * Opens tickets for detected issues
  * Assigns to appropriate team members
  * Sets priority based on impact
* Slack notifications include:
  * Issue summary and severity
  * Direct link to Jira ticket
  * Required action items

## Common Use Cases

1. **Data-Driven Alerts**
   * Performance monitoring
   * Threshold-based notifications
   * SLA tracking
   * Error detection

2. **Automated Reporting**
   * Regular business reports
   * KPI dashboards
   * Compliance reporting
   * Team performance metrics

3. **Process Automation**
   * Customer communications
   * Supply chain management
   * Resource allocation
   * Quality control

4. **Data Integration**
   * Sync data to other platforms
   * Update external systems
   * Consolidate information
   * Cross-platform analytics

## In Summary

The Snowflake Reader node allows seamless integration of Snowflake databases into automated workflows by executing SELECT queries and exposing query results as outputs for further processing. Ideal for data-driven alerts, reporting, and process automation.
