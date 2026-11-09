---
title: Connectors and connections
description: Authorize apps once and reuse them across workflows, agents and triggers.
sidebar_position: 1
---

# Connectors and connections

A **connector** defines an integration. A **connection** (connector credential) is your authorized account for it.

## Authentication types

| Type | Examples | How |
|---|---|---|
| **OAuth 2.0** | Google, Slack, Linear, Jira, Notion | Click **Connect**, approve in the provider, return to the app |
| **API key / token** | Telegram, Twilio, SMTP, Mailgun, Typeform | Enter the key in the connection form |

Credentials are stored encrypted. OAuth tokens refresh automatically (`connectors:refresh-expiring` every 5 minutes), and a health check runs hourly.

## Managing connections

**Settings → Connections** (or the connect dialog inside a node):

- **Add** a connection and give it a name.
- **Default**: mark one connection per connector as the default.
- **Test**: validates the credential without side effects (rate limited to 10 per minute).
- **Usage**: see which workflows and agents use a connection.
- **Delete**: disconnects the app. Dependent workflows will fail until reconnected.

## Scope

Connections are workspace-scoped. Personal connections (visible only to you) and shared ones both exist; see `ConnectorCredentialScope`. Permissions: `connector.view`, `connector.manage`.

## OAuth flow

1. `POST /workspaces/{workspace}/connector-credentials/oauth/initiate` returns the provider URL.
2. The provider redirects back, and `POST /connector-credentials/oauth/complete` stores the tokens. State is validated to prevent forgery.

## Public API

With the `connectors:manage` ability an API key can list connectors and create, read and delete connection records. See [Public API](../12-api/public-api.md).

## Troubleshooting

- **Reconnect needed**: the token was revoked or scopes changed. Reconnect from the connection's page.
- **Missing scopes**: reconnect and accept all requested scopes.
- **Provider paused**: an admin has paused the provider platform-wide. Try again later.
