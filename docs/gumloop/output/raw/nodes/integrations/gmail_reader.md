> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gmail Reader

This document outlines the functionality and characteristics of the Gmail Reader node, which enables automated email processing from Gmail accounts.

## Node Inputs

The Gmail Reader node accepts the following inputs:

* **Label**: The Gmail label to read from (default: 'INBOX')
* **Number of Emails**: Maximum number of emails to process. Leave blank to read all emails.

### Optional Settings

* **Search Query**: Optional Gmail search syntax to filter emails
* **Mark as Read**: Whether to mark processed emails as read (default: False)
* **Ignore Read Status**: If checked, this will include both unread and previously read emails in your search. By default, only unread emails are processed
* **Read Full Thread**: If checked, the full message chain will be read for each email thread (default: False)

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

## Node Output

The Gmail Reader node produces the following outputs (all in list format):

* **Email Bodies**: Content of the email messages
* **Attached File Names**: Attached file object

> Note that multiple attachments are separated by a comma, eg: `PDF1, PDF2`. You can use the `Split Text` node here to output a list with each file.

* **Message ID**: Unique ID of each individual email
* **Thread ID**: Unique ID of the entire conversation thread that can contain multiple emails
* **Sender Addresses**: Email addresses of senders
* **Recipient Addresses**: All recipients (including CC/BCC)
* **Subjects**: Email subject lines
* **Dates**: Date & timestamp of emails (in UTC)
* **Sender Display Names**: Names of the email sender

## Understanding Attachment Handling

The "Attached File Names" output provides the actual file objects from email attachments. These files can be connected directly to file operation nodes like PDF Reader, CSV Reader, or File Reader for immediate processing.

<div align="center">
  <img src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/gmail_reader_attachment_reading_example.png?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=f961d1e9a4fa6e5d61dd6ef57e3c853c" alt="Gmail Reader connected to PDF Reader" width="600" data-path="images/gmail_reader_attachment_reading_example.png" />
</div>

### Multiple Attachments per Email

When a single email contains multiple attachments, they are combined into one output separated by commas:

```text theme={"dark"}
Example output: "document1.pdf, spreadsheet.xlsx, image.png"
```

To process multiple attachments individually, use the Split Text node:

```text theme={"dark"}
Gmail Reader → Split Text (separator: ", ") → PDF Reader (Loop Mode)
```

This converts the comma-separated attachments into a list, allowing each file to be processed separately.

[Here's a workflow example that reads attachments and processes them through a PDF reader node](https://www.gumloop.com/pipeline?workbook_id=n8YJzn7hS1ZLsgrtDaSj6C)

## Node Functionality

The Gmail Reader node provides automated access to Gmail inbox content and email data.

**Key features include**:

* Support for Gmail search syntax filtering
* Date-based email filtering
* Attachment handling
* Customizable email processing options
* Secure authentication with Gumloop

### Trigger Functionality

This node can also function as a trigger to start your workflow when new emails arrive in Gmail. Learn more about triggers in our [Workflow Triggers documentation](https://docs.gumloop.com/core-concepts/workflow_triggers).

<div align="center">
  <img src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/gmail-reader-trigger.png?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=4b72b2ca371cf8b044980dc5c28f3f0f" alt="Alt text" width="500" data-path="images/gmail-reader-trigger.png" />
</div>

#### How the Gmail trigger works

The Gmail trigger polls your mailbox every \~60 seconds using Gmail's incremental history API and fires your workflow when:

* A new email arrives in the configured label, **or**
* An existing email gains the configured label after delivery (e.g. via a Gmail filter or manual labeling).

A few practical notes:

* **Multiple triggers, multiple labels** — you can configure multiple Gmail triggers on the same account, each watching a different label. There is no per-account label limit.
* **Read status is not a filter** — the trigger fires whether or not you've opened the email in your Gmail UI between when it arrived and when the next poll runs.
* **Latency** — emails are detected within \~30 seconds median, \~60 seconds worst case.
* **Cost** — polls with no new email do minimal work (one Gmail API call) and don't run your workflow.

#### Trigger parameters

When the node is configured as a trigger, only a subset of its parameters apply:

* **Label** — the Gmail label to watch (use `Inbox` for the general inbox).
* **Mark as Read?** — whether to mark each fired email as read after processing.
* **Read as HTML?** — read the body as HTML instead of plain text.

Date-range, search-query, and "number of emails" parameters are manual-mode only and have no effect when the node is in trigger mode.

## When To Use

The Gmail Reader node is particularly valuable in scenarios requiring automated email processing. Common use cases include:

* **Customer Support**: Automatically process incoming support emails
* **Data Extraction**: Extract information from scheduled reports
* **Email Monitoring**: Track important communications
* **Attachment Processing**: Handle incoming file attachments

**Some specific examples**:

* Processing order confirmations
* Collecting daily reports
* Monitoring support tickets
* Archiving attachments

## Example Workflows

### 1. Weekly Email Report Processing

```text theme={"dark"}
Current DateTime → Gmail Reader → Ask AI → Google Sheets Writer
Setup:
- Use Dates?: Yes
- Use Exact Dates?: Yes
- Start Date: Connected to Current DateTime with -7 days modifier
- End Date: Connected to Current DateTime
Purpose: Generate weekly summary of important emails
```

### 2. Monthly Invoice Collection

```text theme={"dark"}
Gmail Reader → PDF Reader → Extract Data → Airtable Writer
Setup:
- Label: Invoices
- Date Range: Last Month
- Search Query: has:attachment filename:pdf
Purpose: Extract and store monthly invoice data
```

### 3. Daily Support Email Categorization

```text theme={"dark"}
Gmail Reader [Trigger] → Categorizer → Slack Message Sender
Setup:
- Label: Support
- Mark as Read?: Yes (prevents the same email being re-surfaced
  by downstream tooling that checks unread status)
Purpose: Classify and route incoming support requests as they arrive.
```

## Important Considerations:

1. Requires Authentication with Gmail - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Manual mode** processes only unread emails by default. Toggle `Ignore Read Status` under `Show More Options` to include read emails. **Trigger mode** fires on new emails regardless of read status — see "How the Gmail trigger works" above.
3. Output types change to single `string` if reading just one email
4. Date filtering in UTC timezone may not match your local time

In summary, the Gmail Reader node streamlines email processing tasks by providing automated access to Gmail content, with flexible filtering options, date range controls, and comprehensive data extraction capabilities.
