> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Company Reader

This document outlines the functionality and characteristics of the HubSpot Company Reader node, which enables automated company data retrieval from HubSpot CRM.

## Node Inputs

### Required Field

* **Outputs**: Select company properties to retrieve
  * Names
  * Phone numbers
  * Industry
  * Country
  * Owners
  * And more

### Optional Fields

* **Use List**: Toggle to read from specific HubSpot lists
  * **List**: Select HubSpot list (required if Use List is enabled)

## Node Output

Selected company properties provided as lists (string\[]).

## Node Functionality

The HubSpot Company Reader node retrieves company data from your HubSpot CRM.

**Key features include**:

* Multiple property selection
* Dynamic data retrieval
* Secure authentication with Gumloop

## When To Use

The HubSpot Company Reader node is valuable for CRM data retrieval. Common use cases include:

* **Data Analysis**: Extract company information for reporting
* **Contact Management**: Access company contact details
* **Lead Processing**: Retrieve company qualification data
* **Account Management**: Monitor company status changes

**Some specific examples**:

* Pulling company data for email campaigns
* Gathering company details for account reviews
* Extracting industry data for market analysis
* Collecting company contacts for outreach

## Important Considerations:

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. All outputs are in list format
3. Properties must exist in HubSpot
4. Lists must be configured in HubSpot

In summary, the HubSpot Company Reader node streamlines company data retrieval from HubSpot CRM, supporting various property selections and list-based filtering for efficient data access.
