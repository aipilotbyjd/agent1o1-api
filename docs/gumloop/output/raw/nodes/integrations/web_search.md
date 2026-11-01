> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Web Search

This document outlines the functionality and characteristics of the Web Search node, which enables automated search queries across Google and Bing search engines.

## Node Inputs

### Required Fields

* **Query**: Your search term or phrase

### Optional Fields

* **Results Count**: Number of results to return (default: 5)
* **Use Advanced Search**: Toggle for enhanced search capabilities
  * **Engine**: Choose between Google, Bing, Images, Hotels, Events, or News
  * **Country**: Select search region
  * **Event Dates**: For Google Events searches (when Engine is set to Events)

## Node Outputs

* **URLs**: List of webpage URLs from search results
* **Snippets**: List of descriptions for each result

## Node Functionality

The Web Search node performs searches using Google's Custom Search API and Bing's Web Search API.

**Key features include**:

* Basic and advanced search options
* Multiple search engine types
* Regional search targeting
* Customizable result count
* Loop Mode support for multiple searches

## Example Workflows

### 1. Competitor Content Analysis

```text theme={"dark"}
Web Search → Website Scraper → Summarizer → Notion Page Writer
Setup:
- Query: "site:competitor.com product reviews"
- Engine: Google
- Results Count: 10
Next Steps: Use Extract Data to identify key product features
```

### 2. Industry News Monitoring

```text theme={"dark"}
Web Search → Website Scraper → Categorizer → Slack Message Sender
Setup:
- Query: "industry keyword news"
- Engine: News
- Country: United States
- Results Count: 15
Next Steps: Use Ask AI to generate daily briefings
```

### 3. Social Media Content Research

```text theme={"dark"}
Web Search → Website Scraper → Extract Data → Airtable Writer
Setup:
- Query: "trending topics technology"
- Engine: Bing
- Results Count: 20
Next Steps: Use AI List Sorter to prioritize content ideas
```

### 4. Event Marketing Pipeline

```text theme={"dark"}
Web Search → Extract Data → Gmail Sender
Setup:
- Query: "tech conferences 2024"
- Engine: Events
- Country: United States
- Event Dates: Next 3 months
Next Steps: Use LinkedIn Post Writer to share event details
```

## Best Practices

### Query Optimization

* Use specific keywords for targeted results
* Include `site:` operator for domain-specific searches
  * Example: {Name} Site:Linkedin.com/in
* Combine with the [Filter](https://docs.gumloop.com/nodes/flow_basics/filter) node for refined results

### Handling Result Count with Advanced Search

When using advanced search, the Results Count parameter will be disabled due to SERP API limitations. To control the number of results:

1. Add a [List Trimmer](https://docs.gumloop.com/nodes/list_operations/list_trimmer) node after your Web Search node
2. Configure the List Trimmer to keep your desired number of items
3. Connect the URLs, Snippets, or Titles output to the List Trimmer input

### Engine Selection

* **Google**: Best for general web searches and comprehensive results
* **Bing**: Alternative perspective and sometimes different results
* **News**: Recent articles and press releases
* **Events**: Upcoming conferences, webinars, meetups
* **Images**: Visual content and media
* **Hotels**: Travel and accommodation information

## Important Considerations

1. Basic search costs 2 credits per run
2. Advanced search costs 5 credits per run

In summary, the Web Search node provides flexible search capabilities across multiple engines, making it a versatile starting point for research, monitoring, and content aggregation workflows. Its integration with other nodes enables powerful automation sequences for various business needs.
