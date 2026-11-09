---
title: Public pages
description: Turn a workflow into a shareable web page or form.
sidebar_position: 3
---

# Public pages

A **page** wraps a workflow in a shareable UI. Visitors fill a form, run the workflow, and see the result, without an account.

## Building a page

Pages are made of **sections** and **blocks**: heading, text, image, divider, spacer, callout, button, video, FAQ, testimonial, stats, logos, tabs, countdown, steps, features, code, pricing, embed, gallery, compare, progress, social, timeline.

Workflow-aware blocks: **Field** (an input), **Submit** (starts the run), **Output** (shows results), **Status** (shows progress).

## Access

| Access | Who |
|---|---|
| `workspace` | Signed-in members |
| `link` | Anyone with the link |
| `password` | Anyone with the password |

## Visitor endpoints

```
GET  /api/v1/p/{token}                       page content
GET  /api/v1/p/{token}/meta                  title, description, branding
POST /api/v1/p/{token}/unlock                enter a password
POST /api/v1/p/{token}/submissions           submit the form (starts a run)
GET  /api/v1/p/{token}/submissions/{id}      read the result
POST /api/v1/p/{token}/events                analytics events
```

Rate limits: reads 120/min, submissions 10/min, unlock 5/min.

## Comments and branding

Members can comment on page drafts. The **Pro** plan feature `remove_branding` hides platform branding. Pages require the `public_pages` plan feature and `page.manage` permission.
