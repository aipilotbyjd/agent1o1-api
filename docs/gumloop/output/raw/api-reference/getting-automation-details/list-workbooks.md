> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List workbooks and their saved flows



## OpenAPI

````yaml get /list_workbooks
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /list_workbooks:
    get:
      tags:
        - Data Access
      summary: List workbooks and their saved flows
      operationId: listWorkbooks
      parameters:
        - in: query
          name: user_id
          required: false
          schema:
            type: string
          description: >-
            The user ID for which to list workbooks. Required if project_id is
            not provided.
        - in: query
          name: project_id
          required: false
          schema:
            type: string
          description: >-
            The project ID for which to list workbooks. Required if user_id is
            not provided.
      responses:
        '200':
          description: Successful retrieval of workbooks and their saved items
          content:
            application/json:
              schema:
                type: object
                properties:
                  workbooks:
                    type: array
                    items:
                      type: object
                      properties:
                        workbook_id:
                          type: string
                          description: The id of the workbook.
                        name:
                          type: string
                          description: The name of the workbook.
                        description:
                          type: string
                          description: The description of the workbook.
                        created_ts:
                          type: string
                          format: date-time
                          description: Timestamp for when the workbook was created.
                        saved_items:
                          type: array
                          items:
                            type: object
                            properties:
                              saved_item_id:
                                type: string
                                description: The id of the saved flow.
                              name:
                                type: string
                                description: The name of the saved flow.
                              description:
                                type: string
                                description: The description of the saved flow.
                              created_ts:
                                type: string
                                format: date-time
                                description: Timestamp for when the saved flow was created.
                    description: List of workbooks and their associated saved flows
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