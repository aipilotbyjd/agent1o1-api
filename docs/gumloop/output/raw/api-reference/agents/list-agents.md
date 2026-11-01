> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List agents

> List agents the caller has access to. Filter by team, search by name, or narrow to agents that use a specific tool or trigger. Results can be sorted with `sort_order` and paginated by sending `page_size` and/or `cursor`.



## OpenAPI

````yaml get /agents
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents:
    get:
      tags:
        - Agents
      summary: List agents
      description: >-
        List agents the caller has access to. Filter by team, search by name, or
        narrow to agents that use a specific tool or trigger. Results can be
        sorted with `sort_order` and paginated by sending `page_size` and/or
        `cursor`.
      operationId: listAgents
      parameters:
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: >-
            Scope the listing to a single team. When omitted, returns agents
            owned by the authenticated user.
        - in: query
          name: search
          required: false
          schema:
            type: string
          description: Case-insensitive substring match against the agent name.
        - in: query
          name: creator
          required: false
          schema:
            type: string
          description: Filter to agents created by this user ID.
        - in: query
          name: has_triggers
          required: false
          schema:
            type: boolean
          description: >-
            When `true`, only returns agents that have at least one active
            trigger configured.
        - in: query
          name: tool
          required: false
          schema:
            type: string
          description: Filter to agents that use the specified MCP server as a tool.
        - in: query
          name: flow
          required: false
          schema:
            type: string
          description: Filter to agents that use the specified saved flow as a tool.
        - in: query
          name: sort_order
          required: false
          schema:
            type: string
            enum:
              - newest
              - oldest
              - name_asc
              - name_desc
            default: newest
          description: Sort order for the listing. Defaults to newest first.
        - in: query
          name: include_last_used
          required: false
          schema:
            type: boolean
          description: >-
            When `true`, populates `last_used_at` on each agent with the
            timestamp of its most recent session.
        - in: query
          name: include_last_updated
          required: false
          schema:
            type: boolean
          description: >-
            When `true`, populates `last_updated_at` on each agent with the
            timestamp of its most recent configuration change.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: >-
            Number of agents per page. Sending `page_size` or `cursor` opts into
            cursor pagination; requests that send neither return the full list.
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: >-
            Opaque cursor from a previous response's `next_cursor`. Pass it to
            fetch the next page.
      responses:
        '200':
          description: Agents matching the provided filters.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agents:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: string
                          description: Unique agent identifier.
                          example: abc123DEFghiJKL
                        name:
                          type: string
                          example: Sales research agent
                        description:
                          type: string
                          example: Researches accounts and drafts outreach
                        team_id:
                          type: string
                          description: ID of the team that owns the agent.
                          example: team_4f8c92ab
                        is_active:
                          type: boolean
                          example: true
                        model_name:
                          type: string
                          description: ID of the LLM the agent runs on.
                          example: anthropic/claude-sonnet-4
                        system_prompt:
                          type: string
                          example: You are a B2B sales research assistant.
                        tools:
                          type: array
                          description: >-
                            Tools the agent can call. Secret references are
                            stripped before being returned.
                          items:
                            type: object
                        resources:
                          type: array
                          items:
                            type: object
                        metadata:
                          type: object
                        folder_id:
                          type: string
                          example: folder_91ab
                        created_at:
                          type: string
                          format: date-time
                          description: ISO 8601 timestamp of when the agent was created.
                          example: '2026-05-15T14:32:00Z'
                        active_trigger_count:
                          type: integer
                          description: Number of active triggers on this agent.
                          example: 2
                        last_used_at:
                          type: string
                          format: date-time
                          nullable: true
                          description: >-
                            Timestamp of the agent's most recent session.
                            Populated only when `include_last_used=true`.
                          example: null
                        last_updated_at:
                          type: string
                          format: date-time
                          nullable: true
                          description: >-
                            Timestamp of the agent's most recent configuration
                            change. Populated only when
                            `include_last_updated=true`.
                          example: null
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Cursor for the next page. Present only on paginated
                      requests; `null` when there are no more results.
                    example: null
              examples:
                multiple:
                  summary: Multiple agents
                  value:
                    agents:
                      - id: abc123DEFghiJKL
                        name: Sales research agent
                        description: Researches accounts and drafts outreach
                        team_id: team_4f8c92ab
                        is_active: true
                        model_name: anthropic/claude-sonnet-4
                        system_prompt: You are a B2B sales research assistant.
                        tools: []
                        resources: []
                        metadata: {}
                        folder_id: null
                        created_at: '2026-05-15T14:32:00Z'
                        active_trigger_count: 2
                      - id: xYz789AbCdEfGhI
                        name: Support triage
                        description: Triages incoming support tickets
                        team_id: team_4f8c92ab
                        is_active: true
                        model_name: openai/gpt-5
                        system_prompt: null
                        tools: []
                        resources: []
                        metadata: {}
                        folder_id: null
                        created_at: '2026-04-02T09:11:00Z'
                        active_trigger_count: 0
                    next_cursor: null
                empty:
                  summary: No matches
                  value:
                    agents: []
                    next_cursor: null
        '400':
          description: >-
            Bad request — `page_size` outside 1–100 (`invalid_page_size`),
            unknown `sort_order` (`invalid_sort`), or a malformed `cursor`
            (`invalid_cursor`).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have agent access on the requested
            team.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/agents?team_id=YOUR_TEAM_ID&search=research'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            from gumloop import Gumloop


            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")


            response = client.agents.list(team_id="YOUR_TEAM_ID",
            search="research")

            for agent in response.agents:
                print(agent.id, agent.name)
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