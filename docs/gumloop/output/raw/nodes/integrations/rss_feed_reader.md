> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# RSS Feed Reader

This document outlines the functionality and characteristics of the RSS Feed Reader node, which enables automated content extraction from RSS feeds using Inoreader's API.

## Node Inputs

### Required Field

* **Feed**: URL or name of RSS feed to read from

### Optional Fields

* **Number of Items**: Quantity of recent items to fetch (default: 1)
* **Outputs**: Select specific information types to retrieve:
  * URLs
  * Titles
  * Dates
  * Categories
  * IDs
  * Origin Feeds

## Node Output

All outputs are provided as lists (string\[]):

* **URLs**: Links to feed content
* **Titles**: Content headlines
* **Dates**: Publication dates in ISO format
* **Categories**: Content categorization
* **IDs**: Unique identifiers
* **Origin Feeds**: Source feed URLs

## Node Functionality

The RSS Feed Reader node retrieves and processes content from RSS feeds.

**Key features include**:

* Flexible output selection
* Customizable item count
* ISO formatted dates
* Multiple feed support
* Secure authentication with Inoreader
* Batch processing capabilities

## When To Use

The RSS Feed Reader node is essential when you need to monitor and collect content from RSS feeds. Common use cases include:

* **Content Monitoring**: Track updates from specific sources
* **News Aggregation**: Collect articles from multiple feeds
* **Research Collection**: Gather topic-specific content
* **Website Updates**: Track changes across multiple sites

**Some specific examples**:

* Monitoring competitor blog updates for market analysis
* Aggregating industry news for team newsletters
* Collecting research papers from academic feeds
* Tracking product announcements across multiple sources

## Important Considerations:

1. Requires Authentication with Inoreader - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Feed must be accessible and valid
3. All outputs are provided as lists
4. Dates follow ISO format

In summary, the RSS Feed Reader node streamlines content monitoring from RSS feeds, making it ideal for automated content aggregation and tracking workflows.
