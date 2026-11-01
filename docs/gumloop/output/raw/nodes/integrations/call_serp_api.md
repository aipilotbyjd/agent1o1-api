> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Call Serp API

This document outlines the functionality and characteristics of the Call Serp API node, which enables advanced search engine queries with comprehensive result types.

## Node Inputs

### Required Fields

* **Query**: Your search term or phrase
  * Example: "best CRM software for small business"
* **Engine**: Choose search engine type:
  * google (default)
  * google\_images
  * google\_hotels
  * google\_events
  * google\_news
  * bing

### Optional Fields

* **Results Count**: Number of results to return (default: 10)

### Configure Inputs

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **query**: String
  * The search term you want to query
  * Example: "digital marketing strategies"

* **engine**: String
  * The search engine to use
  * Accepted values: "google", "google\_images", "google\_hotels", "google\_events", "google\_news", "bing"

* **results\_count**: Number
  * Number of results to fetch
  * Example: 15

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow.

## Node Output

The node produces structured outputs based on the search information types you select:

* **organic\_results**: A list of regular search results
  * Each result includes title, link, snippet, position, etc.
* **related\_questions**: Common questions related to your search term
* **knowledge\_graph**: Detailed information about entities (people, places, organizations)
* **images\_results**: Image search results with thumbnails and source URLs
* **news\_results**: Recent news articles related to your search
* **shopping\_results**: Product listings with prices and sellers
* **local\_results**: Location-based results with addresses and ratings
* **jobs\_results**: Job listings related to your search
* **twitter\_results**: Relevant Twitter content
* **ai\_overview**: AI-generated summary of the search results that provides a concise overview of the topic

## Node Functionality

The Call Serp API node performs advanced search engine queries using direct Serp API access.

**Key features include**:

* Multiple search engine options (Google and Bing)
* Comprehensive result type selection
* Direct API integration with minimal rate limiting
* Specialized search capabilities (images, news, hotels, events)
* Loop Mode support for batch searches

## How it Works

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A[Query + Engine Selection] --> B[Call Serp API]
      B --> C[Retrieve Selected Result Types]
      C --> D[Structured Outputs]
      D --> E[Downstream Processing]
  ```
</div>

1. The node sends your query to the selected search engine
2. It retrieves only the data types you've selected
3. Results are formatted into structured outputs
4. Each output type can be connected to different downstream nodes

## When To Use

The Call Serp API node is particularly valuable for advanced search needs that require structured data. Common use cases include:

* **Comprehensive Research**: Gather multiple types of data in one search
* **Specialized Searches**: Access specific data like news or images
* **Data Analysis**: Collect structured search data for processing
* **Content Aggregation**: Gather various content types for content creation
* **Competitive Analysis**: Research competitors across multiple dimensions

### Compared to Web Search Node

| Feature         | Call Serp API                               | Web Search                             |
| --------------- | ------------------------------------------- | -------------------------------------- |
| Credit Cost     | 5 credits                                   | 2 credits                              |
| Result Types    | Multiple specialized formats                | Single text format                     |
| Structured Data | Yes (JSON-like)                             | No (text only)                         |
| Use Case        | Advanced research requiring structured data | Simple searches requiring text results |
| Loop Mode       | Supported                                   | Supported                              |

## Example Use Cases

### Market Research Workflow

```text theme={"dark"}
Input (Company name) → Call Serp API → Extract Data → Google Sheets Writer
Configuration:
- Search Information: organic_results, knowledge_graph, news_results
- Results Count: 20
Output: Structured company data for analysis
```

### Content Creation Assistant

```text theme={"dark"}
Input (Topic) → Call Serp API → Ask AI → Google Docs Writer
Configuration:
- Search Information: organic_results, related_questions, ai_overview
- Results Count: 15
Output: Comprehensive content brief with topic insights
```

### Product Comparison

```text theme={"dark"}
Input (Product name) → Call Serp API → Ask AI → Airtable Writer
Configuration:
- Engine: google
- Search Information: shopping_results, organic_results
- Results Count: 25
Output: Structured product data with pricing information
```

## Important Considerations

* Each Call Serp API execution costs **5 credits**
* Results vary based on region and time - consider using location parameters for consistent results
* Some search information types may not be available for all queries
* The API may return different result formats depending on the search engine selected

## Best Practice

* **Use with AI Nodes**: Combine with Ask AI or Extract Data to process results meaningfully

In summary, the Call Serp API node provides comprehensive search capabilities with multiple data types across both Google and Bing search engines, making it ideal for advanced search needs requiring detailed or specialized information.
