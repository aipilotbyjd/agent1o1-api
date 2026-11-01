> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Reddit Scraper

This document outlines the functionality and characteristics of the Reddit Scraper node, which enables automated content extraction from Reddit.

## Node Inputs

### Optional Fields

* **Subreddit**: Target subreddit (default: 'all')
* **Query**: Search term for finding relevant posts
* **Post Limit**: Number of posts to return (default: 10, max: 500)
* **Sort by**: Post sorting method:
  * hot: trending posts
  * new: recent posts
  * top: highest rated posts
  * relevance: most relevant to query

## Node Output

All outputs are provided as lists (string\[]):

* **Post Titles**: Titles of scraped posts
* **Post URLs**: Links to scraped posts
* **Post Contents**: Text content from posts
* **Post Comments**: User comments from the post

### Date Range Filtering

Filter Reddit posts by a specific time period. This option is available under `Show More Options`:

* **Date Range**: Choose from preset ranges for quick filtering:
  * Last 24 Hours
  * Last Week
  * Last Month
  * Last 3 Months
  * Last 6 Months

Date filtering is useful for:

* Trend analysis over specific periods
* Tracking community sentiment changes
* Researching historical discussions on a topic
* Monitoring subreddit growth patterns
* Collecting posts from specific events or timeframes
* Creating periodic reports on subreddit activity

## Node Functionality

The Reddit Scraper node retrieves posts and content from Reddit.

**Key features include**:

* Flexible search options
* Multiple sorting methods
* Customizable post limits
* Subreddit targeting
* Batch processing via Loop Mode

## When To Use

The Reddit Scraper node is essential when you need to monitor or collect content from Reddit. Common use cases include:

* **Trend Analysis**: Track trending topics in specific communities
* **Content Research**: Gather information on specific topics
* **Community Monitoring**: Follow discussions in target subreddits
* **Data Collection**: Aggregate posts for analysis

**Some specific examples**:

* Monitoring investment discussions in r/wallstreetbets
* Collecting programming solutions from r/programming
* Tracking product mentions in relevant subreddits
* Gathering user feedback about specific topics

In summary, the Reddit Scraper node provides comprehensive Reddit content extraction capabilities, making it ideal for monitoring discussions and collecting targeted information from Reddit communities.

## Reddit Scraper vs Reddit MCP

The **Reddit Scraper** node works out of the box without requiring you to configure custom Reddit credentials. It's ideal for read-only operations like fetching posts, comments, and searching subreddits.

If you need more advanced capabilities like creating posts, editing content, or managing comments, use the [Reddit MCP](/nodes/mcp/reddit) node instead. Note that Reddit MCP requires you to bring your own Reddit app credentials due to Reddit's [Responsible Builder Policy](https://support.reddithelp.com/hc/en-us/articles/42728983564564-Responsible-Builder-Policy). See the [Reddit MCP setup instructions](/nodes/mcp/reddit#important-bring-your-own-reddit-app-mcp-only) for details.
