# Postman Collection

`agent1o1.postman_collection.json` covers every endpoint in `routes/api.php` (268 routes), generated directly from the route definitions and each endpoint's `FormRequest` validation rules.

## Import & use

1. Import the collection into Postman (or Insomnia/Bruno via their Postman-import support).
2. The collection ships with placeholder variables (`base_url`, `workspace_id`, `agent_id`, ...) — set `base_url` for your environment (defaults to `http://localhost:8000`).
3. Run **Auth → Login**; it automatically stores the returned `access_token` / `refresh_token` into the collection variables so every other request authenticates via the collection-level bearer auth.
4. Most endpoints are workspace-scoped — after creating/selecting a workspace, set the `workspace_id` variable.

## Keeping it in sync

There's no automated drift check yet. When adding or changing endpoints in `routes/api.php`, update the matching request(s) in the collection by hand, or regenerate from a route/FormRequest sweep the way this file was built.
