> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Deal Reader

This document outlines the functionality and characteristics of the HubSpot Deal Reader node, which enables automated deal data retrieval from HubSpot CRM.

## Node Inputs

### Filter Options

* **Contact Email**: Filter deals by associated contact

* **Filters**: Additional filtering options
  * Company Domain
  * Contact Email
  * Deal Stage

* **Contact Email**: If you’re filtering by contact email, you would input the specific contact’s email here. For example, ‘[contact@email.com](mailto:contact@email.com)’.

* **Company Domain**: This would be the domain of the company you’re associating the deals with. For example, ‘google.com’.

* **Pipeline**: If filtering by Deal Stage, this is the pipeline from which you’re interested in loading deals. For instance, ‘Sales Pipeline’.

* **Deal Stage**: The specific stages of the deals you want to load. For example, ‘Qualified to Buy’.

### Data Fields

* **Outputs**: Select deal properties to retrieve:
  * Deal name
  * Owner
  * Create/Close dates
  * Amount
  * Custom properties

## Node Output

Selected deal properties provided as lists (string\[]).

## Node Functionality

The HubSpot Deal Reader node retrieves deal information based on specified filters.

**Key features include**:

* Multiple filtering options
* Custom property selection
* Secure authentication with Gumloop

## When To Use

The HubSpot Deal Reader node is valuable for sales pipeline analysis. Common use cases include:

* **Pipeline Management**: Track deals across stages
* **Revenue Forecasting**: Analyze deal values and timelines
* **Contact Analysis**: Review deals associated with contacts
* **Company Reporting**: Generate company-specific deal reports

**Some specific examples**:

* Monitoring high-value deals in late stages
* Tracking deals for specific clients
* Analyzing pipeline velocity metrics
* Generating sales team performance reports

## Important Considerations:

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. At least one filter must be selected
3. All outputs are in list format

In summary, the HubSpot Deal Reader node streamlines deal data retrieval from HubSpot CRM, supporting various filtering options for efficient pipeline analysis.
