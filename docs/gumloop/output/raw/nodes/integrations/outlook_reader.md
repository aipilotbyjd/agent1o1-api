> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Outlook Reader

This document outlines the functionality and characteristics of the Outlook Reader node, which enables automated email retrieval from Microsoft Outlook.

## Node Inputs

### Required Field

* **Folder**: Select Outlook folder to read from (default: 'Inbox')

### Optional Fields

* **Email Information**: Choose data types to retrieve:
  * Email Bodies
  * Attached Filenames
  * Message IDs
  * Sender/Recipient Addresses
  * Subjects
  * Dates
* **Number of Emails**: Limit emails to read (default: 10)
* **Search Query**: Filter emails based on specific criteria
* **Mark as Read**: Option to mark processed emails as read
* **Ignore Read Status**: Include both read and unread emails

## Node Output

All selected email information appears as list outputs (string\[]):

* Email content
* Attachment filenames

> Note that multiple attachments are separated by a comma, eg: `PDF1, PDF2`. You can use the `Split Text` node here to output a list with each file.

* Message IDs
* Addresses
* Subjects
* Dates
* Sender Display Names

### Date Range Filtering

Filter emails by a specific time period, this option is available under `Show More Options`

* **Use Dates?**: Enable this toggle to filter emails by time period

* **Date Range**: Choose from preset ranges for quick filtering:
  * Last 24 Hours
  * Last Week
  * Last Month
  * Last 3 Months
  * Last 6 Months

* **Use Exact Dates?**: Toggle this option to specify custom date ranges
  * When enabled, you can set precise Start and End dates
  * When disabled, the preset Date Range selection is used

* **Start Date (UTC)**: The beginning of your custom date range (only available when Use Exact Dates is enabled)

* **End Date (UTC)**: The end of your custom date range (only available when Use Exact Dates is enabled)

Date filtering is useful for:

* Historical email analysis
* Periodic reporting
* Retrieving emails from specific events or timeframes
* Automating regular email processing batches

> **Note**: When "Use Exact Dates?" is enabled, you can expose the Start Date and End Date parameters through "Configure Inputs" and connect them directly to the `Datetime` node for dynamic date ranges.

## Node Functionality

The Outlook Reader node retrieves email data from specified Outlook folders.

**Key features include**:

* Multiple data type selection
* Email filtering capabilities
* Read status management
* Batch email processing
* Attachment handling
* Secure authentication with Gumloop

## When To Use

The Outlook Reader node is particularly valuable in scenarios requiring automated email processing. Common use cases include:

* **Email Monitoring**: Track incoming communications
* **Attachment Processing**: Handle incoming files
* **Communication Analysis**: Review email patterns
* **Data Extraction**: Pull information from emails

**Some specific examples**:

* Processing incoming support tickets
* Collecting daily report attachments
* Monitoring supplier communications
* Tracking customer inquiries

## Important Considerations:

1. Requires Authentication with Microsoft - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Only processes unread emails by default
3. All outputs are provided as lists

In summary, the Outlook Reader node streamlines email retrieval and processing from Outlook, supporting various data types and filtering options for efficient email automation.
