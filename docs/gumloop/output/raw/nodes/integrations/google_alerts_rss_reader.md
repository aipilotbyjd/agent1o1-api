> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Alerts RSS Reader

This document outlines the functionality and characteristics of the Google Alerts RSS Reader node, which enables automated monitoring of Google Alerts through RSS feeds.

## Node Inputs

### Required Field

* **Feed Link**: Google Alerts RSS feed URL
  * Get this from Google Alerts by right-clicking the RSS icon and copying the link

### Optional Fields

* **Timeframe**: Filter alerts by publish date
  * past hour
  * past day
  * past week
  * past month
  * all
* **Outputs**: Select information to retrieve:
  * links
  * titles
  * dates
  * snippets

## Node Output

All outputs are provided as lists (string\[]):

* **Links**: URLs to alert sources
* **Titles**: Article headlines
* **Dates**: Publication dates
* **Snippets**: Content previews

## Node Functionality

The Google Alerts RSS Reader node retrieves alert content from Google Alerts RSS feeds.

**Key features include**:

* Flexible time filtering
* Multiple output options
* Batch alert processing
* Easy feed URL access
* List format outputs
* Customizable data retrieval

## When To Use

The Google Alerts RSS Reader node is essential when you need to monitor specific topics or keywords. Common use cases include:

* **Brand Monitoring**: Track mentions of your company or products
* **Competitor Analysis**: Follow competitor activities and mentions
* **Industry Updates**: Stay informed about sector developments
* **Topic Research**: Gather information about specific subjects

**Some specific examples**:

* Monitoring press coverage of your brand
* Following industry regulation changes
* Tracking specific product mentions
* Gathering news about emerging trends

## Important Considerations:

1. Feed Link must be from Google Alerts
2. All outputs are provided as lists

In summary, the Google Alerts RSS Reader node streamlines monitoring of Google Alerts, making it ideal for automated topic tracking and content gathering workflows.
