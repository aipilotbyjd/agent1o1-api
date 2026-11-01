> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Create skill

> Upload a skill package and create a new skill. The package must include a `SKILL.md` with `name` and `description` frontmatter; uploads may be a single `.md` file (stored as `SKILL.md`), or a `.zip` / `.skill` archive containing `SKILL.md` at its root. The initial version is created automatically. Maximum upload size is 10 MB.



## OpenAPI

````yaml post /skills
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /skills:
    post:
      tags:
        - Skills
      summary: Create skill
      description: >-
        Upload a skill package and create a new skill. The package must include
        a `SKILL.md` with `name` and `description` frontmatter; uploads may be a
        single `.md` file (stored as `SKILL.md`), or a `.zip` / `.skill` archive
        containing `SKILL.md` at its root. The initial version is created
        automatically. Maximum upload size is 10 MB.
      operationId: createSkill
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              properties:
                files:
                  type: array
                  description: >-
                    One or more file parts. Accepts a single `.md` file (stored
                    as `SKILL.md`), a `.zip` / `.skill` archive containing
                    `SKILL.md` at its root, or loose files that together include
                    a `SKILL.md`. Total upload must not exceed 10 MB.
                  items:
                    type: string
                    format: binary
                team_id:
                  type: string
                  description: >-
                    Team that should own the skill. When omitted, the skill is
                    owned by the authenticated user.
              required:
                - files
      responses:
        '201':
          description: Skill created.
          content:
            application/json:
              schema:
                type: object
                properties:
                  skill:
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
                        example: '2026-05-19T09:00:00Z'
                      updated_at:
                        type: string
                        format: date-time
                        nullable: true
                        description: ISO 8601 timestamp of when the skill was last updated.
                        example: '2026-05-19T09:00:00Z'
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
                        example: 0
                      view_count:
                        type: integer
                        nullable: true
                        example: 0
                      last_used_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: null
                      version_id:
                        type: string
                        nullable: true
                        description: >-
                          ID of the version exposed by this payload (the current
                          draft, by default).
                        example: sv_a1b2c3d4
                      major_version:
                        type: integer
                        nullable: true
                        example: 1
                      is_deployed:
                        type: boolean
                        nullable: true
                        example: false
                      version_created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-19T09:00:00Z'
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
              examples:
                created:
                  summary: Newly created skill
                  value:
                    skill:
                      id: skill_x7y8z9
                      name: Lead enrichment
                      description: Enriches inbound leads with firmographics.
                      team_id: team_4f8c92ab
                      created_at: '2026-05-19T09:00:00Z'
                      updated_at: '2026-05-19T09:00:00Z'
                      metadata: {}
                      usage_count: 0
                      view_count: 0
                      last_used_at: null
                      version_id: sv_a1b2c3d4
                      major_version: 1
                      is_deployed: false
                      version_created_at: '2026-05-19T09:00:00Z'
                      creator:
                        id: user_2b9d71f0
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
        '400':
          description: >-
            Bad request — missing or invalid `SKILL.md`, unsafe archive, or
            malformed metadata.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller cannot create skills on the requested team.
        '413':
          description: Upload too large — request body exceeded 10 MB.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl -X POST 'https://api.gumloop.com/api/v1/skills' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -F 'team_id=YOUR_TEAM_ID' \
              -F 'files=@./lead-enrichment.skill;type=application/zip'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            with open("lead-enrichment.skill", "rb") as fh:
                response = client.skills.create(
                    files=[("lead-enrichment.skill", fh.read(), "application/zip")],
                    team_id="YOUR_TEAM_ID",
                )
            print(response.skill.id)
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