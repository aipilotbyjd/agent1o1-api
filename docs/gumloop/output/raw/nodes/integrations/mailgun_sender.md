> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Mailgun Sender

This document outlines the functionality and characteristics of the Mailgun Sender node, which enables automated email sending at scale using the Mailgun API.

## Node Inputs

### Required Fields

* **Body**: Email content
* **Recipients**: Comma-separated email addresses
* **Subject**: Email subject line
* **Sender Email**: Your sending email address

### Optional Fields

* **Sender Display Name**: Name shown in "From" field
* **Attachment File Name**: Files to attach to email (use comma separated values for multiple files)

## Node Output

* Email send confirmation and status

## Node Functionality

The Mailgun Sender node provides scalable email sending capabilities through Mailgun's API.

**Key features include**:

* HTML and plain text support
* File attachment capability
* Bulk email sending
* Customizable sender info
* Loop Mode for batch sending
* Secure authentication with Gumloop

## When To Use

The Mailgun Sender node is essential when you need to send emails at scale. Common use cases include:

* **Email Campaigns**: Send bulk marketing emails
* **Notifications**: Distribute system alerts
* **Report Distribution**: Send automated reports
* **Customer Communication**: Handle transactional emails

**Some specific examples**:

* Sending monthly newsletters with attachments
* Distributing daily reports to stakeholders
* Sending order confirmations to customers
* Alerting team members about system events

## Important Considerations:

1. Requires Mailgun API Key - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Recipients must be valid email addresses

In summary, the Mailgun Sender node provides reliable, scalable email sending capabilities, perfect for both bulk email campaigns and individual transactional emails.
