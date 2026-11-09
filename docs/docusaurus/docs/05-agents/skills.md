---
title: Skills
description: Reusable instruction bundles with references and scripts that agents load when relevant.
sidebar_position: 4
---

# Skills

A **skill** packages know-how: a description of when to use it, instructions, reference documents and optional scripts.

## Parts of a skill

| Part | Purpose |
|---|---|
| Name and description | The agent reads these to decide when to load the skill |
| Instructions | The main guidance, loaded on demand |
| **References** | Supporting documents (`/skills/{skill}/references`) |
| **Scripts** | Optional helper scripts (`/skills/{skill}/scripts`) |
| Versions | Immutable history |

## Sources

Skills can be created by hand or **synced from a source** (for example a Git repository). Sources re-sync automatically every 15 minutes, and a skill that fails validation cannot be published.

## Attaching to agents

`POST /agents/{agent}/skills/{skill}` attaches a skill. Permission: `agent.skill.manage`.

## Public API

Manage skills in the app. They are not available through API keys yet.
