> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# LinkedIn Company Profile Scraper

This document outlines the functionality and characteristics of the LinkedIn Company Profile Scraper node, which enables automated data extraction from LinkedIn company profiles using Proxycurl.

## Node Inputs

### Input Methods (At least one required)

* **Company LinkedIn URL**: Direct URL to company profile (e.g., [https://www.linkedin.com/company/google/](https://www.linkedin.com/company/google/))
* **Company Domain**: Company's website domain (e.g., google.com)
* **Company Name**: Official company name (e.g., Google)

## Node Output

Choose which data points to retrieve:

#### Basic Information

* Company Name
* Description
* Industry
* Website

#### Size Information

* Company Size Range
* LinkedIn Company Size

#### Company Details

* Company Type
* Founded Year

#### Location Information

* Headquarters
* All Locations

#### Additional Details

* Specialities
* Tagline

#### Related Companies

* Similar Companies
* Affiliated Companies

## Node Functionality

The LinkedIn Company Profile Scraper node extracts company information using Proxycurl's API.

### Key Features

* Multiple input methods for flexibility
* Comprehensive data extraction
* Customizable information selection
* No rate limits
* Loop Mode support for batch processing

## When To Use

The LinkedIn Company Profile Scraper node is valuable in scenarios requiring company research and data collection:

### Market Research

* Analyze competitor profiles
* Research potential partners
* Study industry leaders
* Build company databases

### Lead Generation

* Gather company information for sales outreach
* Create targeted prospect lists
* Research potential clients

### Business Intelligence

* Track competitor information
* Monitor industry trends
* Analyze company networks

## Example Use Cases

1. **Competitor Analysis**

```text theme={"dark"}
LinkedIn Company Profile Scraper → Ask AI → Notion Page Writer
```

* Use the scraper to collect data about your competitors
* Ask AI analyzes the data to identify:
  * Unique selling points
  * Market positioning
  * Growth trends
  * Key differentiators
* Automatically create organized Notion pages for each competitor
* Keep your competitive intelligence database current

2. **Lead Enrichment**

```text theme={"dark"}
CSV Reader → LinkedIn Company Profile Scraper → Airtable Writer
```

* Input a CSV containing basic company information (names or domains)
* Scraper enriches each entry with:
  * Verified company details
  * Employee count
  * Office locations
  * Industry classifications
* Automatically update Airtable records with enriched data
* Provide sales teams with accurate, up-to-date lead information

3. **Market Research**

```text theme={"dark"}
LinkedIn Company Profile Scraper → Ask AI → Slack Block Kit Sender
```

* Gather data on multiple companies in your target market
* Ask AI processes the data to generate:
  * Industry trends
  * Market opportunity analysis
  * Competitive landscape overview
  * Growth patterns
* Send formatted reports to specific Slack channels using Block Kit
* Keep stakeholders informed with regular market updates

4. **Company Database Building**

```text theme={"dark"}
LinkedIn Company Profile Scraper → Perplexity Search → Supabase Table Writer
```

* Scrape company profiles in bulk using Loop Mode to extract all the relevant data
* Use Perplexity Search node to analyze news about the company
* Write structured data to Supabase for:
  * Market analytics
  * Lead generation
  * Partnership opportunities
  * Investment research

## Important Considerations

1. **Credits Usage**
   * 25 credits per company scrape
   * Reduced to 1 credit with own Proxycurl API key

2. **Data Availability**
   * Not all information may be available for every company
   * Some fields may return empty strings

3. **API Limits**
   * No rate limiting with Proxycurl integration
   * Suitable for bulk processing

4. **Input Flexibility**
   * Can use URL, domain, or company name
   * Multiple input methods increase success rate

In summary, the LinkedIn Company Profile Scraper node provides powerful company data extraction capabilities, with flexible input options and comprehensive information retrieval. Its integration with Proxycurl ensures reliable data access without rate limits, making it ideal for both individual company research and bulk data collection projects.
