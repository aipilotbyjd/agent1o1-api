> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Job Posting Scraper

The Job Posting Scraper node automates the process of gathering job listings from multiple job boards. It extracts detailed information about job postings including position details, company information, and compensation data.

## Supported Job Boards

* Indeed (Global)
* Naukri (India)
* Reed (UK)
* CVLibrary (UK)

## Node Configuration

### Required Parameters

* **Job Title**: Position you're searching for (e.g., "Software Engineer")
* **Location**: Geographic location for the job search

### Optional Parameters

* **Max # of Jobs**: Limit the number of results (default: 10)
* **Source**: Select specific job board
  * Indeed (global coverage)
  * Naukri (India-focused)
  * Reed (UK-focused)
  * CVLibrary (UK-focused)
* **Country**: Country selection for Indeed searches
* **Company Type**: Filter by company types (Naukri only)
* **Extra Keywords**: Additional search terms to refine results

### Available Outputs

1. Basic Information:
   * Position name
   * Company name
   * Job location
   * Job posting link

2. Compensation:
   * Salary range

3. Timing Information:
   * Job posting time

4. Detailed Content:
   * Job posting description

5. Company Details:
   * Industry
   * Company website
   * HQ address
   * Number of open positions
   * LinkedIn URL
   * Employee count
   * HQ country

## Dynamic Inputs

The node supports configurable inputs through the "Show As Input" feature, allowing parameters to be set dynamically by previous nodes:

* Job title
* Location
* Max # of jobs
* Source
* Extra keywords

This flexibility enables dynamic job searches based on upstream data or user inputs.

## Example Workflows

### 1. Job Market Analysis Pipeline

```text theme={"dark"}
Job Posting Scraper → Extract Data (for skills) → Google Sheets Writer
                   ↳ AI List Sorter (sort by salary) → Slack Message Sender
```

**Purpose**: Track market trends and salary ranges while analyzing required skills.

### 2. Competitor Hiring Monitor

```text theme={"dark"}
Job Posting Scraper → Airtable Writer
                   ↳ Ask AI (analyze hiring patterns) → Email Notification
```

**Purpose**: Monitor competitor hiring activities and receive insights via email.

### 3. Multi-Location Job Search

```text theme={"dark"}
Create List (locations) → Job Posting Scraper (Loop Mode) 
→ Categorizer (by location) → Notion Database Writer
```

**Purpose**: Search jobs across multiple locations and organize findings in Notion.

### 4. Skills Gap Analysis

```text theme={"dark"}
Job Posting Scraper → Combine Text → 
  Ask AI (analyze requirements) → LinkedIn Profile Scraper → 
    Scorer (match skills) → Slack Message Sender
```

**Purpose**: Compare job requirements with LinkedIn profiles to identify skill gaps.

## Important Notes

1. **Loop Mode Support**
   * Can process multiple job titles or locations in batch
   * Ideal for bulk job searches

2. **Rate Limits**
   * Results limited to \~10 jobs per search for reliability
   * Consider using Loop Mode for larger datasets

3. **Regional Optimization**
   * Reed and CVLibrary: Best for UK job searches
   * Naukri: Optimized for Indian job market
   * Indeed: Global coverage but requires precise location formatting

4. **Cost**
   * 30 credits per execution
   * Consider credit usage when implementing Loop Mode

## Best Practices

1. **Search Optimization**
   * Use specific job titles for better results
   * Include extra keywords to refine searches
   * Consider regional job boards for local searches

2. **Data Processing**
   * Use Extract Data node for parsing job descriptions
   * Implement AI Filter for custom filtering logic
   * Consider using Text Formatter for standardizing output

## Common Use Cases

1. **Market Research**
   * Track salary trends
   * Monitor industry hiring patterns
   * Analyze competitor job postings

2. **Automation Scenarios**
   * Automated job alerts via email or Slack
   * Job requirement analysis
   * Candidate skill matching

3. **Data Collection**
   * Building job market databases
   * Tracking company growth through hiring
   * Salary range analysis
