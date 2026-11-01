> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Email Sender

Send marketing emails using HubSpot's Single-Send API. This node enables you to programmatically send pre-designed HubSpot marketing emails to specific recipients.

## Prerequisites

* HubSpot account with Marketing Hub access
* Published Single-Send API emails in HubSpot
* Verified sender email addresses in HubSpot

## Node Configuration

### Required Parameters

* **Email**
  * Select a published Single-Send API marketing email from your HubSpot account
  * Only published emails are available for selection
  * Marketing emails must be enabled for Single-Send API

* **Recipient**
  * The email address of the recipient
  * Can be a single email or dynamic input
  * Must be a valid email format

### Optional Parameters

* **From**
  * Sender's name and email
  * Must exist and be verified in HubSpot workspace
  * Format: `Name <email@domain.com>`

* **Reply To**
  * Comma-separated list of reply-to email addresses
  * Example: `support@company.com, sales@company.com`
  * All addresses must be verified in HubSpot
  > If not specified, the sender email address will be used.

* **CC**
  * Comma-separated list of CC recipients
  * Multiple addresses separated by commas
  * Example: `user1@domain.com, user2@domain.com`

* **BCC**
  * Comma-separated list of BCC recipients
  * Multiple addresses separated by commas
  * Example: `manager@domain.com, archive@domain.com`

## Email Templates and Custom Properties

> **Note**: Custom properties are configured in your HubSpot email template, not in this node. You can define custom properties in your email template and subject line using placeholders as described in [HubSpot's documentation](https://developers.hubspot.com/beta-docs/guides/api/marketing/emails/single-send-api#customproperties). These properties will be replaced with the values from your HubSpot database when the email is sent.

The node will use these templates as-is and HubSpot will handle the property replacement during sending.

## Loop Mode

The node supports batch operations for sending emails:

* Can process a list of recipient emails
* Maintains same email template and sender details
* Useful for bulk email campaigns
* Each recipient receives an individual email

## Common Use Cases

1. Automated Marketing Campaigns
   * Welcome series emails
   * Product announcements
   * Event invitations

2. Customer Communication
   * Onboarding sequences
   * Follow-up emails
   * Support responses

3. Batch Operations
   * Newsletter distribution
   * Product updates
   * Customer surveys

## Important Notes

* **Email Template**: Only published Single-Send API emails can be used
* **Sender Verification**: All sender emails must be verified in HubSpot
* **Analytics**: Email performance can be tracked in HubSpot dashboard

## Troubleshooting

Common issues and solutions:

* **Email Not Sending**: Verify sender email is verified in HubSpot
* **Template Errors**: Ensure email is published and Single-Send API enabled
* **Custom Property Errors**: Validate property names and formats
