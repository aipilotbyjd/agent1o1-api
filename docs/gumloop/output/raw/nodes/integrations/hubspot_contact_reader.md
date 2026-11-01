> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Contact Reader

This document outlines the functionality and characteristics of the HubSpot Contact Reader node, which enables automated contact data retrieval from HubSpot CRM.

## Node Inputs

### Required Field

* **Outputs**: Select contact properties to retrieve
  * Names
  * Emails
  * Phone numbers
  * Lead status
  * Owner assignments
  * And more

### Optional Fields

* **Use List**: Toggle to read from specific HubSpot lists
  * **List**: Select HubSpot list (required if Use List is enabled)

## Node Output

Selected contact properties provided as lists (string\[]).

## Node Functionality

The HubSpot Contact Reader node retrieves contact data from your HubSpot CRM.

**Key features include**:

* Multiple property selection
* Dynamic data retrieval
* Secure authentication with Gumloop

## When To Use

The HubSpot Contact Reader node is valuable for contact data management. Common use cases include:

* **Lead Processing**: Extract contact data for lead nurturing
* **Email Marketing**: Gather contacts for campaigns
* **Data Synchronization**: Update contact information across systems
* **Contact Analysis**: Review contact properties and status

**Some specific examples**:

* Building email lists for targeted campaigns
* Extracting leads for sales follow-ups
* Monitoring contact lifecycle stages
* Creating contact reports for analysis

## Important Considerations:

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. All outputs are in list format
3. Properties must exist in HubSpot
4. Lists must be configured in HubSpot

In summary, the HubSpot Contact Reader node streamlines contact data retrieval from HubSpot CRM, supporting various property selections and list-based filtering for efficient contact management.
