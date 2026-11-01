> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Engagement Reader

This document outlines the functionality and characteristics of the HubSpot Engagement Reader node, which enables automated engagement data retrieval from HubSpot CRM.

## Node Inputs

### Required Field

* **Company Domain**: Domain name to filter engagements (e.g., "google.com")

### Optional Field

* **Outputs**: Select engagement types to retrieve:
  * Emails
  * Notes
  * Meetings
  * Other communications (WhatsApp, LinkedIn, SMS)

## Node Output

Selected engagement types provided as lists (string\[]):

* **Emails**: Email communication records
* **Notes**: Internal notes and annotations
* **Meetings**: Meeting records
* **Other**: WhatsApp, LinkedIn, SMS communications

## Node Functionality

The HubSpot Engagement Reader node retrieves company engagement history.

**Key features include**:

* Multiple engagement types
* Company-specific filtering
* Communication tracking
* Secure authentication with Gumloop

## When To Use

The HubSpot Engagement Reader node is valuable for relationship tracking. Common use cases include:

* **Communication Analysis**: Review interaction history
* **Customer Engagement**: Track communication patterns
* **Meeting Monitoring**: Review meeting frequencies
* **Internal Documentation**: Access team notes

**Some specific examples**:

* Analyzing email communication patterns
* Reviewing meeting history with clients
* Monitoring internal note documentation
* Tracking multi-channel communications

## Important Considerations:

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Company domain must exist in HubSpot
3. All outputs are in list format

In summary, the HubSpot Engagement Reader node streamlines access to company engagement history, providing comprehensive communication tracking and analysis capabilities.
