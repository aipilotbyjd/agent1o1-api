> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Semrush URL Keywords

The `Semrush URL Keywords` node retrieves organic keyword data for any specific URL, offering insight into the keywords a webpage ranks for in search engine results. This node is useful for SEO analysis, allowing you to understand keyword performance and discover optimization opportunities.

## Prerequisites

This node requires your own Semrush API key, available on Semrush subscription plans that include API access. Add it on your [Credentials page](https://www.gumloop.com/personal/connectors) before using the node.

## Node Inputs

The `Semrush URL Keywords` node requires the following parameters:

1. **URL** (required): The target webpage URL you want to analyze (e.g., `https://example.com/blog-post`).
2. **# of Keywords** (optional): The number of top-ranking keywords to retrieve for the URL. Defaults to `10` if not specified.
3. **Country** (optional): The country code for the region you want data from (e.g., `US` for United States). Defaults to `US` if not specified.
4. **Outputs** (optional): Customize the metrics you want to retrieve, based on your analysis needs. Multiple options are available to select from, allowing you to focus on specific data points.

## Available Output Data

This node provides multiple metrics related to the URL’s keyword rankings. Each output is returned in list format (`string[]`), allowing for easy processing and further analysis.

* **Keywords**: The specific keywords for which the webpage ranks.
* **Position**: The current ranking position of the webpage in search engine results for each keyword.
* **Search Volume**: The monthly search volume of the keyword.
* **CPC (Cost Per Click)**: The estimated cost per click for paid ads targeting the keyword.
* **Competition**: The level of competition in paid search for the keyword, on a scale from 0.0 to 1.0.
* **Traffic %**: The percentage of the webpage's traffic driven by the keyword.
* **Traffic Cost**: The estimated paid traffic cost that the keyword contributes.
* **Num of Results**: The total number of search results for the keyword, indicating the level of competition in organic search.
* **Trends**: Data showing how the search volume for the keyword has changed over time.

## Credit Cost

* This node costs **no Gumloop credits**. Usage is billed by Semrush against your own plan's API units.

## Usage Tips

* **Competitor Analysis**: Analyze URLs from competitor websites to understand their keyword strategies and identify high-traffic keywords.
* **Content Optimization**: Use the keyword list to refine your own webpage’s SEO strategy, focusing on keywords with good search volume and low competition.
* **PPC Planning**: Review CPC and competition scores to find keywords suitable for paid campaigns.

The `Semrush URL Keywords` node is an effective tool for evaluating how a specific webpage performs in organic search. It’s ideal for competitive analysis, content refinement, and understanding the impact of individual keywords on traffic.
