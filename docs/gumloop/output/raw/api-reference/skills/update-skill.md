> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Update skill

> Replace a skill's files with a new upload. Reparses `SKILL.md` to update the skill's `name`, `description`, and `metadata`, and creates a new version. Maximum upload size is 10 MB.



## OpenAPI

````yaml patch /skills/{skill_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /skills/{skill_id}:
    patch:
      tags:
        - Skills
      summary: Update skill
      description: >-
        Replace a skill's files with a new upload. Reparses `SKILL.md` to update
        the skill's `name`, `description`, and `metadata`, and creates a new
        version. Maximum upload size is 10 MB.
      operationId: updateSkill
      parameters:
        - in: path
          name: skill_id
          required: true
          schema:
            type: string
          description: ID of the skill to update.
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
                    One or more file parts containing the new skill contents.
                    Same shape as the create endpoint — must include a
                    `SKILL.md`. Total upload must not exceed 10 MB.
                  items:
                    type: string
                    format: binary
              required:
                - files
      responses:
        '200':
          description: >-
            Skill updated. The returned `version_id` and `major_version` reflect
            the new version that was created.
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
                        example: >-
                          Enriches inbound leads with firmographics and intent
                          signals.
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
                        description: ISO 8601 timestamp of when the skill was last updated.
                        example: '2026-05-19T11:42:00Z'
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
                          ID of the version exposed by this payload (the newly
                          created version).
                        example: sv_b9c8d7e6
                      major_version:
                        type: integer
                        nullable: true
                        example: 4
                      is_deployed:
                        type: boolean
                        nullable: true
                        example: false
                      version_created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-19T11:42:00Z'
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
                updated:
                  summary: Skill after update
                  value:
                    skill:
                      id: skill_x7y8z9
                      name: Lead enrichment
                      description: >-
                        Enriches inbound leads with firmographics and intent
                        signals.
                      team_id: team_4f8c92ab
                      created_at: '2026-05-15T14:32:00Z'
                      updated_at: '2026-05-19T11:42:00Z'
                      metadata:
                        related_server_ids:
                          - apollo
                      usage_count: 42
                      view_count: 117
                      last_used_at: '2026-05-18T22:01:00Z'
                      version_id: sv_b9c8d7e6
                      major_version: 4
                      is_deployed: false
                      version_created_at: '2026-05-19T11:42:00Z'
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
          description: Forbidden — the caller cannot update this skill.
        '404':
          description: Skill not found.
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
            curl -X PATCH 'https://api.gumloop.com/api/v1/skills/skill_x7y8z9' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -F 'files=@./lead-enrichment.skill;type=application/zip'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            with open("lead-enrichment.skill", "rb") as fh:
                response = client.skills.update(
                    "skill_x7y8z9",
                    files=[("lead-enrichment.skill", fh.read(), "application/zip")],
                )
            print(response.skill.version_id)
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