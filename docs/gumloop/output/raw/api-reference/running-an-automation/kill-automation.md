> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Kill flow run

> This endpoint is used to kill a flow run and all its subflow runs.



## OpenAPI

````yaml post /kill_pipeline
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /kill_pipeline:
    post:
      tags:
        - Execution
      summary: Kill flow run
      description: This endpoint is used to kill a flow run and all its subflow runs.
      operationId: killPipeline
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                run_id:
                  type: string
                  description: The ID of the pipeline run to kill.
                user_id:
                  type: string
                  description: The user ID. Required if project_id is not provided.
                project_id:
                  type: string
                  description: The project ID. Required if user_id is not provided.
              required:
                - run_id
      responses:
        '200':
          description: Pipeline killed successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  run_id:
                    type: string
                    description: The ID of the killed pipeline run.
        '400':
          description: Bad request (missing run_id)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (user does not have permission to kill this run)
        '404':
          description: Pipeline run not found
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