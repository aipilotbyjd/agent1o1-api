---
title: Model catalog
description: Available AI models, pricing, routing, speed classes and plan access.
sidebar_position: 1
---

# Model catalog

The catalog lists every model you can pick in AI nodes and agents, with a display name, provider, capability (text, vision, image, speech, embeddings), speed class and price.

## Providers

OpenAI, Anthropic, Google Gemini, Azure OpenAI, Amazon Bedrock, OpenRouter, Groq, Mistral, DeepSeek, xAI, Fireworks, Together, Cohere, Hugging Face, Ollama and any OpenAI-compatible endpoint. Embeddings: OpenAI, Gemini, Jina, VoyageAI, Cohere. Speech: ElevenLabs.

## Routes and failover

A catalog model has one or more **routes** (provider and endpoint). If a route is paused or fails, the next route is used. Admins can pause a provider platform-wide.

## Plan access

A model can be restricted to certain plans. Choosing one outside your plan raises `ModelNotOnPlan`. Models with unverified prices are blocked from paid use until verified.

## Pricing and credits

Each model has a versioned **price**. A call's credit cost = tokens × price × credit rate. Price changes are logged and can enforce a strict or warn-only mode.

## Lifecycle

Models move through states (active, deprecated, retiring, retired). A daily job retires models past their date (`model-catalog:retire-due`). Workflows that use a retired model fall back or need updating.

## Workspace model settings

Admins can set a workspace default model, restrict the allowed list, and see usage per model per day.

## API

`GET /workspaces/{workspace}/models` lists models the workspace can use. Trial calls are rate limited (6 per minute).
