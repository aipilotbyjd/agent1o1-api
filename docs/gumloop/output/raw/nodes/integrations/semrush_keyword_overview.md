> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Semrush Keyword Overview

The `Semrush Keyword Overview` node provides detailed insights into any specific keyword, offering essential metrics that aid in keyword research, SEO analysis, and paid search planning. This node leverages Semrush’s database to give a comprehensive overview of a keyword’s search potential and competition.

## Prerequisites

This node requires your own Semrush API key, available on Semrush subscription plans that include API access. Add it on your [Credentials page](https://www.gumloop.com/personal/connectors) before using the node.

## Node Inputs

The `Semrush Keyword Overview` node requires the following inputs:

1. **Keyword** (required): The target keyword or search term you want to analyze (e.g., `digital marketing`).
2. **Country** (optional): The target country for search data, using the country’s two-letter ISO code (e.g., `US` for the United States, `UK` for the United Kingdom). Defaults to `US` if not specified.
3. **Outputs** (optional): Customize the metrics you want to retrieve based on your specific needs. By default, all available metrics are selected.

## Available Output Data

This node offers multiple data points related to keyword performance and competitiveness. You can customize the outputs based on what is most relevant for your analysis:

* **Search Volume**: The average monthly search volume for the keyword.
* **CPC (Cost Per Click)**: The estimated cost per click for ads targeting this keyword.
* **Competition**: A score from 0.0 to 1.0, indicating the level of competition in paid search.
* **Keyword Difficulty**: A score from 0 to 100, showing how hard it is to rank for this keyword organically.
* **Number of Results**: The total number of search results for the keyword in the selected country.
* **Database**: The specific Semrush database used for the keyword analysis (e.g., `US`, `UK`).
* **Keyword**: The actual keyword being analyzed, returned as a confirmation.

All outputs are returned as individual strings.

## Credit Cost

* This node costs **no Gumloop credits**. Usage is billed by Semrush against your own plan's API units.

## Usage Tips

* **Keyword Research**: Use this node to find high-volume, low-competition keywords to target in SEO and content strategies.
* **PPC Strategy**: Review CPC and competition scores to identify profitable keywords for paid campaigns.
* **Content Planning**: The keyword difficulty score helps prioritize topics that are achievable to rank for based on current SEO resources.

In summary, the `Semrush Keyword Overview` node is a valuable tool for obtaining a high-level understanding of keyword potential and competitiveness. It’s ideal for setting SEO priorities, optimizing paid campaigns, and making informed content strategy decisions.
