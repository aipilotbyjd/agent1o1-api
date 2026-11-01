> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List saved flows



## OpenAPI

````yaml get /list_saved_items
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /list_saved_items:
    get:
      tags:
        - Data Access
      summary: List saved flows
      operationId: listSavedAutomations
      parameters:
        - in: query
          name: user_id
          required: false
          schema:
            type: string
          description: >-
            The user ID to for which to list items. Required if project_id is
            not provided.
        - in: query
          name: project_id
          required: false
          schema:
            type: string
          description: >-
            The project ID for which to list items. Required if user_id is not
            provided.
      responses:
        '200':
          description: Successful retrieval of saved items
          content:
            application/json:
              schema:
                type: object
                properties:
                  saved_items:
                    type: array
                    items:
                      type: object
                      properties:
                        saved_item_id:
                          type: string
                          description: The id for the saved flow.
                        name:
                          type: string
                          description: The name of the saved flow.
                        description:
                          type: string
                          description: The description of the saved flow.
                        created_ts:
                          type: string
                          format: date-time
                          description: Timestamp for when the flow was started.
                    description: List of saved flows
        '400':
          description: Bad request (missing parameters)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (API key does not match)
        '500':
          description: Internal server error
      security:
        - bearerAuth: []
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