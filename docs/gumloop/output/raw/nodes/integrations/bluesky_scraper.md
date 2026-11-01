> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Bluesky Scraper

This document outlines the functionality and characteristics of the Bluesky Scraper node, which enables collecting posts from Bluesky, the decentralized social media platform.

## Node Inputs

### Required Fields

* **Scrape Type**: Choose how to collect posts from Bluesky
  * **Custom Feed**: Collect posts from a specific custom feed
  * **Author**: Collect posts from a specific Bluesky user
  * **Search Query**: Collect posts matching search keywords

### Type-Specific Required Inputs

Depending on your selected Scrape Type, one of these fields will be required:

* **Query**: Keywords or phrases to search for (when using Search Query type)
  * Example: "artificial intelligence" or "climate solutions"

* **Author Handle**: Username of a specific Bluesky user (when using Author type)
  * Example: "bsky.app" or "@bsky.app"

* **Custom Feed URI**: URI of a custom Bluesky feed (when using Custom Feed type)
  * Example format: `at://did:plc:xyz/app.bsky.feed.generator/feedname`
  * Note: You can find this URI by viewing the feed details on Bluesky

### Optional Fields

* **Number of Posts**: Limit the quantity of posts to retrieve
  * Higher numbers may increase processing time
  * Leave blank to use default value (25)

* **Date Filtering Options**:
  * **Use Dates?**: Toggle to enable/disable date filtering
  * **Date Range**: Quick selection for common time periods
    * Options include "Last 24 hours", "Last week", "Last month", etc.
  * **Use Exact Dates?**: Toggle for precise timestamp filtering
    * **Start Date (UTC)**: Beginning of custom date range
    * **End Date (UTC)**: End of custom date range

### Show As Input

You can configure certain parameters as dynamic inputs in the "Configure Inputs" section:

* **scrape\_type**: String
  * Accepted values: "Custom Feed", "Author", "Search Query"
  * Dynamically choose scraping method from previous nodes

* **query**: String
  * Search term when using Search Query type
  * Example: "climate change innovation"

* **author**: String
  * Author handle when using Author type
  * Example: "bsky.app"

* **feed\_uri**: String
  * Custom feed URI when using Custom Feed type
  * Example: "at://did:plc:abc123/app.bsky.feed.generator/feedname"

* **num\_posts**: Number
  * Maximum number of posts to retrieve
  * Example: 50

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

## Node Output

The Bluesky Scraper node produces the following outputs:

* **Post Text**: Content of each post (as List of text)
* **Post URLs**: Direct links to each post (as List of text)
* **Author Handles**: Usernames of post authors (as List of text)
* **Like Counts**: Number of likes per post (as List of text)
* **Repost Counts**: Number of reposts per post (as List of text)

## Node Functionality

The Bluesky Scraper node collects posts from the Bluesky platform using various criteria, enabling social media monitoring, content analysis, and trend tracking.

### Key Features

* Flexible data collection from multiple sources (feeds, authors, or search queries)
* Date filtering for targeted time periods
* Multiple output options for comprehensive data analysis
* Loop Mode support for processing collected posts

## When To Use

The Bluesky Scraper node is particularly valuable in scenarios requiring social media data collection and analysis. Common use cases include:

* **Social Listening**: Monitor conversations about your brand or industry
* **Competitive Analysis**: Track competitor activity and engagement
* **Content Research**: Gather posts on specific topics for research
* **Trend Analysis**: Identify emerging trends and discussions
* **Author Tracking**: Monitor specific users' posts and engagement
* **Feed Monitoring**: Follow custom feeds for relevant content

## Example Workflows

### 1. Brand Monitoring

```text theme={"dark"}
Bluesky Scraper → Categorizer → Slack Message Sender
```

Setup:

* Scrape Type: Search Query
* Query: "your company name"
* Use Dates?: Yes
* Date Range: Last 24 hours
* Configure categorizer to classify sentiment
  Purpose: Monitor brand mentions and send daily reports via Slack

### 2. Competitive Intelligence

```text theme={"dark"}
Bluesky Scraper → Ask AI → Google Sheets Writer
```

Setup:

* Scrape Type: Author
* Author Handle: "competitor\_handle"
* Number of Posts: 100
  Purpose: Analyze competitor posts and summarize their messaging strategy

### 3. Topic Research

```text theme={"dark"}
Bluesky Scraper → Extract Data → CSV Writer
```

Setup:

* Scrape Type: Search Query
* Query: "industry keyword"
* Use Dates?: Yes
* Date Range: Last month
  Purpose: Gather posts about specific industry topics for research

### 4. Multi-Author Analysis

```text theme={"dark"}
Google Sheets Reader → Bluesky Scraper (Loop Mode) → Ask AI → Airtable Writer
```

Setup:

* Google Sheet with list of author handles
* Scrape Type: Author (connected to sheet output)
* Loop Mode enabled
  Purpose: Analyze content patterns across multiple authors

### 5. Custom Feed Dashboard

```text theme={"dark"}
Bluesky Scraper → Summarizer → Notion Page Writer
```

Setup:

* Scrape Type: Custom Feed
* Custom Feed URI: Your selected feed URI
* Number of Posts: 50
  Purpose: Create a regular digest of important content from custom feeds

## Loop Mode Pattern

In Loop Mode, the Bluesky Scraper node can process multiple queries, authors, or feeds in sequence:

```text theme={"dark"}
Input: [query1, query2, query3]
Process: Scrape posts for each query
Output: Lists of posts for each query
```

This pattern is particularly useful for:

* Monitoring multiple keywords
* Tracking several competitors
* Processing different custom feeds

## Important Considerations

* **Authentication**: The node requires [authentication with Bluesky](https://www.gumloop.com/personal/connectors).

In summary, the Bluesky Scraper node provides powerful capabilities for collecting and analyzing content from the Bluesky platform, with flexible configuration options to suit a wide range of social media monitoring and analysis needs.
