# Items to verify before publishing

Notes removed from the customer docs.

- `02-workflows/data-types-and-mapping.md`: confirm the exact interpolation syntax from the templating engine in `app/Services/Workflows` before publishing this page.
- `04-triggers/webhook.md`: confirm the list of `WebhookAuthentication` modes in `app/Enums/Triggers/WebhookAuthentication.php`.
- `01-getting-started/quickstart.md`: the exact request body field names (`inputs`) should be confirmed against the Public API controller before publishing.
- `10-workspace/roles-and-permissions.md`: the role-to-permission matrix is defined in `app/Enums/Workspaces/Role.php`. Replace this summary with the exact table before publishing.
- `03-nodes/custom-nodes.md`: the exact authoring model (declarative definition vs. code) should be confirmed from `app/Models/Nodes/CustomNode.php` and the nodes API before publishing detailed steps here.
- `12-api/errors-and-limits.md`: confirm the exact status mapping in `bootstrap/app.php` and `app/Exceptions/Http` before publishing.
- `12-api/public-api.md`: request and response field names below were derived from route definitions. Confirm payloads against the controllers and resources before publishing.

- `03-nodes/integrations.md`: several integrations were written from public provider docs and not run against live accounts (see `docs/NODES_CATALOG.md`).
