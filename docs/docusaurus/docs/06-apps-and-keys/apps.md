---
title: Connect an app
description: Connect Slack, Gmail, Notion and 60+ other apps so your playbooks and agents can use them.
sidebar_position: 1
---

# Connect an app

**Apps** holds the accounts you've connected. Connect an account once, and use it in any playbook, agent or trigger.

## Connect

1. Open **Apps** in the sidebar.
2. Choose **Connect App**, or **Explore Catalog** to browse.
3. Search for the app.
4. Follow the prompts.

There are two ways apps connect:

- **Sign in with the app** (Google, Slack, Notion, Jira, and many more). You approve access in the app's own window, and return to Agent1o1.
- **Paste a key** (Telegram, Twilio, Mailgun, and others). Enter the API key or token from the app. It is stored encrypted and never shown again.

## Manage connections

- **Test** a connection to check it still works.
- Mark one connection as the **default** if you have several for the same app.
- See where a connection is used before you remove it.
- **Remove** a connection to disconnect it. Playbooks that use it will fail until you reconnect.

Reconnect if you see "reconnect needed", usually after you changed your password in that app, or revoked access.

## Who can see my connections

Connections belong to the workspace. Admins can manage all of them. Some can be kept personal to you.

## Which apps are supported

See the full list in [Steps you can use](../03-playbooks/steps.md#app-steps).

## Staying safe

- Connect with an account that has only the access your automations need.
- Review connections now and then, and remove any you no longer use.
