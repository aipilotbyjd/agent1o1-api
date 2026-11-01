> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio List Reader

This document outlines the functionality and characteristics of the Attio List Reader node, which enables automated data retrieval from Attio lists.

## Node Inputs

### Required Fields

* **List**: Select the Attio list to read from
* **Outputs**: Choose which attributes to retrieve

## Node Output

Each selected output field becomes an individual output containing the corresponding data.

## Node Functionality

The Attio List Reader node retrieves entries from specified Attio lists.

**Key features include**:

* Multiple field selection
* Support for various data types:
  * Text fields
  * Currency values
  * Domain names
  * Status indicators
  * Actor references
  * Select fields
* Loop Mode support to fetch data from multiple lists
* Secure authentication with Gumloop

## When To Use

The Attio List Reader node is valuable for CRM data retrieval and analysis. Common use cases include:

* **Customer Management**: Access customer information and status
* **Sales Operations**: Track deals and opportunities
* **Account Management**: Monitor account health and metrics
* **Team Coordination**: Share customer data across systems

**Some specific examples**:

* Retrieving customer success metrics for reporting
* Pulling company information for integration
* Monitoring account health scores
* Accessing customer stage information

## Important Considerations:

1. Requires Authentication with Attio - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. List must exist in Attio

In summary, the Attio List Reader node provides streamlined access to Attio CRM data, making it ideal for customer data management and analysis workflows.
