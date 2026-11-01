> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio Company Writer

The Attio Company Writer node enables automated creation of company records in your Attio CRM. This powerful integration allows you to create and maintain company data through automated workflows.

## Node Configuration

### Input Fields

Input fields correspond to your Attio CRM field configuration. Common examples include:

* Company Name
* Company Domain
* Description
* Last interaction
* Categories
* Funding raised

Additional fields will match your custom Attio CRM configuration.

## Common Use Cases

### 1. Lead Generation Pipeline

```plaintext theme={"dark"}
LinkedIn Company Profile Scraper → Ask AI → Attio Company Writer
```

This workflow:

1. Scrapes company profiles from LinkedIn
2. Uses AI to extract and structure company data
3. Creates new company records in Attio

### 2. Company Data Enrichment

```plaintext theme={"dark"}
Airtable Reader → Enrich Company Information → Ask AI → Attio Company Writer
```

This workflow:

1. Reads existing company records from Airtable or any other database
2. Enriches with additional company data
3. Uses AI to format and validate information
4. Writes companies with enriched data on Attio

### 3. Website Analysis Pipeline

```plaintext theme={"dark"}
Website Scraper → Extract Data → Attio Company Writer
```

This workflow:

1. Scrapes company websites
2. Uses AI to analyze and structure data
3. Creates company records

## Integration Capabilities

The node works seamlessly with:

* Data enrichment nodes to gather additional company information
* AI nodes for data processing and validation
* Web scraping nodes to gather company data
* Database nodes to sync company information

## Setup Requirements

* Attio Authentication: Configure via [Connectors page](https://www.gumloop.com/personal/connectors)

## Example Workflow: Automated Lead Processing

```plaintext theme={"dark"}
Web Search → Website Scraper → Extract Data (AI) → Attio Company Writer
```

This workflow automates lead processing:

1. **Data Discovery**
   * Searches for potential leads
   * Identifies company websites

2. **Information Gathering**
   * Scrapes company websites
   * Collects contact information

3. **AI Extraction**
   * Validates company data
   * Formats information
   * Generates company descriptions

4. **Record Creation**
   * Creates new company records
   * Sets all available fields

## Integration Tips

1. **With AI Nodes**
   * Use AI for data validation
   * Generate descriptions automatically
   * Classify companies into categories

2. **With Enrichment Nodes**
   * Add company size and revenue data
   * Include social media profiles
   * Update funding information

3. **With Data Reader Nodes (eg. Airtable or Notion Database Reader)**
   * Set up regular data updates
   * Create notification workflows
   * Track data changes
