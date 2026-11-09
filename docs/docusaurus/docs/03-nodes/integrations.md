---
title: App integrations
description: The apps Agent1o1 can read from and write to, grouped by area.
sidebar_position: 5
---

# App integrations

Integrations ship as nodes (actions) and triggers. Each app is a [connector](../07-connections/connectors.md) that you authorize once per workspace.

| Area | Apps |
|---|---|
| **Google** | Gmail, Google Drive, Docs, Sheets, Slides, Calendar, Contacts, Forms, Tasks, Maps, Analytics, Search Console, YouTube |
| **Microsoft** | Outlook, Microsoft Teams |
| **Chat and messaging** | Slack, Discord, Telegram, Twilio (SMS, WhatsApp, calls), Zoom |
| **Email delivery** | SendGrid, Mailgun, Postmark, Resend, Brevo, SMTP email |
| **Marketing and forms** | Mailchimp, Kit (ConvertKit), ActiveCampaign, Klaviyo, Typeform |
| **CRM and sales** | HubSpot, Salesforce, Pipedrive, Zoho (CRM, Books, Campaigns, Cliq, Desk, Mail, Projects), Apollo, Hunter |
| **Projects and tasks** | Jira, Linear, Asana, ClickUp, Monday, Trello, Notion, Airtable |
| **Dev** | GitHub |
| **Commerce and payments** | Stripe, Shopify |
| **Scheduling and e-signature** | Calendly, Cal.com, DocuSign, Dropbox Sign |
| **Search** | Brave Search, Tavily |

## How connections work

- **OAuth apps** (Google, Slack, Linear, Jira, and others): click **Connect**, approve, done. Tokens refresh automatically.
- **Own-key apps** (Telegram, Discord, Twilio, SMTP, Mailgun, and others): paste your own API key or token in the connection form. Nothing is registered on the platform side.
- Each connection has a **test** action and a health check that flags broken credentials.

## Dropdowns, names and ids

Pick lists load live from the app. Fields that take a team, status, label, person or project accept the id or the name. Ambiguous names are an error rather than a guess.

## Triggers per app

Triggers are as instant as the provider allows:

- **Instant** (webhook or push): for example Telegram, Typeform, Mailchimp, Kit, Stripe, Shopify, Slack events.
- **Polled**: for example Discord messages, Jira and Linear new issues. Polling is limited by plan (minimum interval 15, 5 or 1 minute).

The node tells you which mode applies. If an app's single webhook slot is already used somewhere else, Agent1o1 refuses to take it over and tells you where it points.

:::note
Several integrations were written from public provider docs and not yet run against live accounts. See `docs/NODES_CATALOG.md` ("To verify against the providers") before promising behavior to customers.
:::

## Requesting a new integration

Integrations are code plus catalog data. Contributors add a node family under `app/Nodes/Integrations/{App}` and a connector row in `database/setup/connectors.php`. See [Architecture](../13-self-hosting/architecture.md).
