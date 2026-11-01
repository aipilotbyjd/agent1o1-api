> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Ads Search Term Reader

This document outlines the functionality and characteristics of the Google Ads Search Term Reader node, which enables automated extraction of search term performance data from Google Ads accounts.

## Node Overview

The Google Ads Search Term Reader node retrieves search term data from your Google Ads campaigns, showing which actual search queries users typed that triggered your ads. This provides valuable insights into what potential customers are searching for and how these terms perform.

## Node Inputs

### Required Fields

* **Account**: Select which Google Ads account to pull data from
  * Select from a dropdown of connected accounts

* **Managed Account**: Select a specific account to access
  * Allows you to specify which account to pull data from when you have multiple accounts or sub-accounts

* **Campaign ID Filter**: Filter search terms by specific campaign IDs
  * Example format: `['123456789', '987654321']`
  * Expects a list of campaign ID strings

### Optional Fields

* **Campaign Name Filter**: Filter search terms by campaign names
  * Example: "Brand Campaign"
  * Case-sensitive matching
  * Leave empty to retrieve data from all campaigns

#### Date Range Options

> Date filter is available under "Show More Options" on the node

* **Use Dates?**: Toggle to enable date filtering
  * When enabled, reveals additional date configuration options

* **Date Range**: Select a predefined time period
  * Options include: Last 7 days, Last month, Last quarter, etc.
  * Simplifies common date selections

* **Use Exact Dates?**: Toggle for precise date filtering
  * When enabled, allows selection of specific start and end dates

## Node Output

The Google Ads Search Term Reader provides comprehensive search term data through multiple outputs:

| Output        | Type | Description                                            |
| ------------- | ---- | ------------------------------------------------------ |
| Search Term   | List | The actual queries users typed in search engines       |
| Campaign ID   | List | IDs of the campaigns that the search terms triggered   |
| Campaign Name | List | Names of the campaigns that the search terms triggered |
| Ad Group ID   | List | IDs of the specific ad groups within campaigns         |
| Ad Group Name | List | Names of the specific ad groups within campaigns       |
| Impressions   | List | Number of times ads were shown for each search term    |
| Clicks        | List | Number of clicks received for each search term         |
| Cost          | List | Amount spent on each search term                       |
| Conversions   | List | Number of conversions attributed to each search term   |

All outputs are provided as lists, with each item corresponding to a specific search term's performance metrics.

## Common Use Cases

### 1. Discover New Keywords for Your Campaigns

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Search Term Reader"] --> B["Ask AI<br>(Identify new keyword opportunities)"]
      B --> C["Google Sheets<br>Writer"]
  ```
</div>

**Purpose**: Identify new keywords that are performing well but aren't yet part of your keyword targeting.

### 2. Optimize Negative Keywords

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Search Term Reader"] --> B["Ask AI<br>(Find irrelevant terms)"]
      B --> C["Categorizer<br>(High/Medium/Low relevance)"]
      C --> D["Google Sheets<br>Writer"]
  ```
</div>

**Purpose**: Find search terms that are triggering your ads but aren't relevant to your business, helping you build a negative keyword list.

### 3. Performance Analysis by Search Intent

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Search Term Reader"] --> B["Extract Data<br>(Extract intent from terms)"]
      B --> C["Join List Items"]
      C --> D["Ask AI<br>(Analyze by intent type)"]
      D --> E["Gmail<br>Sender"]
  ```
</div>

**Purpose**: Analyze how different search intents (informational, transactional, navigational) perform in your campaigns.

### 4. Cross-Campaign Search Term Analysis

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Google Ads<br>Search Term Reader"] --> B["Filter by Campaigns<br>(Set different node configurations)"]
      B --> C["Google Sheets<br>Writer"]
  ```
</div>

**Purpose**: Compare how the same search terms perform across different campaigns to optimize budget allocation.

## Loop Mode Application

When Loop Mode is enabled, the node processes each campaign or account individually, allowing for campaign or account specific analysis:

```text theme={"dark"}
Google Ads Search Term Reader (Loop Mode) → Ask AI → Categorizer
```

This setup could analyze each campaign's or account's relevance and intent individually, creating a more granular analysis.

## Important Considerations

* **Authentication**: Requires proper setup in the [Connectors page](https://www.gumloop.com/personal/connectors)

## Example Workflow: Search Term Optimization

This workflow automatically identifies high-performing search terms and potential negative keywords:

<div align="center">
  ```mermaid theme={"dark"}
  flowchart TD
      A["Google Ads<br>Search Term Reader"] --> B["Combine Text<br>(Format search term data)"]
      B --> C["Join List Items<br>(Combine all terms)"]
      C --> D["Ask AI<br>'Analyze search terms'"]
      D --> E["Extract Data<br>(Extract recommendations)"]
      E --> F["Google Sheets<br>Writer"]
  ```
</div>

1. **Google Ads Search Term Reader**: Retrieves search term performance data
2. **Combine Text**: Templates each search term with its metrics (in Loop Mode)
   * Template: `Term: {Search Term}\nClicks: {Clicks}\nConversions: {Conversions}\nCost: {Cost}\n`
3. **Join List Items**: Merges all formatted search term data with newline separators
4. **Ask AI**: Analyzes the data to identify patterns and opportunities
5. **Extract Data**: Pulls out specific recommendations for new keywords and negative keywords
6. **Google Sheets Writer**: Saves the recommendations for implementation

## How To Set Up

1. **Connect your Google Ads Account**: First, add your Google Ads credentials in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Add the Node**: Drag the Google Ads Search Term Reader onto your canvas
3. **Select Account**: Choose your Google Ads account from the dropdown
4. **Configure Filters**: Set campaign filters if needed
5. **Set Date Range**: Configure the time period for data retrieval
6. **Connect Outputs**: Link the search term data outputs to subsequent nodes in your workflow

## Getting Help

If you encounter any issues or have questions about using the Google Ads Search Term Reader node, [reach out to us](https://portal.usepylon.com/gumloop/forms/help) for support.
