---
title: Environment variables
description: The groups of settings in .env and what each controls.
sidebar_position: 4
---

# Environment variables

`.env.example` is grouped into labeled sections. Add new variables in their section and comment optional ones.

:::warning Before going live
Set `APP_ENV=production`, keep `APP_DEBUG=false`, generate `APP_KEY`, enable `SESSION_SECURE_COOKIE`, and review the spend limits.
:::

| Group | Variables | Notes |
|---|---|---|
| **Application** | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_FRONTEND_URL`, `CORS_ALLOWED_ORIGINS` | `APP_PREVIOUS_KEYS` lets old keys decrypt after rotation |
| **Database** | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_URL` | Use PostgreSQL, port 5432 |
| **Redis, cache, session, queue** | `REDIS_*`, `CACHE_STORE`, `SESSION_*`, `QUEUE_CONNECTION` | Queue should be Redis |
| **Storage** | `FILESYSTEM_DISK`, `AWS_*` | S3-compatible for artifacts |
| **Mail** | `MAIL_*`, `POSTMARK_API_KEY`, `RESEND_API_KEY` | Verification, invites, alerts |
| **Realtime** | `BROADCAST_CONNECTION`, `REVERB_*`, `VITE_REVERB_*` | |
| **Auth** | `PASSPORT_PASSWORD_CLIENT_ID`, `PASSPORT_PASSWORD_CLIENT_SECRET` | From `passport:client --password` |
| **Social login** | `GOOGLE_*`, `GITHUB_*` (id, secret, redirect) | |
| **AI providers** | `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `OPENROUTER_API_KEY`, `GROQ_API_KEY`, `MISTRAL_API_KEY`, `DEEPSEEK_API_KEY`, `XAI_API_KEY`, `FIREWORKS_API_KEY`, `TOGETHER_API_KEY`, `COHERE_API_KEY`, `HUGGINGFACE_API_KEY`, `ELEVENLABS_API_KEY`, `OLLAMA_API_KEY`, `AZURE_OPENAI_API_KEY`, `AWS_BEARER_TOKEN_BEDROCK` | Platform keys, used when a workspace has no own key |
| **Embeddings** | `AI_EMBEDDINGS_PROVIDER`, `GEMINI_EMBEDDINGS_DIMENSIONS`, `JINA_API_KEY`, `VOYAGEAI_API_KEY` | |
| **BYOK connect** | `BYOK_CONNECT_*_ENABLED`, `HUGGINGFACE_OAUTH_*`, `CHATGPT_OAUTH_*` | OAuth connect for providers |
| **Assistant** | `ASSISTANT_NAME`, `_TAGLINE`, `_EMOJI`, `_COLOR`, `ASSISTANT_FEATURE_*`, `ASSISTANT_EMAIL*`, `ASSISTANT_INBOUND_DOMAIN`, `ASSISTANT_SLACK_*`, `ASSISTANT_SMS_*`, `TWILIO_*` | Branding and channels |
| **Triggers** | `TRIGGERS_NODES_ENABLED`, `TRIGGERS_PUSH_<APP>_ENABLED`, `TRIGGERS_SLACK_SIGNING_SECRET`, `TRIGGERS_GMAIL_PUBSUB_TOPIC`, ... | Turn on push per app |
| **Billing** | `STRIPE_*`, plan product and price ids | Keys, webhook secret, price ids per plan and interval |
| **Super admin** | `SUPER_ADMIN_*` (enabled, prefix, security) | |

Run `php artisan config:show <key>` to inspect a resolved value.
