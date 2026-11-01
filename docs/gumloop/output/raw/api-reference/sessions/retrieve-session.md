> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve session

> Retrieve a session by ID, including its messages, current state, agent metadata, and participants.



## OpenAPI

````yaml get /sessions/{session_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}:
    get:
      tags:
        - Sessions
      summary: Retrieve session
      description: >-
        Retrieve a session by ID, including its messages, current state, agent
        metadata, and participants.
      operationId: retrieveSession
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session to retrieve.
      responses:
        '200':
          description: The session.
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
                          properties:
                            id:
                              type: string
                              nullable: true
                              example: msg_a1b2c3
                            role:
                              type: string
                              nullable: true
                              example: user
                            content:
                              type: string
                              nullable: true
                              example: Research Acme Corp and draft a brief.
                            created_at:
                              type: string
                              format: date-time
                              nullable: true
                              example: '2026-05-15T14:32:00Z'
                            creator_id:
                              type: string
                              nullable: true
                              example: user_2b9d71f0
                            parts:
                              type: array
                              nullable: true
                              items:
                                type: object
                      created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-15T14:32:00Z'
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
                        example: completed
                      agent_name:
                        type: string
                        nullable: true
                        example: Sales research agent
                      agent_team_id:
                        type: string
                        nullable: true
                        example: team_4f8c92ab
                      agent_creator_user_id:
                        type: string
                        nullable: true
                        example: user_2b9d71f0
                      agent_icon_url:
                        type: string
                        nullable: true
                        example: null
                      agent_tools:
                        type: array
                        description: >-
                          Tools available to the agent. Secret references are
                          stripped before being returned.
                        items:
                          type: object
                      participants:
                        type: object
                        description: Map of participant user IDs to participant metadata.
                      pending_approvals:
                        type: array
                        description: >-
                          Pending asks (tool approvals, human input requests,
                          checkpoints) when the session is `approval_required`.
                          Empty when nothing is pending. Answer them via the
                          Resolve approvals endpoint.
                        items:
                          type: object
                          properties:
                            action_request_id:
                              type: string
                              nullable: true
                              description: >-
                                ID to pass back in `approval_responses` when
                                resolving.
                              example: areq_9f3k2m
                            type:
                              type: string
                              description: >-
                                Kind of ask, e.g. `tool_approval` or
                                `human_input`.
                              example: tool_approval
                            title:
                              type: string
                              nullable: true
                            reason:
                              type: string
                              nullable: true
                            recipient_user_id:
                              type: string
                              nullable: true
                            tool_name:
                              type: string
                              nullable: true
                              example: send_email
                            server_label:
                              type: string
                              nullable: true
                              example: Gmail
                            display_fields:
                              type: array
                              nullable: true
                              description: Label/value pairs describing the pending action.
                              items:
                                type: array
                                items:
                                  type: string
                            questions:
                              type: array
                              nullable: true
                              description: >-
                                For `human_input` asks, the questions to answer
                                via `response.values`.
                              items:
                                type: object
                      creator:
                        type: object
                        nullable: true
                        description: >-
                          Creator of the session. `null` when no creator is
                          recorded.
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
                    description: >-
                      Position in the per-agent queue. Populated only when the
                      session is currently queued; otherwise `null`.
                    example: null
              examples:
                completed:
                  summary: Completed session
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages:
                        - id: msg_a1b2c3
                          role: user
                          content: Research Acme Corp and draft a brief.
                          created_at: '2026-05-15T14:32:00Z'
                          creator_id: user_2b9d71f0
                          parts: null
                        - id: msg_d4e5f6
                          role: assistant
                          content: Here is what I found about Acme Corp...
                          created_at: '2026-05-15T14:32:09Z'
                          creator_id: null
                          parts: null
                      created_at: '2026-05-15T14:32:00Z'
                      state: completed
                      agent_name: Sales research agent
                      agent_team_id: team_4f8c92ab
                      agent_creator_user_id: user_2b9d71f0
                      agent_icon_url: null
                      agent_tools: []
                      participants:
                        user_2b9d71f0:
                          first_name: Ada
                          last_name: Lovelace
                      creator:
                        id: user_2b9d71f0
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
                      usage:
                        credit_cost: 12.5
                        tool_credit_cost: 4
                        flow_credit_cost: 1.5
                        input_tokens: 18432
                        output_tokens: 2043
                    queue_position: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have read access on the session.
        '404':
          description: Session not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl 'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            from gumloop import Gumloop


            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")


            response = client.sessions.retrieve("sess_xYz789AbCd")

            print(response.session.state)

            print(response.session.usage.credit_cost,
            response.session.usage.input_tokens)

            for message in response.session.messages:
                print(message.role, message.content)
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