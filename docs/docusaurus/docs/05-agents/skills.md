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

Skills can be created by hand or **synced from a source** (for example a Git repository). Sources are re-synced every 15 minutes (`skills:sync-sources`). Publish checks can block a skill that fails validation (`SkillPublishBlocked`).

## Attaching to agents

`POST /agents/{agent}/skills/{skill}` attaches a skill. Permission: `agent.skill.manage`.

## Public API

Skills are listed through the internal API today; see [Public API](../12-api/public-api.md) for what is exposed to API keys.
