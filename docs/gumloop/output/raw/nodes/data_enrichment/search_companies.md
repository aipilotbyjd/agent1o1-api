> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Search Companies

## Overview

The **Search Companies** node allows you to retrieve detailed information about companies based on various criteria. This is particularly useful for tasks such as market research, lead generation, recruitment, and competitive analysis. By connecting to the Apollo service, the node gathers and returns relevant company data based on your specified filters.

***

## Node Inputs

1. **Inputs**
   * **Description**: Specifies the criteria for filtering the search. Selecting specific criteria will yield more targeted results. Available options include:
     * **Keywords**: Keywords related to industry or technology.
     * **Company Name**: Name of a specific company.
     * **Industry**: Specific sector (e.g., "Technology", "Healthcare").
     * **Location**: Geographic location (e.g., "California, USA").
     * **Size**: Company size range based on employee count (e.g., "1-50" or "1000-5000").

2. **Company Name** (Optional)
   * **Description**: Enter the name of a specific company to target your search on one entity. Requires "Company Name" to be selected in **Inputs**.

3. **Industry** (Optional)
   * **Description**: Specify an industry to filter results within a particular sector. Requires "Industry" to be selected in **Inputs**.

4. **Location** (Optional)
   * **Description**: Define the geographic location of companies you want to find (e.g., "New York, USA"). Requires "Location" to be selected in **Inputs**.

5. **Size** (Optional)
   * **Description**: Sets a range for company size based on employee numbers (e.g., "1-50" or "500-1000"). This input is conditional on "Size" being included in **Inputs**.

6. **Keywords** (Optional)
   * **Description**: Input specific industry- or technology-related keywords to refine your search results. Requires "Keywords" to be selected in **Inputs**.

7. **# of Results** (Optional)
   * **Description**: Define the number of results to return. Defaults to 10 results, with a maximum of 100.

***

## Node Outputs

The `Search Companies` node provides the following information about companies that match your search criteria:

* **Company Names**: A list of company names that meet the specified criteria.
* **Websites**: URLs of the companies' official websites.
* **Domains**: Primary domains associated with each company.
* **LinkedIn URLs**: LinkedIn profiles for the companies, if available.

***

## Node Functionality

This node connects to Apollo’s database to find and retrieve information about companies based on the chosen filters. It allows you to specify different criteria such as keywords, industry, company name, location, and size to conduct a focused search. The node can return data in multiple formats and is designed to be flexible and useful for various research and analysis needs.

***

## When To Use

1. **Market Research**: To gather insights on companies in a specific industry or region.
2. **Lead Generation**: To build a list of companies in your target market for outreach.
3. **Recruitment and Talent Sourcing**: Identify companies with potential candidates for hiring.
4. **Competitive Analysis**: Understand your competitors by analyzing companies in a similar sector or region.
5. **Partnership Opportunities**: Find potential partners or collaborators based on criteria like industry, location, or company size.

***

## Notes

* **Flexible Criteria**: Customize search criteria using keywords, location, industry, etc., for precise targeting.
* **Batch Processing**: Capable of handling multiple search parameters in one operation using loop mode.
* **Credit Cost**: Base cost per search is 30 credits per run.

***

## Usage Tip

* **More Specific Criteria for Refined Results**: Use multiple filters like keywords, location, and industry for a focused search.

The `Search Companies` node is an effective tool for discovering and analyzing company data, suitable for various use cases from research to sales and competitive analysis. By using targeted search inputs, it provides detailed and relevant information to support informed decision-making.
