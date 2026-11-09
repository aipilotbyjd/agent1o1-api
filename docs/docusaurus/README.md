# Agent1o1 customer documentation (Docusaurus source)

Customer-facing help for people who use the Agent1o1 app. Written around what you see and click, using the app's own names:

| In the app | Also called |
|---|---|
| Playbook | workflow |
| Blueprint | template |
| Apps | connections / integrations |
| Vault | secrets |
| Trail | run history |

## Use it

1. `npx create-docusaurus@latest docs-site classic`
2. Replace the site's `docs/` folder with this `docs/` folder and `sidebars.js` with this one.
3. Set `docs.routeBasePath: '/'` in `docusaurus.config.js` so `intro.md` is the home page.

Folder prefixes (`01-`, `02-`) set sidebar order and are hidden from URLs. API paths and developer detail live only in `09-developers`.
