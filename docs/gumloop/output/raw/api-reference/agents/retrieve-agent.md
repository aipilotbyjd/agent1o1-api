> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve agent

> Retrieve a single agent by ID.

In addition to regular agent IDs, `agent_id` accepts the reserved aliases `gumball` (your personal Gumball agent) and `analytics` (your analytics agent) on all agent-scoped endpoints. The alias resolves to your own copy of the platform agent, creating it on first use, and responses report the alias back as the agent's `id`.




## OpenAPI

````yaml get /agents/{agent_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}:
    get:
      tags:
        - Agents
      summary: Retrieve agent
      description: >
        Retrieve a single agent by ID.


        In addition to regular agent IDs, `agent_id` accepts the reserved
        aliases `gumball` (your personal Gumball agent) and `analytics` (your
        analytics agent) on all agent-scoped endpoints. The alias resolves to
        your own copy of the platform agent, creating it on first use, and
        responses report the alias back as the agent's `id`.
      operationId: retrieveAgent
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent to retrieve. Also accepts the reserved aliases
            `gumball` and `analytics`.
      responses:
        '200':
          description: The requested agent.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agent:
                    type: object
                    properties:
                      id:
                        type: string
                        example: abc123DEFghiJKL
                      name:
                        type: string
                        example: Sales research agent
                      description:
                        type: string
                        nullable: true
                        example: Researches accounts and drafts outreach
                      team_id:
                        type: string
                        example: team_4f8c92ab
                      is_active:
                        type: boolean
                        example: true
                      tools:
                        type: array
                        description: >-
                          Tools the agent can call. Secret references are
                          stripped and MCP server URLs are redacted before being
                          returned.
                        items:
                          type: object
                      metadata:
                        type: object
                      model_name:
                        type: string
                        nullable: true
                        example: anthropic/claude-sonnet-4
                      system_prompt:
                        type: string
                        nullable: true
                        example: You are a B2B sales research assistant.
                      resources:
                        type: array
                        items:
                          type: object
                      skill_ids:
                        type: array
                        nullable: true
                        description: >-
                          IDs of skills attached to the agent. `null` on
                          surfaces that don't include them (e.g. the list
                          endpoint); `[]` when none are attached. Manage with
                          `PATCH /agents/{agent_id}/skills`.
                        items:
                          type: string
                        example:
                          - skill_2b9c
                          - skill_7f1a
                      folder_id:
                        type: string
                        nullable: true
                        example: folder_91ab
                      type:
                        type: string
                        nullable: true
                        description: Internal agent type discriminator.
                      created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-15T14:32:00Z'
                      active_trigger_count:
                        type: integer
                        nullable: true
                        description: >-
                          Number of active triggers on this agent. Populated on
                          list responses; may be `null` here.
                      creator:
                        type: object
                        nullable: true
                        description: >-
                          User who created the agent. `null` when the creator is
                          not known.
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
              examples:
                single:
                  summary: Single agent
                  value:
                    agent:
                      id: abc123DEFghiJKL
                      name: Sales research agent
                      description: Researches accounts and drafts outreach
                      team_id: team_4f8c92ab
                      is_active: true
                      tools: []
                      metadata: {}
                      model_name: anthropic/claude-sonnet-4
                      system_prompt: You are a B2B sales research assistant.
                      resources: []
                      skill_ids:
                        - skill_2b9c
                        - skill_7f1a
                      folder_id: folder_91ab
                      type: null
                      created_at: '2026-05-15T14:32:00Z'
                      active_trigger_count: null
                      creator:
                        id: user_8c2a1b
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have read access to this agent.
        '404':
          description: Agent not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl 'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.agents.retrieve("abc123DEFghiJKL")
            print(response.agent.name)
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