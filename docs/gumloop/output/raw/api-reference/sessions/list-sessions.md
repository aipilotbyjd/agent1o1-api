> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List sessions

> List sessions for an agent with cursor-based pagination, optional filtering, and search.




## OpenAPI

````yaml get /agents/{agent_id}/sessions
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/sessions:
    get:
      tags:
        - Sessions
      summary: List sessions
      description: >
        List sessions for an agent with cursor-based pagination, optional
        filtering, and search.
      operationId: listSessions
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent whose sessions to list. Also accepts the reserved
            aliases `gumball` and `analytics`.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: >-
            Number of sessions to return per page. Defaults to `20`, maximum
            `100`.
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: >-
            Cursor for the next page of results. Use the `next_cursor` value
            from a previous response.
        - in: query
          name: search
          required: false
          schema:
            type: string
          description: >-
            Free-text search query to filter sessions by name or content. Also
            accepted as `search_query`.
        - in: query
          name: sort_order
          required: false
          schema:
            type: string
          description: Sort order for the results (e.g. `newest` or `oldest`).
        - in: query
          name: type
          required: false
          schema:
            type: string
          description: Filter sessions by type (e.g. `api`, `web`, `slack`).
        - in: query
          name: state
          required: false
          schema:
            type: string
            enum:
              - processing
              - completed
              - failed
              - queued
              - idle
          description: Filter sessions by state.
        - in: query
          name: creator_user_id
          required: false
          schema:
            type: string
          description: Filter sessions by the user who created them.
        - in: query
          name: trigger_id
          required: false
          schema:
            type: string
          description: Filter sessions by the trigger that initiated them.
      responses:
        '200':
          description: A paginated list of sessions.
          content:
            application/json:
              schema:
                type: object
                properties:
                  sessions:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: string
                          example: sess_xYz789AbCd
                        agent_id:
                          type: string
                          example: abc123DEFghiJKL
                        name:
                          type: string
                          nullable: true
                          example: Research task
                        type:
                          type: string
                          nullable: true
                          example: api
                        messages:
                          type: array
                          items:
                            type: object
                          description: >-
                            Messages are not included in list responses. Use the
                            retrieve session endpoint to get the full message
                            history.
                        created_at:
                          type: string
                          format: date-time
                          nullable: true
                          example: '2026-05-15T14:32:00Z'
                        state:
                          type: string
                          nullable: true
                          enum:
                            - processing
                            - completed
                            - failed
                            - queued
                            - idle
                            - approval_required
                          example: completed
                        agent_name:
                          type: string
                          nullable: true
                          example: Sales research agent
                        creator:
                          type: object
                          nullable: true
                          properties:
                            id:
                              type: string
                              nullable: true
                            first_name:
                              type: string
                              nullable: true
                            last_name:
                              type: string
                              nullable: true
                            email:
                              type: string
                              nullable: true
                            profile_picture:
                              type: string
                              nullable: true
                        usage:
                          type: object
                          description: >-
                            Per-session usage totals. Credit and token counts
                            accumulate as the agent runs and are `null` until
                            the first run records usage.
                          properties:
                            credit_cost:
                              type: number
                              nullable: true
                              description: >-
                                Total credits consumed by the session, including
                                tool and flow (workflow) credits.
                              example: 12.5
                            tool_credit_cost:
                              type: number
                              nullable: true
                              description: Credits consumed by tool calls.
                              example: 4
                            flow_credit_cost:
                              type: number
                              nullable: true
                              description: >-
                                Credits consumed by workflow (flow) runs invoked
                                by the agent.
                              example: 1.5
                            input_tokens:
                              type: integer
                              nullable: true
                              description: Total input tokens across the session.
                              example: 18432
                            output_tokens:
                              type: integer
                              nullable: true
                              description: Total output tokens across the session.
                              example: 2043
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Cursor to pass as the `cursor` query parameter to retrieve
                      the next page. `null` when there are no more results.
                    example: >-
                      eyJzb3J0X3ZhbHVlIjoiMjAyNi0wNS0xNVQxNDozMjowMFoiLCJpbnRlcmFjdGlvbl9pZCI6InNlc3NfeFl6Nzg5QWJDZCIsInNvcnRfYnkiOiJuZXdlc3QifQ==
              examples:
                paginated:
                  summary: First page of sessions
                  value:
                    sessions:
                      - id: sess_xYz789AbCd
                        agent_id: abc123DEFghiJKL
                        name: Research task
                        type: api
                        messages: []
                        created_at: '2026-05-15T14:32:00Z'
                        state: completed
                        agent_name: Sales research agent
                        creator:
                          id: user_2b9d71f0
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                        usage:
                          credit_cost: 12.5
                          tool_credit_cost: 4
                          flow_credit_cost: 1.5
                          input_tokens: 18432
                          output_tokens: 2043
                      - id: sess_AbC123dEfG
                        agent_id: abc123DEFghiJKL
                        name: Follow-up call
                        type: web
                        messages: []
                        created_at: '2026-05-14T09:15:00Z'
                        state: completed
                        agent_name: Sales research agent
                        creator:
                          id: user_2b9d71f0
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                    next_cursor: eyJzb3J0X3ZhbHVlIjoiMjAyNi0wNS0xNFQwOToxNTowMFoifQ==
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have read access on the agent.
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
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/sessions?page_size=20'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.sessions.list("abc123DEFghiJKL")
            for session in response.sessions:
                print(session.id, session.state)
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