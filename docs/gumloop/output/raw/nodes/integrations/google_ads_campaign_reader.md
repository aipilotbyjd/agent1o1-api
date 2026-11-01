> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Ads Campaign Reader

This document outlines the functionality and characteristics of the Google Ads Campaign Reader node, which enables automated extraction of campaign data from Google Ads accounts.

## Node Overview

The Google Ads Campaign Reader node retrieves comprehensive campaign-level data from your Google Ads accounts, providing you with performance metrics and configuration details that can be used in your automation workflows.

This node is particularly valuable for marketing teams that need to monitor campaign performance, generate reports, or trigger automations based on advertising metrics.

## Node Inputs

### Required Fields

* **Account**: Select which Google Ads account to pull data from
  * Select from a dropdown of connected accounts

### Optional Fields

* **Managed Account**: Select a specific client account to access
  * Available for all Google Ads accounts
  * Allows you to specify which account to pull data from when you have multiple accounts or sub-accounts

* **Show More Options**: Expand to access additional configuration settings
  * **Credentials to use**: Specify which set of Google Ads API credentials to use
    * Useful when managing multiple Google Ads accounts with different API access

## Node Output

The Google Ads Campaign Reader provides rich campaign data through multiple outputs:

| Output           | Type | Description                                                     |
| ---------------- | ---- | --------------------------------------------------------------- |
| Bidding Strategy | List | The automated or manual bidding approach used for each campaign |
| ID               | List | Unique campaign identifiers                                     |
| Clicks           | List | Number of clicks received by each campaign                      |
| Cost             | List | Total spend for each campaign                                   |
| Impressions      | List | Number of times ads were shown                                  |
| Name             | List | Campaign names                                                  |
| Status           | List | Current campaign status (Active, Paused, Removed, etc.)         |
| Type             | List | Campaign type (Search, Display, Video, Shopping, etc.)          |

All outputs are provided as lists, with each item corresponding to a specific campaign in your account.

## Node Functionality

The Google Ads Campaign Reader connects to the Google Ads API to extract real-time campaign data for analysis and automation.

### Key Features

* **Comprehensive Campaign Data**: Access to all essential campaign metrics
* **MCC Support**: Ability to work with Manager accounts and their child accounts
* **Multiple Authentication Options**: Support for various credential configurations
* **Loop Mode**: Process campaigns individually when loop mode is enabled

## Common Use Cases

### 1. Performance Monitoring and Alerts

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Campaign Reader"] --> B["Extract Data<br>(High-spend campaigns)"]
      B --> C["Slack Message<br>Sender"]
  ```
</div>

**Purpose**: Automatically detect campaigns that are spending more than allocated budgets and send alerts.

### 2. AI-Powered Campaign Analysis

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Campaign Reader"] --> B["Ask AI<br>(Analyze performance)"]
      B --> C["Google Docs<br>Writer"]
  ```
</div>

**Purpose**: Generate weekly performance insights and recommendations for optimization.

### 3. Cross-Platform Reporting

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Campaign Reader"] --> C["Google Sheets<br>Writer"]
      B["Facebook Ad<br>Scraper"] --> C
  ```
</div>

**Purpose**: Create consolidated cross-platform advertising reports.

### 4. Campaign Optimization Recommendations

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Campaign Reader"] --> B["Ask AI<br>(Generate optimization ideas)"]
      B --> C["Gmail<br>Sender"]
  ```
</div>

**Purpose**: Use AI to analyze campaign performance and generate actionable recommendations.

## Loop Mode Application

When Loop Mode is enabled, the node processes each ad account individually, allowing for account-specific operations:

```text theme={"dark"}
Google Ads Campaign Reader (Loop Mode) → Ask AI → Gmail Sender
```

This setup could generate individual performance reports for each ad account and email them to respective account managers.

## Important Considerations

* **Authentication**: Requires proper setup in the [Connectors page](https://www.gumloop.com/personal/connectors)

## Example Workflow: Weekly Campaign Performance Report

This workflow automatically generates a weekly performance report for all active campaigns:

<div align="center">
  ```mermaid theme={"dark"}
  flowchart TD
      A["Google Ads<br>Campaign Reader"] --> B["Combine Text<br>(Format campaign data)"]
      B --> C["Join List Items<br>(Combine all campaigns)"]
      C --> D["Ask AI<br>'Generate weekly report'"]
      D --> E["Gmail Sender<br>Weekly Performance Report"]
  ```
</div>

1. **Google Ads Campaign Reader**: Retrieves all campaign data from your account

2. **Combine Text**: Templates each campaign's data with proper formatting (in Loop Mode)

   `Template: Campaign: {Name}\nClicks: {Clicks}\nCost: {Cost}\nImpressions: {Impressions}\nStatus: {Status}\n`

3. **Join List Items**: Merges all formatted campaign data into a single report with newline separators

4. **Ask AI**: Analyzes the consolidated data and generates a comprehensive report with insights and recommendations

5. **Gmail Sender**: Emails the formatted report with actionable insights to stakeholders

## How To Set Up

1. **Connect your Google Ads Account**: First, add your Google Ads credentials in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Add the Node**: Drag the Google Ads Campaign Reader onto your canvas
3. **Select Account**: Choose your Google Ads account from the dropdown
4. **Connect Outputs**: Link the campaign data outputs to subsequent nodes in your workflow

## Getting Help

If you encounter any issues or have questions about using the Google Ads Campaign Reader node, [reach out to us](https://portal.usepylon.com/gumloop/forms/help) for support.
