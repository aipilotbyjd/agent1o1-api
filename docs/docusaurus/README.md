# Agent1o1 documentation source (Docusaurus)

Markdown source for the Agent1o1 documentation site. Nothing here is built yet.

## How to use it

1. Create a Docusaurus site: `npx create-docusaurus@latest agent1o1-docs classic`
2. Delete the template contents of the site's `docs/` folder.
3. Copy this folder's `docs/*` into the site's `docs/`.
4. Copy `sidebars.js` over the site's `sidebars.js`.
5. In `docusaurus.config.js`, set `docs.routeBasePath: '/'` if you want the docs at the site root (`intro.md` is the landing page).

## Conventions

- Numeric folder prefixes (`01-`, `02-`) set sidebar order. Docusaurus strips them from URLs.
- Every page has `title` and `description` front matter. `sidebar_position` orders pages inside a folder.
- Each folder has a `_category_.json` that sets its sidebar label and position.
- Use `:::tip`, `:::note`, `:::warning` admonitions as shown in existing pages.

## Section map

| Folder | Audience | Content |
|---|---|---|
| `01-getting-started` | Everyone | What Agent1o1 is, quickstart, core concepts, onboarding |
| `02-workflows` | Builders | Canvas, data types, runs, flow control, versions, pages |
| `03-nodes` | Builders | Node catalog by category and integration |
| `04-triggers` | Builders | Webhook, schedule, polling, app triggers, conditions |
| `05-agents` | Builders | Agents, tools, autonomy, skills, knowledge, evals |
| `06-assistant` | End users | Personal assistant, briefings, Smart Inbox, channels |
| `07-connections` | Builders and admins | Connectors, OAuth, secrets |
| `08-ai-models` | Builders and admins | Model catalog, bring your own key |
| `09-content` | Everyone | Artifacts, templates, library, public pages |
| `10-workspace` | Admins | Members, roles, permissions, notifications, audit |
| `11-billing` | Admins | Plans, credits, packs, overage, referrals |
| `12-api` | Developers | Public API, webhooks, errors |
| `15-reference` | Everyone | Glossary, statuses, limits, FAQ, troubleshooting |
