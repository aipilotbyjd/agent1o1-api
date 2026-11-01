> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio Company Reader

The Attio Company Reader node enables automated retrieval of company information from your Attio CRM. This powerful integration allows you to pull detailed company data for analysis, enrichment, and automation workflows.

## Node Configuration

### Required Fields

* **Outputs**: Select which company attributes to retrieve. Example:
  * Company Name
  * Company Domain
  * Categories
  * Funding Raised
  * Team Information

### Optional Fields

* **Number of Companies**: Specify how many companies to fetch (default: all companies)
  * Companies are retrieved in order of creation date
  * Leave empty to fetch all available companies

## Output Format

Each selected field becomes an individual output containing a list of values. For example:

* Company Names → List of company names
* Domains → List of company domains
* Categories → List of company categories

## Common Use Cases

### 1. Investment Research Automation

```plaintext theme={"dark"}
Attio Company Reader → Perplexity Search → Ask AI → Slack Message Sender
```

This workflow:

1. Pulls company and funding data
2. Uses Perplexity to analyze news related to the company
3. Generates investment summaries
4. Sends daily briefings to Slack

### 2. Competitive Intelligence Pipeline

```plaintext theme={"dark"}
Attio Company Reader → Website Scraper → Ask AI → Airtable Writer
```

This workflow:

1. Retrieves competitor companies
2. Scrapes their websites for updates
3. Analyzes changes with AI
4. Maintains a competitive dashboard

### 3. Lead Enrichment System

```plaintext theme={"dark"}
Attio Company Reader → Enrich Company Information → Notion Database Writer
```

This workflow:

1. Fetches company basic info
2. Enriches with additional data
3. Extracts relevant insights
4. Updates Notion database

## Integration Capabilities

The node works seamlessly with:

* AI analysis nodes
* Data enrichment nodes to gather company information
* Notification nodes to send updates
* Database nodes (eg. Airtable Writer node) to update company data across different platforms

## Setup Requirements

1. Attio Authentication
   * Configure via [Connectors page](https://www.gumloop.com/personal/connectors)

2. Output Configuration
   * Select required fields before running

## Example Workflow: AI-Powered Market Research

```plaintext theme={"dark"}
Attio Company Reader → Ask AI → Generate File → Gmail Sender
```

This workflow automates market research:

1. **Data Collection**
   * Retrieves company information
   * Pulls funding and category data

2. **AI Analysis**
   * Extracts key business metrics
   * Identifies market trends
   * Generates insights

3. **Report Generation**
   * Creates PDF report
   * Formats key findings

4. **Distribution**
   * Emails reports to stakeholders
   * Includes executive summary
   * Attaches detailed analysis
