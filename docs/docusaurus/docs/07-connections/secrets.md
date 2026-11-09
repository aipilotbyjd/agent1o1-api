---
title: Secrets
description: Store tokens and passwords encrypted and reference them from nodes.
sidebar_position: 2
---

# Secrets

A **secret** is a named, encrypted value in a workspace. Use it for anything you would not paste into a node: tokens, passwords, signing keys.

## Create and use

1. **Settings → Secrets → New secret**. Enter a name and the value.
2. Reference the secret by name in a node field that accepts secrets.
3. The value is decrypted only at run time and masked in logs and outputs.

## Behavior

- Values cannot be read back after saving. Update a secret by entering a new value.
- Deleting a secret that is in use makes dependent runs fail with a **missing secret** error.
- Permissions: `secret.view` (names only) and `secret.manage`.

## Endpoints

`GET/POST /workspaces/{workspace}/secrets`, `GET/PATCH/DELETE /secrets/{secret}`.

## Encryption

Secrets use the application key (`APP_KEY`). Rotating the key without keeping the old one in `APP_PREVIOUS_KEYS` makes existing secrets unreadable.
