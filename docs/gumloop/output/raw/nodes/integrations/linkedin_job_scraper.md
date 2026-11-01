> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# LinkedIn Job Scraper

This document outlines the functionality and characteristics of the LinkedIn Job Scraper node, which enables automated job listing extraction from LinkedIn company profiles using Proxycurl.

## Node Inputs

### Company Identification (At least one required)

* **Company Domain**: Company's website domain (e.g., google.com)
* **Company Name**: Official company name (e.g., Google)

### Search Parameters (Optional)

* **Keyword**: Search term for job titles or descriptions (e.g., "software engineer")

* **Job Type**: Filter by employment type:
  * Full-time
  * Part-time
  * Contract
  * Internship
  * Temporary
  * Volunteer
  * Anything (default)

* **Experience Level**: Filter by seniority:
  * Internship
  * Entry\_level
  * Associate
  * Mid\_senior\_level
  * Director
  * Anything (default)

* **Time Posted**: Filter by posting date:
  * Yesterday
  * Past-week
  * Past-month
  * Anytime (default)

* **Workplace Type**: Filter by work location:
  * Remote
  * On-site
  * Hybrid
  * Anything (default)

* **Maximum Number of Jobs**: Limit number of results (default: 10)

> Note: Use the 'Configure Inputs' option to expose these fields as inputs to the node. This is particularly useful for Loop Mode operations.

## Node Output

Each selected information field becomes an output containing a list (array) of values:

* **Total Company Listings**: Total number of active job postings for the company

> Note: This is different from the "Maximum Number of Jobs" parameter, which limits how many jobs you want to retrieve. For instance, even if a company has 500 total listings, you might only want to fetch the 10 most recent ones.

* **Posted Date**: When each job was posted
* **Location**: Job locations
* **Company URL**: LinkedIn company profile URLs
* **Company Name**: Company names
* **Job URL**: Direct links to job listings
* **Job Title**: Position titles

## Node Functionality

The LinkedIn Job Scraper node extracts job listings using Proxycurl's API.

### Key Features

* Multiple company identification methods
* Comprehensive search filters
* Customizable information selection
* No rate limits
* Loop Mode support for multiple companies

## Example Use Cases

1. **Tech Talent Market Analysis**

```text theme={"dark"}
LinkedIn Job Scraper → Website Scraper → Ask AI → Slack Block Kit Sender
```

* Use Job Scraper to collect job postings from top tech companies with "software engineer" keyword
* Website scraper node scrapes the job listing URL
* Ask AI analyzes the scraped data to identify:
  * Most in-demand programming languages
  * Common experience requirements
  * Emerging technical skills
* Slack Message Sender delivers weekly trend reports in formatted blocks

2. **Competitive Hiring Intelligence**

```text theme={"dark"}
LinkedIn Job Scraper → Categorizer → Notion Page Writer
```

* Job Scraper monitors competitors' job posts with director-level filter enabled
* Categorizer automatically classifies jobs into departments:
  * Engineering & Tech
  * Sales & Marketing
  * Product & Design
  * Operations & Finance
* Notion pages are organized by department with automated updates about:
  * New leadership positions
  * Team expansion areas
  * Required qualifications

3. **Job Market Dashboard**

```text theme={"dark"}
LinkedIn Job Scraper → Website Scraper → Ask AI → Google Sheets Writer
```

* Job Scraper retrieves latest job posts from target companies with experience\_level filter
* Website scraper node scrapes the job listing URL
* Ask AI processes job descriptions to extract:
  * Salary ranges when mentioned
  * Required qualifications
  * Benefits packages
* Google Sheets Writer updates a live dashboard with:
  * Job count by level
  * Compensation trends
  * Skills in demand

## Important Considerations

1. **Credits Usage**
   * 27 credits per scrape
   * Reduced to 3 credits with own Proxycurl API key

2. **Data Availability**
   * Results depend on public job listings
   * Some fields may be empty if information isn't available

3. **API Limits**
   * No rate limiting with Proxycurl integration
   * Suitable for bulk processing

4. **Search Optimization**
   * More specific searches yield better results
   * Combine multiple filters for precision
   * Use company domain for accurate targeting

In summary, the LinkedIn Job Scraper node provides powerful job listing extraction capabilities with flexible search options and comprehensive information retrieval. Its integration with Proxycurl ensures reliable data access without rate limits, making it ideal for both individual job searches and market analysis projects.
