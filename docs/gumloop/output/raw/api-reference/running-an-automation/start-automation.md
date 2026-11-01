> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Start flow run

> This endpoint is used to trigger a flow run via API



## OpenAPI

````yaml post /start_pipeline
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /start_pipeline:
    post:
      tags:
        - Execution
      summary: Start flow run
      description: This endpoint is used to trigger a flow run via API
      operationId: startFlow
      requestBody:
        required: true
        description: >
          In addition to the documented top-level fields, you may include any
          additional inputs in the request body.
        content:
          application/json:
            schema:
              type: object
              properties:
                user_id:
                  type: string
                  description: The id for the user initiating the flow.
                project_id:
                  type: string
                  description: >-
                    (Optional) The id of the project within which the flow is
                    executed.
                saved_item_id:
                  type: string
                  description: The id for the saved flow.
              required:
                - saved_item_id
                - user_id
              additionalProperties:
                description: >
                  Arbitrary input value. The property key is matched by name to
                  an Input node in the flow, and the entire body will be
                  forwarded to any Webhook Input node.
            example:
              user_id: xxxxxxxxxxxxxx
              saved_item_id: xxxxxxxxxxxxxx
              recipient: recipient@gmail.com
              subject: Example of an Email Subject Line
              body: Example of the Text of an Email Body
              topic: weekly update
      responses:
        '200':
          description: Flow started successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  run_id:
                    type: string
                  saved_item_id:
                    type: string
                  workbook_id:
                    type: string
                  url:
                    type: string
        '400':
          description: Bad request (missing or conflicting parameters)
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