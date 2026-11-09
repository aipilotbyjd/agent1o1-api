---
title: AI nodes
description: Reference for AI automation nodes — ask, extract, classify, summarize, translate, vision, speech, agents and knowledge.
sidebar_position: 2
---

# AI nodes

All AI nodes choose a model from the [model catalog](../08-ai-models/model-catalog.md). With a workspace [own key](../08-ai-models/bring-your-own-key.md), calls use your provider key.

| Node | What it does |
|---|---|
| **Ask AI** | Free-form prompt to a model. Optionally returns structured output. |
| **AI Answer** | Answers a question from supplied context. |
| **AI Extract** | Pulls named fields out of unstructured text into structured data. |
| **AI Classify** | Assigns text to one of your categories. |
| **AI Score** | Rates content against a scale or rubric. |
| **AI Sentiment** | Positive, negative or neutral with a confidence. |
| **AI Summarize** | Summarizes text to a target length or style. |
| **AI Rewrite** | Rewrites text in a tone or format. |
| **AI Translate** | Translates between languages. |
| **AI Compare** | Compares two pieces of content. |
| **AI Moderate** | Flags unsafe content. |
| **AI Read File** | Reads a document or image (PDF, image, and more) and answers about it. |
| **AI Image** | Generates an image. Output is an artifact. |
| **AI Speech** | Text to speech. |
| **AI Transcribe** | Speech to text. |
| **AI Route to Agent** | Chooses which agent should handle a message. |
| **Agent** | Runs a full [agent](../05-agents/overview.md) session inside the workflow. |
| **Knowledge Save** | Stores text into the [knowledge base](../05-agents/knowledge-base.md). |
| **Knowledge Search** | Semantic search over the knowledge base. |

## Tips

- Prefer **Extract** or structured output over parsing free text yourself.
- Set a **max credits per run** limit through your plan to cap spend in loops.
- Prompts are plain text with mapped inputs. Put instructions first and data last.
- Provider outages or pauses return clear errors (`ProviderPaused`, `ModelBusy`). Add an error edge to retry or fall back.

## Spend controls

Workspaces have daily and weekly **AI cost limits** by plan. When a limit is reached AI calls are blocked with a usage error until the window resets or the plan changes.
