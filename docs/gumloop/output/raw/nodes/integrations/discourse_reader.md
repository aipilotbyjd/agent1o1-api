> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Discourse Reader

The Discourse Reader node enables you to fetch and analyze topics from your Discourse forum, making it ideal for community monitoring, content analysis, and automated responses.

## Node Inputs

### Required Fields

* **Number of Topics**: Specify how many topics to fetch from your forum

### Optional Fields

* **Use Dates?**: Enable date-based filtering
  * **Date Range**: Choose from predefined ranges (last 7 days, last month, etc.)
  * **Use Exact Dates?**: Toggle for custom date range
    * **Start Date (UTC)**: Beginning of date range
    * **End Date (UTC)**: End of date range

## Node Outputs

* **Topic Titles**: List of topic titles from the forum
* **Topic URLs**: List of links to each topic
* **Topic Replies**: List of replies for each topic

## Node Setup

* Add your Discourse API key username and forum URL on the [Connectors page](https://www.gumloop.com/personal/connectors)

## Example Workflows

### 1. Community Sentiment Analysis

```text theme={"dark"}
Discourse Reader → Categorizer → Slack Message Sender
Setup:
- Fetch last 7 days of topics
- Use the AI Categorizer node to analyze sentiment
- Send weekly sentiment analysis summary to Slack
Benefits: Track community mood and trending issues
```

### 2. Auto-Response System

```text theme={"dark"}
Discourse Reader → Extract Data → AI Filter → Airtable Writer
Setup:
- Monitor new topics continuously (setup a time based trigger)
- Extract key information (category, urgency)
- Filter for support requests
- Log in Airtable for team tracking
Benefits: Quick response to community needs
```

### 3. Content Curation

```text theme={"dark"}
Discourse Reader → Summarizer → Extract Data → CSV Writer
Setup:
- Fetch top discussions
- Generate concise summaries
- Extract key points and insights
- Export to structured format
Benefits: Create digestible content roundups
```

### 4. Knowledge Base Enhancement

```text theme={"dark"}
Discourse Reader → AI List Sorter → Notion Page Writer
Setup:
- Gather frequently discussed topics
- Sort by relevance and engagement
- Create organized knowledge base
Benefits: Turn community discussions into documentation
```

## Best Practices

1. **Date Filtering**
   * Use date ranges for focused monitoring
   * Consider timezone differences when setting exact dates
   * Use the `Datetime` node to pass the date dynamically if exact dates is enabled

2. **Topic Management**
   * Start with smaller topic numbers for testing

3. **Integration Tips**
   * Combine with AI nodes for automated analysis
   * Use notification nodes for important updates

The Discourse Reader node streamlines community management by providing automated access to forum discussions and enabling sophisticated analysis workflows.
