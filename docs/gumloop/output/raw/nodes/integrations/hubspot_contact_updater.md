> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Contact Updater

This document outlines the functionality and characteristics of the HubSpot Contact Updater node, which enables automated contact updates in HubSpot CRM.

## Node Inputs

### Required Fields

* **Contact Email**: Email address to identify the contact
* **Inputs**: Select properties to update
  * Names
  * Phone numbers
  * Lead status
  * Company details
  * Custom fields
  * And more...

### Dynamic Inputs

Connect your node outputs to any contact property you wish to update. Properties are populated based on your HubSpot configuration.

### Available Properties

The Hubspot Contact Updater node displays properties that can be updated based on your existing contact data. Important notes about property visibility:

* Only properties that already have values across multiple existing contacts will appear in the node's input fields. The node samples a random set of contacts to determine which properties to display.
  * This helps keep the node focused by only showing actively used properties.
* New properties with no existing values across contacts will not appear automatically.

## Node Output

Success/failure status of the update operation.

## Node Functionality

The HubSpot Contact Updater node modifies existing contact records in HubSpot.

**Key features include**:

* Email-based contact identification
* Multiple property updates
* Custom field support
* Loop Mode for batch updates
* Dynamic property mapping
* Secure authentication with Gumloop

## When To Use

The HubSpot Contact Updater node is valuable for contact maintenance. Common use cases include:

* **Data Enrichment**: Update contact details from external sources
* **Status Updates**: Modify lead or lifecycle stages
* **Information Correction**: Fix or update contact data
* **Bulk Updates**: Process multiple contact changes

**Some specific examples**:

* Updating contact information from form submissions
* Modifying lead status based on interactions
* Enriching contact data from third-party tools
* Synchronizing contact details across systems

## Important Considerations:

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Contact email must exist in HubSpot
3. Only specified fields are updated
4. Properties must match HubSpot fields

In summary, the HubSpot Contact Updater node streamlines contact maintenance in HubSpot CRM, supporting both individual and batch updates with flexible property selection.
