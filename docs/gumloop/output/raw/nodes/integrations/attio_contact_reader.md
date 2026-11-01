> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio Contact Reader

The Attio Contact Reader node enables automated retrieval of contact information from your Attio CRM. This powerful integration allows you to pull detailed contact data for analysis, enrichment, and automation workflows.

## Node Configuration

### Required Fields

* **Outputs**: Select which contact attributes to retrieve. Common examples include:
  * Company
  * Created by
  * Job title
  * Email
  * Name
  * Phone

Additional fields will match your custom Attio CRM configuration.

### Optional Fields

* **Number of Contacts**: Specify how many contacts to fetch (default: all contacts)
  * Contacts are retrieved in order of creation date
  * Leave empty to fetch all available contacts

## Output Format

Each selected field becomes an individual output containing a list of values. For example:

* Company → List of company names
* Job title → List of job titles
* Created by → List of creator names

## Example Use Cases

### 1. Contact Enrichment Pipeline

```plaintext theme={"dark"}
Attio Contact Reader → Enrich Contact Information → Ask AI → Slack Message Sender
```

This workflow:

1. Pulls contact basic information
2. Enriches with additional contact data
3. Uses AI to generate insights
4. Sends updates to Slack

### 2. Sales Outreach Automation

```plaintext theme={"dark"}
Attio Contact Reader → LinkedIn Profile Scraper → Ask AI → Gmail Sender
```

This workflow:

1. Retrieves contact information
2. Scrape LinkedIn data for personalization
3. Sends personalized emails

### 3. Contact Database Sync

```plaintext theme={"dark"}
Attio Contact Reader → Ask AI → Extract Data → Notion Database Writer
```

This workflow:

1. Fetches contact data
2. Uses AI to format and categorize
3. Updates Notion database

## Setup Requirements

* Attio Authentication: Configure via [Connectors page](https://www.gumloop.com/personal/connectors)

## Integration Tips

1. **With AI Nodes**
   * Use Perplexity Search for additional information gathering
   * Generate personalized outreach content with Ask AI
   * Qualify leads automatically with Categorizer node

2. **With Communication Nodes**
   * Automate email campaigns
   * Send SMS notifications
   * Post updates to Slack

3. **With Database Nodes**
   * Sync with other databases like Airtable, Monday.com, etc.
   * Update contact records
   * Track engagement history
