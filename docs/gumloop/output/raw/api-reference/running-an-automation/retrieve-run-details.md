> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve run details

> This endpoint can be used to poll for completion and retrieve final flow outputs. Output nodes must be used to retrieve outputs.



## OpenAPI

````yaml get /get_pl_run
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /get_pl_run:
    get:
      tags:
        - Data Access
      summary: Retrieve run details
      description: >-
        This endpoint can be used to poll for completion and retrieve final flow
        outputs. Output nodes must be used to retrieve outputs.
      operationId: getAutomationRun
      parameters:
        - in: query
          name: run_id
          required: true
          schema:
            type: string
          description: ID of the flow run to retrieve
        - in: query
          name: user_id
          required: false
          schema:
            type: string
          description: >-
            The id for the user initiating the flow. Required if project_id is
            not provided.
        - in: query
          name: project_id
          required: false
          schema:
            type: string
          description: >-
            The id of the project within which the flow is executed. Required if
            user_id is not provided.
      responses:
        '200':
          description: Successful retrieval of flow run details
          content:
            application/json:
              schema:
                type: object
                properties:
                  user_id:
                    type: string
                    description: The id for the user initiating the flow.
                  state:
                    type: string
                    enum:
                      - RUNNING
                      - DONE
                      - TERMINATING
                      - FAILED
                      - TERMINATED
                      - QUEUED
                  outputs:
                    type: object
                    description: >-
                      JSON object where keys are the `output_name` parameters of
                      your Gumloop Output nodes and the values are the values
                      that get sent to your node.
                  created_ts:
                    type: string
                    format: date-time
                    description: Timestamp for when the flow was started.
                  finished_ts:
                    type: string
                    format: date-time
                    description: Timestamp for when the flow completed.
                  log:
                    type: array
                    items:
                      type: string
                    description: A list of log entries from your Gumloop flow run.
        '400':
          description: Bad request (missing run_id)
        '404':
          description: Flow run not found
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