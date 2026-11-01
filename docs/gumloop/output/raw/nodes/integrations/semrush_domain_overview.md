> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Semrush Domain Overview

The `Semrush Domain Overview` node provides a comprehensive snapshot of SEO, advertising, and traffic metrics for any specified domain using data from Semrush. This node is especially useful for competitive analysis, SEO research, and understanding the online presence of competitors or potential partners.

## Prerequisites

This node requires your own Semrush API key, available on Semrush subscription plans that include API access. Add it on your [Credentials page](https://www.gumloop.com/personal/connectors) before using the node.

## Node Inputs

The `Semrush Domain Overview` node requires the following key inputs:

1. **Domain** (required): The target domain or website for analysis (e.g., `example.com`). This input must be specified to generate data.
2. **Country** (optional): Specify the target country for regional data, such as `US` for the United States, `UK` for the United Kingdom, etc. If left blank, the data defaults to the United States.
3. **Outputs (optional)**: Allows you to specify which metrics to retrieve. By default, the node will fetch all available metrics.

Note: Use 'Configure Inputs' option to make certain fields dynamic for Loop Mode operations.

## Available Outputs

The node provides various metrics grouped into different categories:

* **Rank**: The overall global ranking of the domain based on traffic and visibility.
* **Organic Metrics**:
  * **Keywords**: Number of keywords the domain ranks for in organic search.
  * **Traffic**: Estimated monthly traffic driven by organic search.
  * **Cost**: The estimated cost of traffic if it were obtained through paid search.
* **Adwords Metrics**:
  * **Keywords**: Number of keywords the domain ranks for in paid search.
  * **Traffic**: Estimated monthly traffic from paid search.
  * **Cost**: The estimated cost of paid traffic.
* **PLA (Product Listing Ads)**:
  * **Keywords**: Number of keywords targeted in Product Listing Ads.
  * **Unique Visitors**: Estimated unique visitors from PLA campaigns.

All outputs are returned in `string` format, allowing for easy integration with other workflow steps.

## Credit Cost

* This node costs **no Gumloop credits**. Usage is billed by Semrush against your own plan's API units.

## Usage Tips

* **Competitive Analysis**: Use this node to gather insights on competitors' organic and paid search strategies, top keywords, and overall online performance.
* **SEO Research**: Obtain organic search data for a specific domain to understand its ranking strength and keyword opportunities.
* **Batch Processing**: This node can be used in batch mode to analyze multiple domains in one go, making it ideal for broader market research.

In summary, the `Semrush Domain Overview` node is a powerful tool for gathering valuable insights into a domain’s search visibility, SEO performance, and advertising reach. It offers essential data to support digital marketing strategies, competitive analysis, and business development.
