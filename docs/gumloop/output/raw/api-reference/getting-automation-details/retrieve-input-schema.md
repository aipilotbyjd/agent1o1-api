> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve input schema



## OpenAPI

````yaml get /get_inputs
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /get_inputs:
    get:
      tags:
        - Data Access
      summary: Retrieve input schema
      operationId: getInputs
      parameters:
        - in: query
          name: saved_item_id
          required: true
          schema:
            type: string
          description: The ID of the saved item for which to retrieve input schemas.
        - in: query
          name: user_id
          required: false
          schema:
            type: string
          description: >-
            User ID that created the flow. Required if project_id is not
            provided.
        - in: query
          name: project_id
          required: false
          schema:
            type: string
          description: >-
            Project ID that the flow is under. Required if user_id is not
            provided.
      responses:
        '200':
          description: Successful retrieval of item input schemas
          content:
            application/json:
              schema:
                type: object
                properties:
                  inputs:
                    type: array
                    items:
                      type: object
                      properties:
                        data_type:
                          type: string
                          enum:
                            - string
                            - file
                          description: >-
                            The type of the input, either a 'string' or a
                            'file'.
                        description:
                          type: string
                          nullable: true
                          description: >-
                            A description of the input. Can be null if no
                            desecription is given.
                        name:
                          type: string
                          description: The name of the input.
                    description: List of inputs for the saved item.
        '400':
          description: Bad request (missing parameters)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (API key does not match)
        '404':
          description: Saved item not found
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