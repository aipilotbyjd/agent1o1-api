> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Custom SMTP Email Sender

This document explains the Custom SMTP Email Sender node, which sends emails using your own SMTP server.

## Node Inputs

### Required Fields

* **Recipient Email**: Destination address(es)
* **Email Subject**: Message subject line
* **SMTP Server Address**: Server hostname
* **Sender Email**: Your email address
* **Sender Password**: Your email password

### Optional Fields

* **SMTP Server Port**: Default 465 (SSL) or 587 (TLS)
* **Sender Display Name**: Name shown to recipients
* **Email Body**: Message content
* **Send as HTML**: Enable HTML formatting
* **Connection Type**: SSL/TLS/STARTTLS
* **Attachment**: File to include

### Show As Input Options

You can expose these fields as inputs:

* Recipient Email
* Email Subject
* SMTP Settings
* Sender Details

## Node Functionality

The Custom SMTP Email Sender node:

* Sends custom emails
* Supports HTML content
* Handles attachments
* Uses secure connections
* Enables batch sending

## When to Use

Use this node when you need to:

1. **Send Automated Emails**:
   * Customer notifications
   * System alerts
   * Report distribution

2. **Custom Email Setup**:
   * Use your own domain
   * Maintain brand identity
   * Control email delivery

3. **Bulk Communications**:
   * Newsletter distribution
   * Batch notifications
   * Mass updates

## Email Format Examples

1. **Single Recipient**:

```text theme={"dark"}
user@example.com
```

2. **Multiple Recipients**:

```text theme={"dark"}
user1@example.com, user2@example.com
```

3. **With CC/BCC**:

```text theme={"dark"}
user@example.com, cc:copy@example.com, bcc:hidden@example.com
```

## Important Considerations

1. Check email provider settings
2. Use app passwords if required
3. Test connection first
4. Configure spam settings

In summary, the Custom SMTP Email Sender node provides complete control over email sending using your own SMTP server in Gumloop workflows.
