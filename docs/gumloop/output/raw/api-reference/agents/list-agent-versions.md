> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List agent versions

> List the immutable versions of an agent, newest first. Each entry is a point-in-time snapshot of the agent's configuration.

Requires configuration access on the agent — callers limited to using the agent (no configuration access) get a `403`.




## OpenAPI

````yaml get /agents/{agent_id}/versions
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/versions:
    get:
      tags:
        - Agents
      summary: List agent versions
      description: >
        List the immutable versions of an agent, newest first. Each entry is a
        point-in-time snapshot of the agent's configuration.


        Requires configuration access on the agent — callers limited to using
        the agent (no configuration access) get a `403`.
      operationId: listAgentVersions
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent whose versions to list. Also accepts the reserved
            aliases `gumball` and `analytics`.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: Number of versions to return per page. Clamped to 1–100.
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: Opaque pagination cursor returned by a prior call as `next_cursor`.
      responses:
        '200':
          description: Versions of the agent, newest first.
          content:
            application/json:
              schema:
                type: object
                properties:
                  versions:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: string
                          description: >-
                            Unique version identifier. Pass this as `version_id`
                            to retrieve the full version.
                          example: gv_a1b2c3d4
                        agent_id:
                          type: string
                          example: abc123DEFghiJKL
                        major_version:
                          type: integer
                          description: Incrementing version number for the agent.
                          example: 3
                        name:
                          type: string
                          description: The agent's name as of this version.
                          example: Sales research agent
                        is_deployed:
                          type: boolean
                          nullable: true
                          description: >-
                            `true` for the version currently deployed; `null`
                            otherwise.
                          example: true
                        created_at:
                          type: string
                          format: date-time
                          nullable: true
                          example: '2026-05-16T09:10:00Z'
                        creator:
                          type: object
                          nullable: true
                          description: >-
                            The user who created this version. `null` when
                            unknown.
                          properties:
                            id:
                              type: string
                              nullable: true
                              example: user_8c2a1b
                            first_name:
                              type: string
                              nullable: true
                              example: Ada
                            last_name:
                              type: string
                              nullable: true
                              example: Lovelace
                            email:
                              type: string
                              nullable: true
                              example: ada@example.com
                            profile_picture:
                              type: string
                              nullable: true
                              example: https://example.com/avatars/ada.png
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Cursor to pass as `cursor` on the next request. `null`
                      when there are no more results.
              examples:
                multiple:
                  summary: Multiple versions
                  value:
                    versions:
                      - id: gv_a1b2c3d4
                        agent_id: abc123DEFghiJKL
                        major_version: 3
                        name: Sales research agent
                        is_deployed: true
                        created_at: '2026-05-16T09:10:00Z'
                        creator:
                          id: user_8c2a1b
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                      - id: gv_e5f6g7h8
                        agent_id: abc123DEFghiJKL
                        major_version: 2
                        name: Sales research agent
                        is_deployed: null
                        created_at: '2026-05-02T09:11:00Z'
                        creator:
                          id: user_8c2a1b
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                    next_cursor: eyJjcmVhdGVkX3RzIjoiMjAyNi0wNS0wMlQwOToxMTowMFoifQ==
                empty:
                  summary: No versions
                  value:
                    versions: []
                    next_cursor: null
        '400':
          description: Bad request — `page_size` is not an integer.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have configuration access on the
            agent.
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
            curl
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/versions?page_size=20'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.agents.list_versions("abc123DEFghiJKL")
            for version in response.versions:
                print(version.id, version.major_version, version.is_deployed)
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