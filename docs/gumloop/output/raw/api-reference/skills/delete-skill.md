> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Delete skill

> Permanently delete a skill. This is a soft-delete — the skill will no longer appear in listings or be usable by agents.



## OpenAPI

````yaml delete /skills/{skill_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /skills/{skill_id}:
    delete:
      tags:
        - Skills
      summary: Delete skill
      description: >-
        Permanently delete a skill. This is a soft-delete — the skill will no
        longer appear in listings or be usable by agents.
      operationId: deleteSkill
      parameters:
        - in: path
          name: skill_id
          required: true
          schema:
            type: string
          description: ID of the skill to delete.
      responses:
        '200':
          description: Skill deleted successfully.
          content:
            application/json:
              schema:
                type: object
                properties:
                  deleted:
                    type: boolean
                    description: Whether the skill was successfully deleted.
                    example: true
              examples:
                deleted:
                  summary: Skill deleted
                  value:
                    deleted: true
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller cannot delete this skill.
        '404':
          description: Skill not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X DELETE 'https://api.gumloop.com/api/v1/skills/skill_x7y8z9'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.skills.delete("skill_x7y8z9")
            print(response.deleted)
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