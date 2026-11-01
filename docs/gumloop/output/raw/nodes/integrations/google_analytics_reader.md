> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Analytics Reader

This document outlines the functionality and characteristics of the Google Analytics Reader node, which enables automated data retrieval from Google Analytics 4 (GA4).

## Node Inputs

### Required Fields

* **Account**: Select your Google Analytics account
* **Property**: Choose the property within the selected account
* **Metrics**: Choose analytics metrics to retrieve
  * Examples: pageviews, sessions, users, bounce rate

## Node Output

* **Analytics Report**: Text string in JSON format containing the requested metrics data

## Node Functionality

The Google Analytics Reader node fetches analytics data from your GA4 account.

**Key features include**:

* Dynamic parameter population using 'Configure Inputs' option
* Multiple metric selection
* Secure authentication with Gumloop

## Example Workflows

### 1. Basic Analytics Report

```text theme={"dark"}
Google Analytics Reader → JSON Reader → Airtable Writer
Setup:
- Account: Your GA4 account
- Property: Your website property
- Metrics: pageviews, sessions, users
Purpose: Populate your database like Airtable with the analytics metrics
```

### 2. Automated Analytics Notifications

```text theme={"dark"}
Google Analytics Reader → JSON Reader → Slack Message Sender
Setup:
- Account: Your GA4 account
- Property: Your website property
- Metrics: sessions, bounce_rate
Purpose: Sends daily analytics updates to Slack
```

### 3. Analytics Dashboard Integration

```text theme={"dark"}
Google Analytics Reader → JSON Reader → Notion Database Writer
Setup:
- Account: Your GA4 account
- Property: Your website property
- Metrics: users, pageviews, sessions
Purpose: Populates a Notion database with analytics data
```

## Processing the JSON Output

Since the node outputs data in JSON format, you'll typically want to process it for use in other nodes. Here's how:

1. Use the [JSON Reader node](https://docs.gumloop.com/nodes/json/read_json_values) to extract specific metrics
2. Transform data for reporting using nodes like:
   * Airtable Writer
   * Text Formatter
   * Notion Database Writer
   * Slack Message Sender

## Best Practices

### Working with JSON Output

* Use the JSON reader node or create a [custom node](https://docs.gumloop.com/nodes/custom_node_details) to parse the analytics data
* Extract specific metrics needed for your workflow
* Format data appropriately for your target integration

### Setting Up Analytics Access

1. Configure GA4 credentials in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Ensure you have appropriate access permissions
3. Verify your GA4 property setup

## Important Considerations

1. Parameters populate based on your GA4 setup
2. Must have appropriate GA4 access permissions

## Common Use Cases

* Analytics reporting automation
* Dashboard creation
* Performance monitoring
* Regular stakeholder updates
* Data aggregation for analysis

In summary, the Google Analytics Reader node provides streamlined access to GA4 data, making it ideal for automated reporting and analytics monitoring workflows. Its JSON output format allows for flexible integration with other nodes for data processing and distribution.
