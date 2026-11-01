> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve automation run history

> This endpoint retrieves the run history for automations, either by workbook or saved item. Returns the 10 most recent runs.



## OpenAPI

````yaml get /get_plrun_saved_item_map
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /get_plrun_saved_item_map:
    get:
      tags:
        - Data Access
      summary: Retrieve automation run history
      description: >-
        This endpoint retrieves the run history for automations, either by
        workbook or saved item. Returns the 10 most recent runs.
      operationId: getAutomationRunHistory
      parameters:
        - in: query
          name: workbook_id
          required: false
          schema:
            type: string
          description: >-
            The ID of the workbook to retrieve run history for. Required if
            saved_item_id is not provided.
        - in: query
          name: saved_item_id
          required: false
          schema:
            type: string
          description: >-
            The ID of the saved item to retrieve run history for. Required if
            workbook_id is not provided.
        - in: query
          name: user_id
          required: false
          schema:
            type: string
          description: The user ID. Required if project_id is not provided.
        - in: query
          name: project_id
          required: false
          schema:
            type: string
          description: The project ID. Required if user_id is not provided.
      responses:
        '200':
          description: Successful retrieval of automation run history
          content:
            application/json:
              schema:
                type: object
                description: >-
                  A map of saved item IDs to their recent run history, with up
                  to 10 runs per saved item
        '400':
          description: Bad request (missing workbook_id or saved_item_id parameter)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (API key does not match)
        '404':
          description: Workbook not found
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