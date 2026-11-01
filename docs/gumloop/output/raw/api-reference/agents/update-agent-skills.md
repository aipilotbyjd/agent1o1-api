> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attach or detach agent skills

> Attach and/or detach skills on an agent using deltas. This is **not** a replace-list: skills you don't mention are left untouched.

- The operation is idempotent. Re-attaching a skill that's already attached (or detaching one that isn't) is reported under `already_attached` / `already_detached` rather than failing.
- A skill ID may not appear in both `attach` and `detach`.
- Up to 100 unique skill IDs total (`attach` + `detach`) per request.
- Attaching requires `INVOKE` permission on the skill. Detaching is permissive so stale attachments can always be removed.




## OpenAPI

````yaml patch /agents/{agent_id}/skills
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/skills:
    patch:
      tags:
        - Agents
      summary: Attach or detach agent skills
      description: >
        Attach and/or detach skills on an agent using deltas. This is **not** a
        replace-list: skills you don't mention are left untouched.


        - The operation is idempotent. Re-attaching a skill that's already
        attached (or detaching one that isn't) is reported under
        `already_attached` / `already_detached` rather than failing.

        - A skill ID may not appear in both `attach` and `detach`.

        - Up to 100 unique skill IDs total (`attach` + `detach`) per request.

        - Attaching requires `INVOKE` permission on the skill. Detaching is
        permissive so stale attachments can always be removed.
      operationId: updateAgentSkills
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent whose skills to update. Also accepts the reserved
            aliases `gumball` and `analytics`.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                attach:
                  type: array
                  description: Skill IDs to attach. Ignored if already attached.
                  items:
                    type: string
                  default: []
                detach:
                  type: array
                  description: Skill IDs to detach. Ignored if not attached.
                  items:
                    type: string
                  default: []
            examples:
              attachAndDetach:
                summary: Attach one skill and detach another
                value:
                  attach:
                    - skill_2b9c
                  detach:
                    - skill_7f1a
      responses:
        '200':
          description: The resulting skill set plus what this request changed.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agent_id:
                    type: string
                    example: abc123DEFghiJKL
                  skill_ids:
                    type: array
                    description: >-
                      Full set of skills attached to the agent after this
                      request.
                    items:
                      type: string
                  attached:
                    type: array
                    description: Skills newly attached by this request.
                    items:
                      type: string
                  detached:
                    type: array
                    description: Skills newly detached by this request.
                    items:
                      type: string
                  already_attached:
                    type: array
                    description: Requested attaches that were already attached (no-op).
                    items:
                      type: string
                  already_detached:
                    type: array
                    description: Requested detaches that weren't attached (no-op).
                    items:
                      type: string
              examples:
                updated:
                  summary: One attached, one detached
                  value:
                    agent_id: abc123DEFghiJKL
                    skill_ids:
                      - skill_2b9c
                    attached:
                      - skill_2b9c
                    detached:
                      - skill_7f1a
                    already_attached: []
                    already_detached: []
        '400':
          description: >-
            Invalid request — e.g. an ID appears in both `attach` and `detach`,
            more than 100 unique IDs, or a skill to attach doesn't exist or
            isn't active (offending IDs returned in `error.details.skill_ids`).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller lacks update access to the agent, or lacks
            `INVOKE` permission on a skill being attached (offending IDs in
            `error.details.skill_ids`).
        '404':
          description: Agent not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X PATCH
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/skills' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "attach": ["skill_2b9c"],
                "detach": ["skill_7f1a"]
              }'
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````