---
title: Bring your own key (BYOK)
description: Use your own AI provider credentials for a workspace or a member.
sidebar_position: 2
---

# Bring your own key (BYOK)

BYOK lets AI calls use **your** provider account instead of the platform's key.

## Scope

BYOK is **provider-level**, not model-level. One OpenAI key covers every OpenAI model in the catalog.

## Add a key

**Settings → AI providers → Add provider.**

| Method | Providers |
|---|---|
| API key | Most providers |
| OAuth connect | OpenRouter, Hugging Face, ChatGPT (when enabled by the platform) |

After saving, the key is validated. Keys are encrypted and never shown again. A scheduled check (`ai-credentials:check`) revalidates keys hourly, and OAuth tokens refresh every five minutes.

## Who can use it

- **Workspace key**: available to the whole workspace. `ai-credential.manage` to create.
- **Personal key**: used only by its owner (`ai-credential.use-personal`).
- **Policy**: admins can require own keys for certain providers (`WorkspaceAiKeyPolicy`) or forbid personal keys.

## Resolution order

For each AI call: personal key (if allowed and present) → workspace key → platform key. If a model requires your own key and none exists, the call fails with `OwnAiKeyRequired`.

## Billing impact

With your own key the provider bills you directly. Platform credits for AI usage may be reduced or waived according to your plan's rule. **Check:** the exact credit rule for BYOK should be confirmed in `docs/BYOK_PLAN.md`, which lists it as an open decision.

## Security

Keys are encrypted at rest with `APP_KEY`, rate-limited when tested, and excluded from logs.
