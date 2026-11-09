---
title: Vault
description: Store passwords, tokens and keys safely and use them without ever showing them.
sidebar_position: 2
---

# Vault

The **Vault** keeps sensitive values (passwords, API tokens, signing keys) encrypted, so you never paste them into a playbook.

## Add a secret

1. Open **Vault** in the sidebar.
2. Add a secret. Give it a **name** such as `MY_SERVICE_TOKEN`, enter the **value**, and add a short note on what it's for.
3. Save. The value is encrypted and **can't be read back later**.

## Use a secret

In a step's settings, choose the secret from the Vault instead of typing the value. It is only used when the step runs, and it's hidden in run history.

## Change or remove

- **Edit** a secret to enter a new value.
- **Copy** its name to use elsewhere.
- **Delete** it when it's no longer needed. Steps that use a deleted secret will fail until you point them at another.

## Good practice

- Use a separate secret for each service.
- Replace secrets when someone leaves the team.
- Never put secrets in step text, instructions or knowledge documents.
