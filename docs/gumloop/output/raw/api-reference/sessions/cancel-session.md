> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Cancel session

> Cancel an in-progress session. If the session is currently `processing` or `queued`, any running stream is aborted and the session is transitioned to `failed`. If the session is already `completed` or `failed`, its current state is returned unchanged.

The response carries a `session` envelope but only `id`, `agent_id`, and `state` are populated.




## OpenAPI

````yaml post /sessions/{session_id}/cancel
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/cancel:
    post:
      tags:
        - Sessions
      summary: Cancel session
      description: >
        Cancel an in-progress session. If the session is currently `processing`
        or `queued`, any running stream is aborted and the session is
        transitioned to `failed`. If the session is already `completed` or
        `failed`, its current state is returned unchanged.


        The response carries a `session` envelope but only `id`, `agent_id`, and
        `state` are populated.
      operationId: cancelSession
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session to cancel.
      responses:
        '200':
          description: >-
            Session state after the cancel attempt. Only `id`, `agent_id`, and
            `state` are populated on the session envelope.
          content:
            application/json:
              schema:
                type: object
                properties:
                  session:
                    type: object
                    properties:
                      id:
                        type: string
                        example: sess_xYz789AbCd
                      agent_id:
                        type: string
                        example: abc123DEFghiJKL
                      messages:
                        type: array
                        items:
                          type: object
                      created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: null
                      state:
                        type: string
                        nullable: true
                        enum:
                          - processing
                          - completed
                          - failed
                          - queued
                          - idle
                          - approval_required
                        example: failed
                      agent_name:
                        type: string
                        nullable: true
                        example: null
                      agent_team_id:
                        type: string
                        nullable: true
                        example: null
                      agent_creator_user_id:
                        type: string
                        nullable: true
                        example: null
                      agent_icon_url:
                        type: string
                        nullable: true
                        example: null
                      agent_tools:
                        type: array
                        items:
                          type: object
                      participants:
                        type: object
                      creator:
                        type: object
                        nullable: true
                        properties:
                          id:
                            type: string
                            nullable: true
                          first_name:
                            type: string
                            nullable: true
                          last_name:
                            type: string
                            nullable: true
                          email:
                            type: string
                            nullable: true
                          profile_picture:
                            type: string
                            nullable: true
                      usage:
                        type: object
                        description: >-
                          Per-session usage totals. Credit and token counts
                          accumulate as the agent runs and are `null` until the
                          first run records usage.
                        properties:
                          credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Total credits consumed by the session, including
                              tool and flow (workflow) credits.
                            example: 12.5
                          tool_credit_cost:
                            type: number
                            nullable: true
                            description: Credits consumed by tool calls.
                            example: 4
                          flow_credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Credits consumed by workflow (flow) runs invoked
                              by the agent.
                            example: 1.5
                          input_tokens:
                            type: integer
                            nullable: true
                            description: Total input tokens across the session.
                            example: 18432
                          output_tokens:
                            type: integer
                            nullable: true
                            description: Total output tokens across the session.
                            example: 2043
                  queue_position:
                    type: integer
                    nullable: true
                    example: null
              examples:
                cancelled:
                  summary: Cancelled an in-progress session
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages: []
                      created_at: null
                      state: failed
                      agent_name: null
                      agent_team_id: null
                      agent_creator_user_id: null
                      agent_icon_url: null
                      agent_tools: []
                      participants: {}
                      creator: null
                    queue_position: null
                already_completed:
                  summary: Already in a terminal state — current state returned
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages: []
                      created_at: null
                      state: completed
                      agent_name: null
                      agent_team_id: null
                      agent_creator_user_id: null
                      agent_icon_url: null
                      agent_tools: []
                      participants: {}
                      creator: null
                    queue_position: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session not found.
        '500':
          description: >-
            Internal server error — the abort could not be persisted
            (`failed_to_abort`).
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/cancel' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.sessions.cancel("sess_xYz789AbCd")
            print(response.session.state)
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