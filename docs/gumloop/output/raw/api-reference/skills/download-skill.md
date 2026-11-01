> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Download skill

> Generate a signed URL to download a skill's contents as a `.skill` archive (ZIP). When `version_id` is provided, returns that exact version; otherwise returns the current draft.



## OpenAPI

````yaml get /skills/{skill_id}/download
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /skills/{skill_id}/download:
    get:
      tags:
        - Skills
      summary: Download skill
      description: >-
        Generate a signed URL to download a skill's contents as a `.skill`
        archive (ZIP). When `version_id` is provided, returns that exact
        version; otherwise returns the current draft.
      operationId: downloadSkill
      parameters:
        - in: path
          name: skill_id
          required: true
          schema:
            type: string
          description: ID of the skill to download.
        - in: query
          name: version_id
          required: false
          schema:
            type: string
          description: >-
            Specific version to download. When omitted, the current draft is
            returned.
      responses:
        '200':
          description: Signed download URL for the requested skill archive.
          content:
            application/json:
              schema:
                type: object
                properties:
                  download_url:
                    type: string
                    description: >-
                      Short-lived signed URL pointing at the `.skill` archive in
                      object storage.
                  filename:
                    type: string
                    description: Suggested filename for the archive (`<skill name>.skill`).
                  media_type:
                    type: string
                    enum:
                      - application/zip
                    description: Media type of the archive at `download_url`.
                  size:
                    type: integer
                    nullable: true
                    description: Size of the archive in bytes.
                  id:
                    type: string
                    description: ID of the skill the archive was generated for.
                  version_id:
                    type: string
                    nullable: true
                    description: >-
                      Version that was archived, or `null` if the skill has no
                      current version.
                  major_version:
                    type: integer
                    nullable: true
                    description: >-
                      Major version number of the archived version, or `null` if
                      unavailable.
              examples:
                download:
                  summary: Signed download URL
                  value:
                    download_url: >-
                      https://storage.googleapis.com/gumloop-skills/skill_x7y8z9/Lead%20enrichment.skill?X-Goog-Signature=...
                    filename: Lead enrichment.skill
                    media_type: application/zip
                    size: 18432
                    id: skill_x7y8z9
                    version_id: sv_a1b2c3d4
                    major_version: 3
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller cannot read this skill.
        '404':
          description: Skill, version, or skill files not found.
        '502':
          description: Failed to generate a signed download URL from object storage.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl 'https://api.gumloop.com/api/v1/skills/skill_x7y8z9/download' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.skills.download("skill_x7y8z9")
            print(response.download_url)
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