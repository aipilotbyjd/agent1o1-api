> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List skills

> List skills the caller has access to. Filter by team, search by name, or narrow to a specific creator, related MCP server, or agent.



## OpenAPI

````yaml get /skills
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /skills:
    get:
      tags:
        - Skills
      summary: List skills
      description: >-
        List skills the caller has access to. Filter by team, search by name, or
        narrow to a specific creator, related MCP server, or agent.
      operationId: listSkills
      parameters:
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: >-
            Scope the listing to a single team. When omitted, returns skills
            owned by the authenticated user.
        - in: query
          name: search_query
          required: false
          schema:
            type: string
          description: Case-insensitive substring match against the skill name.
        - in: query
          name: sort_order
          required: false
          schema:
            type: string
            enum:
              - newest
              - popular
              - most_used
            default: newest
          description: Sort order for the returned skills.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: Number of skills per page. Clamped between 1 and 100.
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: >-
            Opaque pagination cursor returned in `next_cursor` from a prior
            page.
        - in: query
          name: creator_user_id
          required: false
          schema:
            type: string
          description: Filter to skills created by this user ID.
        - in: query
          name: related_server_id
          required: false
          schema:
            type: string
          description: >-
            Filter to skills that reference this MCP server ID in their
            metadata.
        - in: query
          name: agent_id
          required: false
          schema:
            type: string
          description: Filter to skills attached to this agent.
        - in: query
          name: unused
          required: false
          schema:
            type: string
          description: When set, filters to skills that have not been used.
      responses:
        '200':
          description: Skills matching the provided filters.
          content:
            application/json:
              schema:
                type: object
                properties:
                  skills:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: string
                          description: Unique skill identifier.
                          example: skill_x7y8z9
                        name:
                          type: string
                          example: Lead enrichment
                        description:
                          type: string
                          example: Enriches inbound leads with firmographics.
                        team_id:
                          type: string
                          description: ID of the team that owns the skill.
                          example: team_4f8c92ab
                        created_at:
                          type: string
                          format: date-time
                          nullable: true
                          description: ISO 8601 timestamp of when the skill was created.
                          example: '2026-05-15T14:32:00Z'
                        updated_at:
                          type: string
                          format: date-time
                          nullable: true
                          description: >-
                            ISO 8601 timestamp of when the skill was last
                            updated.
                          example: '2026-05-16T09:10:00Z'
                        metadata:
                          type: object
                          description: >-
                            User-defined metadata. May include
                            `related_server_ids` (array of MCP server IDs the
                            skill references).
                          default: {}
                        usage_count:
                          type: integer
                          nullable: true
                          example: 42
                        view_count:
                          type: integer
                          nullable: true
                          example: 117
                        last_used_at:
                          type: string
                          format: date-time
                          nullable: true
                          example: '2026-05-18T22:01:00Z'
                        version_id:
                          type: string
                          nullable: true
                          description: >-
                            ID of the version exposed by this payload (the
                            current draft, by default).
                          example: sv_a1b2c3d4
                        major_version:
                          type: integer
                          nullable: true
                          example: 3
                        is_deployed:
                          type: boolean
                          nullable: true
                          example: true
                        version_created_at:
                          type: string
                          format: date-time
                          nullable: true
                          example: '2026-05-16T09:10:00Z'
                        creator:
                          type: object
                          nullable: true
                          description: >-
                            Creator of the skill. `null` when no creator is
                            recorded.
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
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Opaque cursor for the next page, or `null` when there are
                      no more results.
                  total_count:
                    type: integer
                    nullable: true
                    description: >-
                      Total number of skills matching the filters across all
                      pages.
              examples:
                multiple:
                  summary: Multiple skills
                  value:
                    skills:
                      - id: skill_x7y8z9
                        name: Lead enrichment
                        description: Enriches inbound leads with firmographics.
                        team_id: team_4f8c92ab
                        created_at: '2026-05-15T14:32:00Z'
                        updated_at: '2026-05-16T09:10:00Z'
                        metadata:
                          related_server_ids:
                            - apollo
                        usage_count: 42
                        view_count: 117
                        last_used_at: '2026-05-18T22:01:00Z'
                        version_id: sv_a1b2c3d4
                        major_version: 3
                        is_deployed: true
                        version_created_at: '2026-05-16T09:10:00Z'
                        creator:
                          id: user_2b9d71f0
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                      - id: skill_p4q5r6
                        name: Ticket triage
                        description: Classifies and routes support tickets.
                        team_id: team_4f8c92ab
                        created_at: '2026-04-02T09:11:00Z'
                        updated_at: '2026-04-02T09:11:00Z'
                        metadata: {}
                        usage_count: 0
                        view_count: 5
                        last_used_at: null
                        version_id: sv_e5f6g7h8
                        major_version: 1
                        is_deployed: false
                        version_created_at: '2026-04-02T09:11:00Z'
                        creator:
                          id: user_2b9d71f0
                          first_name: Ada
                          last_name: Lovelace
                          email: ada@example.com
                          profile_picture: null
                    next_cursor: null
                    total_count: 2
                empty:
                  summary: No matches
                  value:
                    skills: []
                    next_cursor: null
                    total_count: 0
        '400':
          description: >-
            Bad request — invalid query parameter (for example, non-integer
            `page_size`).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have skill access on the requested
            team.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl 'https://api.gumloop.com/api/v1/skills?team_id=YOUR_TEAM_ID' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.skills.list(team_id="YOUR_TEAM_ID")
            for skill in response.skills:
                print(skill.id, skill.name)
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