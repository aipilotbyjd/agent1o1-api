> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Enrich Company Information

## Overview

The **Enrich Company Information** node allows you to gather comprehensive details about a company using just its domain name. This node connects with multiple data providers to retrieve relevant company insights, making it an invaluable tool for market research, business development, and competitive analysis.

## Node Inputs

### Required Fields

* **Domain Name**: The website domain of the company you want to gather information about (e.g., "google.com", "microsoft.com")

### Optional Fields

* **Data Providers**: Choose which data sources to use:
  * **Apollo**: Comprehensive B2B data (costs 60 credits)
  * **Proxycurl**: Rich company data with LinkedIn insights (costs 60 credits)
  * **SimilarWeb**: Website traffic and analytics data (costs 1 credit)

* **Company Information to Extract**: Select the specific data points you want to gather about the company:
  * **Company Name**: Official name of the company
  * **Description**: Brief overview of what the company does
  * **Industry**: Business sector or category
  * **Company Size**: Number of employees (estimated or reported)
  * **Founded Year**: When the company was established
  * **Location**: Primary headquarters location
  * **Country**: Country where the company is based
  * **LinkedIn URL**: Link to company's LinkedIn profile
  * **Phone Number**: Main contact number
  * **Annual Revenue**: Estimated yearly earnings
  * **Monthly Web Traffic**: Estimated monthly website visitors
  * **Website Ranking**: Position in global website rankings
  * And more...

## Node Output

The node outputs each selected data point as a separate field. For example:

* **Company Name**: "Google LLC"
* **Industry**: "Technology, Information and Internet"
* **Annual Revenue**: "\$280.5B"
* **Monthly Web Traffic**: "89.3B"

> **Note**: Output availability depends on which data providers you select and whether they have information on the requested company.

## Node Functionality

The Enrich Company Information node queries multiple data providers to build a comprehensive profile of a company based on its domain name. It's particularly useful for:

* **Automating research**: Get company details without manual searching
* **Database enrichment**: Add information to existing company records
* **Lead qualification**: Assess potential clients based on company attributes
* **Competitive analysis**: Gather intelligence on competitors

## Credit Costs Explained

Understanding how credits work with this node is important for cost optimization:

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart TD
      A["Enrich Company\nInformation Node"] --> B{"Which providers\nare selected?"}
      B -->|"SimilarWeb only"| C["1 credit\n(regardless of result)"]
      B -->|"Apollo/Proxycurl\n(with or without SimilarWeb)"| D{"Data found in\nSimilarWeb?"}
      D -->|"Yes"| E["1 credit"]
      D -->|"No"| F["60 credits\n(Apollo/Proxycurl APIs used)"]
      
      style F fill:#FFE6E6
      style E fill:#E6FFE6
      style C fill:#E6FFE6
  ```
</div>

### Credit Cost Breakdown:

* **60 credits**: When Apollo or Proxycurl providers are used AND data is not found in SimilarWeb
* **1 credit**: When:
  * Only SimilarWeb is selected as a provider (regardless of whether data is found)
  * Data is successfully found in SimilarWeb
  * You provide your own API keys for the data providers

### Cost Optimization Strategy:

If you're primarily interested in certain data points like Monthly Web Traffic:

1. First select only SimilarWeb as your provider (1 credit)
2. If the data is not found and you need to try other providers, run a second iteration with Apollo/Proxycurl

## When To Use

This node is particularly valuable when you need to:

1. **Enrich CRM Data**: Add company details to lead or account records
   ```text theme={"dark"}
   Google Sheets Reader (domains) → Enrich Company Info (Loop Mode) → Ask AI → Google Sheet Updater
   ```

2. **Qualify Sales Leads**: Assess potential customers based on company size, revenue, industry
   ```text theme={"dark"}
   Airtable Reader (prospects) → Enrich Company Info (Loop Mode) → Filter (company size > 100) → Gmail Sender
   ```

3. **Analyze Competitors**: Gather information about companies in your space
   ```text theme={"dark"}
   CSV Reader (competitor domains) → Enrich Company Info (Loop Mode) → Google Sheets Writer
   ```

4. **Build Target Lists**: Identify companies that match specific criteria
   ```text theme={"dark"}
   Website Scraper → Extract Data (domains) → Enrich Company Info → Filter (industry = "Finance") → Airtable Writer
   ```

## Example Implementation

### Create an Enriched Company Database

```text theme={"dark"}
1. Start with a Google Sheet of company domains
2. Read domains with Google Sheets Reader
3. Use Enrich Company Info (Loop Mode) to gather details
   - Select only relevant data points to control costs
   - Consider using only SimilarWeb for initial scan
4. Write enriched data back to a different sheet
```

This workflow provides a cost-effective way to build a detailed company database without manual research.

## Important Considerations

1. **Data Accuracy**:
   * Information is only as accurate as the data providers' sources
   * Data might be estimated or outdated for some companies
   * Smaller or private companies may have limited information available

2. **Credit Optimization**:
   * Start with SimilarWeb provider only (1 credit) if cost is a concern
   * Use Apollo/Proxycurl only when SimilarWeb doesn't provide the data you need
   * Provide your own API keys (available on Pro+ plans) to reduce credit costs

3. **Large Runs**:
   * Use Error Shield when running in Loop Mode to handle potential failures

## Related Nodes

* **[Enrich Contact Information](https://docs.gumloop.com/nodes/data_enrichment/enrich_contact_information)**: Get details about specific individuals
* **[Search Companies](https://docs.gumloop.com/nodes/data_enrichment/search_companies)**: Find companies based on criteria rather than specific domains
* **[Email Validator](https://docs.gumloop.com/nodes/data_enrichment/email_validator)**: Verify email addresses associated with companies
