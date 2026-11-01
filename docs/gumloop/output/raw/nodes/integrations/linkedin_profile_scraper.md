> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# LinkedIn Profile Scraper

This document outlines the functionality and characteristics of the LinkedIn Profile Scraper node, which enables automated data extraction from LinkedIn profiles using Proxycurl.

## Node Inputs

### Required Field

* **LinkedIn URL**: Profile URL to scrape (format: [https://www.linkedin.com/in/username/](https://www.linkedin.com/in/username/))

### Optional Field

* **Scraped Information**: Select which data points to retrieve:

#### Personal Information

* First Name
* Last Name
* Headline
* About
* Profile Picture URL

#### Location Details

* Country
* Country Code
* City
* State

#### Professional Information

* Job Title
* Work Experiences
* Education
* Certifications

#### Additional Details

* Volunteer Work
* Number of Connections
* Recommendations

## Node Output

Each selected information field becomes an individual output containing the scraped data.

## Node Functionality

The LinkedIn Profile Scraper node extracts profile information using Proxycurl's API.

**Key features include**:

* Comprehensive data extraction
* Customizable information selection
* No rate limits
* Loop Mode support

## When To Use

The LinkedIn Profile Scraper node is essential when you need to extract professional information from LinkedIn profiles. Common use cases include:

* **Candidate Research**: Gather detailed information about potential candidates
* **Lead Generation**: Extract professional details for sales outreach
* **Market Analysis**: Research competitors or industry professionals
* **Network Building**: Analyze potential connections or partners

## Example Use Cases

1. **Talent Acquisition**

```text theme={"dark"}
CSV Reader → LinkedIn Profile Scraper → Ask AI → Notion Page Writer
```

* Input CSV with list of candidate LinkedIn URLs
* Scraper collects detailed professional information
* Ask AI analyzes profiles to:
  * Evaluate skill matches
  * Identify experience relevance
  * Assess cultural fit indicators
* Create organized candidate profiles in Notion
* Streamline recruitment workflow

2. **Sales Lead Qualification**

```text theme={"dark"}
LinkedIn Profile Scraper → Perplexity Search → HubSpot Contact Updater
```

* Scrape profiles of potential leads
* Perplexity Search node researches and extracts relevant articles/links related to the lead
* Automatically update or create HubSpot contacts
* Enable data-driven sales outreach

3. **Network Building**

```text theme={"dark"}
LinkedIn Profile Scraper → Categorizer → Airtable Writer
```

* Collect data from industry professionals
* Categorizer classifies experts based on:
  * Domain expertise
  * Years of experience
  * Industry focus
  * Geographic location
* Build structured database in Airtable

4. **Leadership Analysis**

```text theme={"dark"}
LinkedIn Profile Scraper → Ask AI → Slack Block Kit Sender
```

* Gather executive team profiles
* Ask AI generates insights about:
  * Leadership experience
  * Industry backgrounds
  * Common career patterns
  * Team composition
* Send formatted analysis to Slack
* Keep stakeholders informed about key personnel

## Important Considerations:

1. Costs 25 credits per profile scrape
2. Cost reduces to 1 credit if using your own Proxycurl API key
3. Profile must be publicly accessible
4. Not all selected fields may be available (in that case, the output would be an empty string)
5. No rate limiting with Proxycurl integration

In summary, the LinkedIn Profile Scraper node provides reliable profile data extraction using Proxycurl, with flexible data selection and no rate limits.
