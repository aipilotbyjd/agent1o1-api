> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Update agent

> Update an existing agent. Only fields included in the request body are changed; omitted fields are left untouched.

This endpoint edits document fields only. To attach or detach skills, use [`PATCH /agents/{agent_id}/skills`](/api-reference/agents/update-agent-skills); to manage MCP servers, use the [agent MCP server endpoints](/api-reference/agents/attach-agent-mcp-server).

**`is_active: false` is not a pause switch.** It retires the agent: the agent disappears from `GET /agents`, and both `GET` and `PATCH /agents/{agent_id}` return `404` afterwards, so you cannot set it back to `true` through the API. To stop an agent from running on its own while keeping it fully reachable, disable its [triggers](/core-concepts/agent_triggers#managing-active-triggers) instead.




## OpenAPI

````yaml patch /agents/{agent_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}:
    patch:
      tags:
        - Agents
      summary: Update agent
      description: >
        Update an existing agent. Only fields included in the request body are
        changed; omitted fields are left untouched.


        This endpoint edits document fields only. To attach or detach skills,
        use [`PATCH
        /agents/{agent_id}/skills`](/api-reference/agents/update-agent-skills);
        to manage MCP servers, use the [agent MCP server
        endpoints](/api-reference/agents/attach-agent-mcp-server).


        **`is_active: false` is not a pause switch.** It retires the agent: the
        agent disappears from `GET /agents`, and both `GET` and `PATCH
        /agents/{agent_id}` return `404` afterwards, so you cannot set it back
        to `true` through the API. To stop an agent from running on its own
        while keeping it fully reachable, disable its
        [triggers](/core-concepts/agent_triggers#managing-active-triggers)
        instead.
      operationId: updateAgent
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent to update. Also accepts the reserved aliases
            `gumball` and `analytics`.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  nullable: true
                model_name:
                  type: string
                  nullable: true
                  description: >-
                    ID of the LLM the agent runs on. Use `GET /models` to
                    discover valid values.
                  example: anthropic/claude-sonnet-4
                description:
                  type: string
                  nullable: true
                  example: Researches enterprise accounts and drafts outreach
                system_prompt:
                  type: string
                  nullable: true
                tools:
                  type: array
                  nullable: true
                  description: When provided, replaces the agent's tool list.
                  items:
                    type: object
                resources:
                  type: array
                  nullable: true
                  description: When provided, replaces the agent's resource list.
                  items:
                    type: object
                metadata:
                  type: object
                  nullable: true
                is_active:
                  type: boolean
                  nullable: true
                  description: >
                    Setting this to `false` retires the agent: it disappears
                    from `GET /agents`, and `GET`/`PATCH /agents/{agent_id}`
                    return `404`, so it cannot be reactivated through the API.
                    This is not a pause switch — to stop an agent from running
                    while keeping it reachable, disable its triggers instead.
                team_id:
                  type: string
                  nullable: true
                  description: >-
                    When provided, transfers ownership of the agent to this
                    team.
            examples:
              partial:
                summary: Partial update
                value:
                  description: Researches enterprise accounts and drafts outreach
                  is_active: true
      responses:
        '200':
          description: The updated agent.
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
                        example: Researches enterprise accounts and drafts outreach
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
                      resources:
                        type: array
                        items:
                          type: object
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
                updated:
                  summary: Updated agent
                  value:
                    agent:
                      id: abc123DEFghiJKL
                      name: Sales research agent
                      description: Researches enterprise accounts and drafts outreach
                      team_id: team_4f8c92ab
                      is_active: true
                      tools: []
                      metadata: {}
                      model_name: anthropic/claude-sonnet-4
                      system_prompt: You are a B2B sales research assistant.
                      resources: []
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
        '400':
          description: Invalid request body.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have permission to update this
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
            curl -X PATCH
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "description": "Researches enterprise accounts and drafts outreach",
                "is_active": true
              }'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.agents.update(
                "abc123DEFghiJKL",
                description="Researches enterprise accounts and drafts outreach",
                is_active=True,
            )
            print(response.agent.description)
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