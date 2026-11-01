> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio List Writer

This document outlines the functionality and characteristics of the Attio List Writer node, which enables automated entry creation and updating in Attio lists.

## Node Inputs

### Required Fields

* **List**: Select the Attio list to write to
* **Inputs**: Choose fields to populate and connect data

## Node Output

Success/failure status of the write operation.

## Node Functionality

The Attio List Writer node creates or updates entries in Attio lists.

**Key features include**:

* Automatic Company/Person creation
* Existing entry updates
* Multiple field types
* Duplicate prevention
* Loop Mode support
* Secure authentication with Gumloop

## When To Use

The Attio List Writer node is valuable for CRM data management. Common use cases include:

* **Customer Onboarding**: Create new customer records
* **Data Updates**: Modify existing customer information
* **Lead Management**: Add new prospect data
* **Account Maintenance**: Update account statuses

**Some specific examples**:

* Creating customer entries from form submissions
* Updating account health scores from analysis
* Adding new leads from marketing campaigns
* Updating customer success metrics

## Important Considerations:

1. Requires Authentication with Attio - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Automatically creates related entities if needed
3. Updates existing entries to prevent duplicates
4. Input types must match field requirements
5. Companies/Persons are created if missing

In summary, the Attio List Writer node streamlines data entry and updates in Attio, automatically handling entity creation and updates for efficient CRM management.
