> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Outlook Sender

This document outlines the functionality and characteristics of the Outlook Sender node, which enables automated email sending through Microsoft Outlook.

## Node Inputs

### Required Fields

* **Body**: Main content of the email
* **Recipients**: Email addresses of recipients
  * Use comma separation for multiple recipients
  * Use 'cc:' prefix for CC recipients
  * Use 'bcc:' prefix for BCC recipients
* **Subject**: Email subject line

### Optional Fields

* **Save as draft**: Option to save email as draft instead of sending
* **Send as html**: Enable HTML formatting in the email
* **Attachment file names**: Files to attach to the email
* **Message ID**: ID of email to reply to (for reply functionality) - You can pass this dynamically using the 'Outlook Reader' node

## Node Output

* **Email Status**: Success or failure status of the send operation

## Node Functionality

The Outlook Sender node automates email sending through Microsoft Outlook.

**Key features include**:

* Multiple recipient types (To, CC, BCC)
* HTML formatting support
* File attachment capability
* Draft saving option
* Reply functionality
* Loop Mode for batch sending
* Secure authentication with Gumloop

## When To Use

The Outlook Sender node is particularly valuable in scenarios requiring automated email communication. Common use cases include:

* **Customer Communication**: Send automated responses or updates
* **Report Distribution**: Share regular reports with stakeholders
* **Team Updates**: Send automated team notifications
* **Document Sharing**: Distribute files via email

**Some specific examples**:

* Sending weekly performance reports
* Distributing meeting minutes with attachments
* Sending customer order confirmations
* Automated response to inquiries

## Important Considerations:

1. Requires Authentication with Microsoft - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. HTML formatting requires 'Send as html' to be enabled
3. All recipient emails must be valid

In summary, the Outlook Sender node provides a robust way to automate email communications through Microsoft Outlook, supporting various email formats and recipient types.
