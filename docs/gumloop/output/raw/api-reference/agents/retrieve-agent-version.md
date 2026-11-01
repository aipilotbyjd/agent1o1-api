> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve agent version

> Retrieve one immutable agent version: its full configuration (`composition`) plus the structured `changes` relative to the version before it. Use it to export an agent's configuration or to audit what changed between versions.

`changes` is `null` for the first version of an agent, since there is no predecessor to diff against. Versions created before attachment snapshots were recorded report `composition.complete: false` (and `changes.attachment_changes_complete: false`); their `skill_ids` and `knowledge_sources` are `null` rather than empty. Skill file contents are never included, and this endpoint is read-only — it cannot restore or deploy a version.

Requires configuration access on the agent — callers limited to using the agent (no configuration access) get a `403`.




## OpenAPI

````yaml get /agents/{agent_id}/versions/{version_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/versions/{version_id}:
    get:
      tags:
        - Agents
      summary: Retrieve agent version
      description: >
        Retrieve one immutable agent version: its full configuration
        (`composition`) plus the structured `changes` relative to the version
        before it. Use it to export an agent's configuration or to audit what
        changed between versions.


        `changes` is `null` for the first version of an agent, since there is no
        predecessor to diff against. Versions created before attachment
        snapshots were recorded report `composition.complete: false` (and
        `changes.attachment_changes_complete: false`); their `skill_ids` and
        `knowledge_sources` are `null` rather than empty. Skill file contents
        are never included, and this endpoint is read-only — it cannot restore
        or deploy a version.


        Requires configuration access on the agent — callers limited to using
        the agent (no configuration access) get a `403`.
      operationId: retrieveAgentVersion
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent the version belongs to. Also accepts the reserved
            aliases `gumball` and `analytics`.
        - in: path
          name: version_id
          required: true
          schema:
            type: string
          description: >-
            ID of the version to retrieve, from `GET
            /agents/{agent_id}/versions`.
      responses:
        '200':
          description: The requested agent version.
          content:
            application/json:
              schema:
                type: object
                properties:
                  version:
                    type: object
                    properties:
                      id:
                        type: string
                        example: gv_a1b2c3d4
                      agent_id:
                        type: string
                        example: abc123DEFghiJKL
                      major_version:
                        type: integer
                        example: 3
                      name:
                        type: string
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
                      composition:
                        type: object
                        description: The agent's full configuration as of this version.
                        properties:
                          complete:
                            type: boolean
                            description: >-
                              `false` for legacy versions saved without an
                              attachment snapshot; `skill_ids` and
                              `knowledge_sources` are `null` in that case.
                            example: true
                          schema_version:
                            type: integer
                            nullable: true
                            description: >-
                              Schema version of the stored snapshot. `null` when
                              the snapshot is missing.
                            example: 1
                          name:
                            type: string
                            example: Sales research agent
                          description:
                            type: string
                            nullable: true
                            example: Researches accounts and drafts outreach
                          model_name:
                            type: string
                            example: anthropic/claude-sonnet-4
                          system_prompt:
                            type: string
                            nullable: true
                            example: You are a B2B sales research assistant.
                          tools:
                            type: array
                            description: >-
                              Tool configurations attached to the agent.
                              Credentials and secrets are stripped.
                            items:
                              type: object
                          resources:
                            type: array
                            items:
                              type: object
                          metadata:
                            type: object
                          skill_ids:
                            type: array
                            nullable: true
                            description: >-
                              IDs of skills attached at this version. `null`
                              when the snapshot is incomplete.
                            items:
                              type: string
                          knowledge_sources:
                            type: array
                            nullable: true
                            description: >-
                              Brain knowledge sources attached at this version.
                              `null` when the snapshot is incomplete.
                            items:
                              type: object
                              properties:
                                connector_id:
                                  type: string
                                config:
                                  type: object
                                  nullable: true
                  changes:
                    type: object
                    nullable: true
                    description: >-
                      Structured diff against the preceding version. `null` for
                      the agent's first version.
                    properties:
                      base_version_id:
                        type: string
                        description: ID of the version this diff is computed against.
                        example: gv_e5f6g7h8
                      attachment_changes_complete:
                        type: boolean
                        description: >-
                          `false` when either version lacks an attachment
                          snapshot, so skill and knowledge-source changes may be
                          incomplete.
                        example: true
                      field_changes:
                        type: array
                        description: >-
                          Changed agent fields. Long text fields (such as
                          `system_prompt`) report `text_hunks` instead of whole
                          values.
                        items:
                          type: object
                      tool_changes:
                        type: array
                        description: >-
                          Added, removed, reordered, or edited tools. Secrets
                          are stripped and custom MCP URLs are redacted.
                        items:
                          type: object
                      skill_changes:
                        type: array
                        items:
                          type: object
                          properties:
                            skill_id:
                              type: string
                            status:
                              type: string
                      knowledge_source_changes:
                        type: array
                        items:
                          type: object
              examples:
                withChanges:
                  summary: Version with changes
                  value:
                    version:
                      id: gv_a1b2c3d4
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
                      composition:
                        complete: true
                        schema_version: 1
                        name: Sales research agent
                        description: Researches accounts and drafts outreach
                        model_name: anthropic/claude-sonnet-4
                        system_prompt: You are a B2B sales research assistant.
                        tools: []
                        resources: []
                        metadata: {}
                        skill_ids:
                          - skill_2b9c
                        knowledge_sources: []
                    changes:
                      base_version_id: gv_e5f6g7h8
                      attachment_changes_complete: true
                      field_changes:
                        - field: system_prompt
                          status: modified
                          text_hunks:
                            - old_start: 0
                              old_end: 1
                              new_start: 0
                              new_end: 1
                              old_text: You are a sales assistant.
                              new_text: You are a B2B sales research assistant.
                      tool_changes: []
                      skill_changes:
                        - skill_id: skill_2b9c
                          status: added
                      knowledge_source_changes: []
                firstVersion:
                  summary: First version (no diff)
                  value:
                    version:
                      id: gv_z9y8x7w6
                      agent_id: abc123DEFghiJKL
                      major_version: 1
                      name: Sales research agent
                      is_deployed: null
                      created_at: '2026-04-02T09:11:00Z'
                      creator:
                        id: user_8c2a1b
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
                      composition:
                        complete: true
                        schema_version: 1
                        name: Sales research agent
                        description: null
                        model_name: anthropic/claude-sonnet-4
                        system_prompt: You are a sales assistant.
                        tools: []
                        resources: []
                        metadata: {}
                        skill_ids: []
                        knowledge_sources: []
                    changes: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have configuration access on the
            agent.
        '404':
          description: Agent or version not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/versions/gv_a1b2c3d4'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            from gumloop import Gumloop


            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")


            response = client.agents.get_version("abc123DEFghiJKL",
            "gv_a1b2c3d4")

            print(response.version.composition.system_prompt)
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