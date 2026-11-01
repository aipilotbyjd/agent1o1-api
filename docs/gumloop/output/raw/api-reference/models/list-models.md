> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List models

> List the LLMs and preset model chains available to the caller, grouped for display in a model picker.



## OpenAPI

````yaml get /models
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /models:
    get:
      tags:
        - Models
      summary: List models
      description: >-
        List the LLMs and preset model chains available to the caller, grouped
        for display in a model picker.
      operationId: listModels
      parameters:
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: >-
            Scope model availability to a specific team. When omitted, uses the
            authenticated user's default organization.
      responses:
        '200':
          description: Model groups available to the caller.
          content:
            application/json:
              schema:
                type: object
                properties:
                  model_groups:
                    type: array
                    description: >-
                      Groups of available models. The shape of each group is
                      open and may evolve; treat it as a passthrough payload for
                      the model picker.
                    items:
                      type: object
              examples:
                groups:
                  summary: Model groups
                  value:
                    model_groups:
                      - {}
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
          source: |
            curl 'https://api.gumloop.com/api/v1/models?team_id=YOUR_TEAM_ID' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.models.list()
            for group in response.model_groups:
                print(group)
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