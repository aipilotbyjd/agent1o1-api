> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Create agent

> Create a new agent. The authenticated caller must have permission to create agents on the target team.



## OpenAPI

````yaml post /agents
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents:
    post:
      tags:
        - Agents
      summary: Create agent
      description: >-
        Create a new agent. The authenticated caller must have permission to
        create agents on the target team.
      operationId: createAgent
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
                - model_name
              properties:
                name:
                  type: string
                  description: Display name for the agent.
                  example: Sales research agent
                model_name:
                  type: string
                  description: >-
                    ID of the LLM the agent runs on. Use `GET /models` to
                    discover valid values.
                  example: anthropic/claude-sonnet-4
                description:
                  type: string
                  nullable: true
                  example: Researches accounts and drafts outreach
                system_prompt:
                  type: string
                  nullable: true
                  example: You are a B2B sales research assistant.
                tools:
                  type: array
                  description: >-
                    Tools the agent can call. Each tool is an object whose shape
                    depends on the tool type.
                  items:
                    type: object
                  default: []
                resources:
                  type: array
                  description: Resources attached to the agent.
                  items:
                    type: object
                  default: []
                skill_ids:
                  type: array
                  nullable: true
                  description: >
                    IDs of skills to attach to the agent. Attachment happens
                    inside the create transaction, so an invalid ID fails the
                    whole request (no orphaned agent). Omit to attach none. The
                    caller must hold `INVOKE` on each skill. After creation,
                    manage skills with `PATCH /agents/{agent_id}/skills`.
                  items:
                    type: string
                  example:
                    - skill_2b9c
                    - skill_7f1a
                metadata:
                  type: object
                  nullable: true
                  description: Arbitrary key/value metadata stored on the agent.
                folder_id:
                  type: string
                  nullable: true
                  description: ID of the folder to place the agent in.
                  example: folder_91ab
                is_active:
                  type: boolean
                  description: >
                    Whether the agent is active. Defaults to `true`.


                    Setting this to `false` retires the agent: it disappears
                    from `GET /agents`, and `GET`/`PATCH /agents/{agent_id}`
                    return `404`, so it cannot be reactivated through the API.
                    This is not a pause switch — to stop an agent from running
                    while keeping it reachable, disable its triggers instead.
                  default: true
                agent_id:
                  type: string
                  nullable: true
                  description: >-
                    Optional caller-supplied agent ID. When omitted, the server
                    generates one.
                team_id:
                  type: string
                  nullable: true
                  description: >-
                    ID of the team to create the agent under. When omitted, the
                    agent is owned by the authenticated user.
                  example: team_4f8c92ab
            examples:
              minimal:
                summary: Minimal create
                value:
                  name: Sales research agent
                  model_name: anthropic/claude-sonnet-4
              full:
                summary: Full create
                value:
                  name: Sales research agent
                  model_name: anthropic/claude-sonnet-4
                  description: Researches accounts and drafts outreach
                  system_prompt: You are a B2B sales research assistant.
                  tools: []
                  resources: []
                  skill_ids:
                    - skill_2b9c
                    - skill_7f1a
                  metadata: {}
                  folder_id: folder_91ab
                  is_active: true
                  team_id: team_4f8c92ab
      responses:
        '201':
          description: Agent created.
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
                        description: Unique agent identifier.
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
                        description: ID of the team that owns the agent.
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
                        description: ID of the LLM the agent runs on.
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
                        description: ISO 8601 timestamp of when the agent was created.
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
                created:
                  summary: Newly created agent
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
        '400':
          description: Invalid request body.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have permission to create agents on
            the requested team.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl -X POST 'https://api.gumloop.com/api/v1/agents' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "name": "Sales research agent",
                "model_name": "anthropic/claude-sonnet-4",
                "description": "Researches accounts and drafts outreach",
                "system_prompt": "You are a B2B sales research assistant.",
                "team_id": "team_4f8c92ab"
              }'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.agents.create(
                name="Sales research agent",
                model_name="anthropic/claude-sonnet-4",
                description="Researches accounts and drafts outreach",
                system_prompt="You are a B2B sales research assistant.",
                team_id="team_4f8c92ab",
            )
            print(response.agent.id)
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